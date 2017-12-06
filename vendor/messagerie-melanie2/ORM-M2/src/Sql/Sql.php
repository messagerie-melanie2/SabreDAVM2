<?php

/**
 * Ce fichier est développé pour la gestion de la librairie Mélanie2
 * Cette Librairie permet d'accèder aux données sans avoir à implémenter de couche SQL
 * Des objets génériques vont permettre d'accèder et de mettre à jour les données
 *
 * ORM M2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace LibMelanie\Sql;

use LibMelanie\Cache\Cache;
use LibMelanie\Log\M2Log;
use LibMelanie\Lib\Selaforme;
use LibMelanie\Config\ConfigMelanie;
use LibMelanie\Exceptions;

/**
 * Gestion de la connexion Sql
 *
 * @author PNE Messagerie/Apitech
 * @package Librairie Mélanie2
 * @subpackage SQL
 */
class Sql {
  /**
   * Connexion PDO en cours
   *
   * @var PDO
   */
  private $connection = null;
  /**
   * String de connexion
   *
   * @var string
   */
  private $cnxstring;
  /**
   * Utilisateur SQL
   *
   * @var string
   */
  private $username;
  /**
   * Mot de passe SQL
   *
   * @var string
   */
  private $password;
  /**
   * Connexion persistante
   *
   * @var bool
   */
  private $persistent;
  /**
   * Connexion PDO en cours pour la lecture
   *
   * @var PDO
   */
  private $connection_read = null;
  /**
   * String de connexion pour la lecture
   *
   * @var string
   */
  private $cnxstring_read;
  /**
   * Utilisateur SQL pour la lecture
   *
   * @var string
   */
  private $username_read;
  /**
   * Mot de passe SQL pour la lecture
   *
   * @var string
   */
  private $password_read;
  /**
   * Connexion persistante pour la lecture
   *
   * @var bool
   */
  private $persistent_read;
  /**
   * Mise en cache des statements par requete SQL
   * Voir MANTIS 3547: Réutiliser les prepare statements pour les requêtes identiques
   *
   * @var array
   */
  private $PreparedStatementCache;
  /**
   * Classe courante
   *
   * @var string
   */
  protected $get_class;
  /**
   * Resource vers les selaformes
   *
   * @var resource
   */
  private $ret_sel;

  /**
   * Constructor SQL
   *
   * @param array $db configuration vers la base de données
   * @access public
   */
  public function __construct($db, $db_read = null) {
    // Défini la classe courante
    $this->get_class = get_class($this);

    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->__construct()");
    // Définition des données de connexion
    $this->cnxstring = "pgsql:dbname=$db[database];host=$db[hostspec];port=$db[port]";
    $this->username = $db['username'];
    $this->password = $db['password'];
    $this->persistent = $db['persistent'];
    // Définition des données de connexion pour la lecture
    if (isset($db_read)) {
      $this->cnxstring_read = "pgsql:dbname=$db_read[database];host=$db_read[hostspec];port=$db_read[port]";
      $this->username_read = $db_read['username'];
      $this->password_read = $db_read['password'];
      $this->persistent_read = $db_read['persistent'];
    }
    // Mise en cache des statements
    // MANTIS 3547: Réutiliser les prepare statements pour les requêtes identiques
    $this->PreparedStatementCache = [];
    $this->getConnection();
  }

  /**
   * Destructor SQL
   *
   * @access public
   */
  public function __destruct() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->__destruct()");
    $this->disconnect();
  }

  /**
   * Connect to sql database
   *
   * @throws Melanie2DatabaseException
   *
   * @access private
   */
  private function connect() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->connect()");
    // Utilisation des selaformes
    if (ConfigMelanie::SEL_ENABLED) {
      $this->ret_sel = Selaforme::selaforme_acquire(ConfigMelanie::SEL_MAX_ACQUIRE, ConfigMelanie::SEL_FILE_NAME);
      if ($this->ret_sel === false) {
        throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Selaforme maximum atteint : " . ConfigMelanie::SEL_MAX_ACQUIRE, 11);
      }
    }
    // Connexion persistante ?
    $driver = [\PDO::ATTR_PERSISTENT => ($this->persistent == 'true'),\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    try {
      $this->connection = new \PDO($this->cnxstring, $this->username, $this->password, $driver);
    }
    catch (\PDOException $e) {
      // Erreur de connexion
      M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->connect(): Erreur de connexion à la base de données\n" . $e->getMessage());
      throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Erreur de connexion", 21);
    }
    // Connexion à la base de données en lecture
    if (isset($this->cnxstring_read)) {
      // Connexion persistante ?
      $driver_read = [\PDO::ATTR_PERSISTENT => ($this->persistent_read == 'true'),\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
      try {
        $this->connection_read = new \PDO($this->cnxstring_read, $this->username_read, $this->password_read, $driver_read);
      }
      catch (\PDOException $e) {
        // Erreur de connexion
        M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->connect(): Erreur de connexion à la base de données en lecture\n" . $e->getMessage());
        $this->connection_read = null;
      }
    }
    return true;
  }

  /**
   * Disconnect from SQL database
   *
   * @access public
   */
  public function disconnect() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->disconnect()");
    // Fermer tous les statements
    $this->PreparedStatementCache = [];
    // Deconnexion de la bdd
    if (! is_null($this->connection)) {
      $this->connection = null;
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->disconnect() Close connection");
    }
    // Deconnexion de la bdd pour la lecture
    if (! is_null($this->connection_read)) {
      $this->connection_read = null;
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->disconnect() Close connection read");
    }
    // Utilisation des selaformes
    if (ConfigMelanie::SEL_ENABLED && $this->ret_sel !== false) {
      Selaforme::selaforme_release($this->ret_sel);
    }
  }

  /**
   * Get the active connection to the sql database
   *
   * @access private
   */
  public function getConnection() {
    // Si la connexion n'existe pas, on se connecte
    if (is_null($this->connection)) {
      if (! $this->connect()) {
        $this->connection = null;
      }
    }
  }

  /**
   * Execute a sql query to the active database connection in PDO
   * If query start by SELECT
   * return an array of array of data
   *
   * @param string $query
   * @param array $params
   * @param string $class
   * @param string $objectType
   * @param boolean $cached_statement Utiliser le cache pour les statements
   * @return mixed array de resultat, true
   * @throws Melanie2DatabaseException
   *
   * @access public
   */
  public function executeQuery($query, $params, $class, $objectType, $cached_statement = true) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->executeQuery($query, $class)");
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->executeQuery() params : " . print_r($params, true));
    // Si la connexion n'est pas instanciée
    if (is_null($this->connection)) {
      // Throw exception, erreur
      M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->executeQueryToObject(): Problème de connexion à la base de données");
      throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Erreur de connexion", 21);
    }
    // Configuration de la réutilisation des prepares statements
    $cached_statement = $cached_statement & ConfigMelanie::REUSE_PREPARE_STATEMENT;
    // Si la requête demarre par SELECT on retourne les resultats
    // Sinon on retourne true (UPDATE/DELETE pas de resultat)
    // Récupération des données du cache
    if (strpos($query, "SELECT") === 0) {
      // Récupération du cache
      $cache = Cache::getFromSQLCache(null, is_array($params) ? array_keys($params) : $params, $query, $params);
      if (! is_null($cache) && $cache !== false) {
        return $cache;
      }
    }
    try {
      if ($cached_statement && isset($this->PreparedStatementCache[$query])) {
        // Récupérer le statement depuis le cache
        $sth = $this->PreparedStatementCache[$query];
      }
      else {
        // Choix de la connexion lecture/ecriture
        if (strpos($query, "SELECT") === 0 && ! is_null($this->connection_read) && ! $this->connection->inTransaction) {
          if (! isset($this->connection_read)) {
            return null;
          }
          $sth = $this->connection_read->prepare($query);
        }
        else {
          if (! isset($this->connection)) {
            return null;
          }
          $sth = $this->connection->prepare($query);
        }
        if ($cached_statement) {
          // Mise en cache du statement
          $this->PreparedStatementCache[$query] = $sth;
        }
      }
      if (isset($class))
        $sth->setFetchMode(\PDO::FETCH_CLASS, $class);
      else
        $sth->setFetchMode(\PDO::FETCH_BOTH);
      if (isset($params))
        $res = $sth->execute($params);
      else
        $res = $sth->execute();
    }
    catch (\PDOException $ex) {
      // Throw exception, erreur
      M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->executeQuery(): Exception $ex");
      throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Erreur d'execution de la requête", 22);
    }
    // Tableau de stockage des données sql
    $arrayData = Array();
    // Si la requête demarre par SELECT on retourne les resultats
    // Sinon on retourne true (UPDATE/DELETE pas de resultat)
    if (strpos($query, "SELECT") === 0) {
      while ($object = $sth->fetch()) {
        if (isset($class) && method_exists($object, "pdoConstruct")) {
          if (isset($objectType))
            $object->pdoConstruct(true, $objectType);
          else
            $object->pdoConstruct(true);
        }
        $arrayData[] = $object;
      }
      Cache::setSQLToCache(null, is_array($params) ? array_keys($params) : $params, $query, $params, $arrayData);
      $sth->closeCursor();
      return $arrayData;
    }
    else {
      // Suppression dans le cache
      Cache::deleteFromSQLCache(null, null, $query);
      // Retourne le resultat de l'execution
      return $res;
    }
    // Retourne null, pas de resultat
    return false;
  }

  /**
   * Execute a sql query to the active database connection in PDO
   * If query start by SELECT
   *
   * @param string $query
   * @param array $params
   * @param mixed $object
   * @param boolean $cached_statement Utiliser le cache pour les statements
   * @return boolean
   *
   * @throws Melanie2DatabaseException
   *
   * @access public
   */
  public function executeQueryToObject($query, $params, $object, $cached_statement = true) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->executeQueryToObject($query, " . get_class($object) . ")");
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->executeQueryToObject() params : " . print_r($params, true));
    // Si la connexion n'est pas instanciée
    if (is_null($this->connection)) {
      // Throw exception, erreur
      M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->executeQueryToObject(): Problème de connexion à la base de données");
      throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Erreur de connexion", 21);
    }
    // Configuration de la réutilisation des prepares statements
    $cached_statement = $cached_statement & ConfigMelanie::REUSE_PREPARE_STATEMENT;
    // Si la requête demarre par SELECT on retourne les resultats
    // Sinon on retourne null (UPDATE/DELETE pas de resultat)
    // Récupération des données du cache
    if (strpos($query, "SELECT") == 0) {
      // Récupération du cache
      $cache = Cache::getFromSQLCache(null, is_array($params) ? array_keys($params) : $params, $query, $params, $object);
      if (! is_null($cache) && $cache !== false) {
        if (method_exists($object, "__copy_from")) {
          if ($object->__copy_from($cache)) {
            return true;
          }
        }
      }
    }
    try {
      if ($cached_statement && isset($this->PreparedStatementCache[$query])) {
        // Récupérer le statement depuis le cache
        $sth = $this->PreparedStatementCache[$query];
      }
      else {
        // Choix de la connexion lecture/ecriture
        if (strpos($query, "SELECT") === 0 && ! is_null($this->connection_read) && ! $this->connection->inTransaction) {
          if (! isset($this->connection_read)) {
            return false;
          }
          $sth = $this->connection_read->prepare($query);
        }
        else {
          if (! isset($this->connection)) {
            return false;
          }
          $sth = $this->connection->prepare($query);
        }
        if ($cached_statement) {
          // Mise en cache du statement
          $this->PreparedStatementCache[$query] = $sth;
        }
      }
      $sth->setFetchMode(\PDO::FETCH_INTO, $object);
      if (isset($params))
        $res = $sth->execute($params);
      else
        $res = $sth->execute();
    }
    catch (\PDOException $ex) {
      // Throw exception, erreur
      M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->executeQueryToObject(): Exception $ex");
      throw new Exceptions\Melanie2DatabaseException("Erreur de base de données Mélanie2 : Erreur d'execution de la requête", 23);
    }
    // Si la requête demarre par SELECT on retourne les resultats
    // Sinon on retourne null (UPDATE/DELETE pas de resultat)
    if (strpos($query, "SELECT") == 0) {
      if ($sth->fetch(\PDO::FETCH_INTO)) {
        Cache::setSQLToCache(null, is_array($params) ? array_keys($params) : $params, $query, $params, $object);
        $sth->closeCursor();
        // Retourne true, l'objet est trouvé
        return true;
      }
      else {
        // Retourne false, l'objet n'est pas trouvé
        return false;
      }
    }
    else {
      // Suppression dans le cache
      Cache::deleteFromSQLCache(null, null, $query);
      return $res;
    }
    // Retourne null, pas de resultat
    return false;
  }

  /**
   * Begin a PDO transaction
   */
  public function beginTransaction() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->beginTransaction()");
    $this->connection->beginTransaction();
  }

  /**
   * Commit a PDO transaction
   */
  public function commit() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->commit()");
    $this->connection->commit();
  }

  /**
   * Rollback a PDO transaction
   */
  public function rollBack() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->rollBack()");
    $this->connection->rollBack();
  }
}
?>