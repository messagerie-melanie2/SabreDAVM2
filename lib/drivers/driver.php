<?php
/**
 * Plugin Mél
 *
 * Moteur de drivers pour le plugin mel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

abstract class driver {
  /**
   * Separator used for object shared
   */
  protected static $_objectSharedSeparator = "";

  /**
   * Namespace for the objets
   */
  protected static $_objectsNS = "";

  /**
   * Singleton
   *
   * @var driver
   */
  private static $driver;
  
  /**
   * Return the singleton instance
   *
   * @return driver
   */
  public static function get_instance() {
    if (!isset(self::$driver)) {
      $drivername = strtolower(\Config\Config::driver);
      require_once $drivername . '/' . $drivername . '.php';
      $drivername = $drivername . '_driver';
      self::$driver = new $drivername();
    }
    return self::$driver;
  }

  /**
   * get_instance short
   *
   * @return driver
   */
  public static function gi() {
    return self::get_instance();
  }

  /**
   * Get object shared separator for the current driver
   * 
   * @return string
   */
  public function objectSharedSeparator() {
    return static::$_objectSharedSeparator;
  }

  /**
   * Get object namespace for the current driver
   * 
   * @return string
   */
  public function objectNS() {
    return static::$_objectsNS;
  }

  /**
   * Generate an object from the ORM with the right Namespace
   * 
   * @param string $objectName Object name (add sub namespace if needed, ex : Event, Users\Type)
   * @param array $params [Optionnal] parameters of the constructor
   * 
   * @return staticClass object of the choosen type
   */
  protected function object($objectName, $params = []) {
    $class = new \ReflectionClass(static::$_objectsNS . $objectName);
    if (!is_array($params)) {
      $params = [$params];
    }
    return $class->newInstanceArgs($params);
  }

  /**
   * Create a new object from objectName, NS and arguments
   * 
   * @param string $objectName Name of the object to instanciate
   * @param array $arguments List of arguments for the object
   * 
   * @return object
   */
  public static function new($objectName, ...$arguments) {
    return self::gi()->object($objectName, $arguments);
  }

  /**
   * Return constantName value from objectName and NS
   * 
   * @param string $objectName Name of the object
   * @param string $constantName Name of the constant
   * 
   * @return mixed constant value
   */
  public static function const($objectName, $constantName) {
    return constant(self::$driver::$_objectsNS . $objectName . '::' . $constantName);
  }

  /**
   * Retourne si le username est une boite partagée ou non
   *
   * @param string $username
   * @return boolean
   */
  public function isBalp($username) {
    return strpos($username, static::$_objectSharedSeparator) !== false;
  }

  /**
   * Retourne le username et le balpname à partir d'un username complet
   * balpname sera null si username n'est pas un objet de partage
   * username sera nettoyé de la boite partagée si username est un objet de partage
   *
   * @param string $username Username à traiter peut être un objet de partage ou non
   * 
   * @return array($username, $balpname) $username traité, $balpname si objet de partage ou null sinon
   */
  public function getBalpnameFromUsername($username) {
    // On peut recevoir un radical principals/ depuis sabredav
    $principals = "";
    if (substr($username, 0, 11 ) === "principals/") {
      $principals = "principals/";
      $username = substr($username, 11);
    }
    list($username, $balpname) = explode(static::$_objectSharedSeparator, $username, 2);

    if (isset($balpname)) {
      $balpname = "$principals$balpname";
    }

    return array("$principals$username", $balpname);
  }

  /**
   * Permet de convertir un username 
   * au moment du check de validation de l'authentification
   * A utiliser pour une authentification email qui doit donner un uid
   * 
   * @param string $username Username a convertir en entrée
   * 
   * @return string $convertUsername Username converti
   */
  public function convertUser($username) {
    return $username;
  }
}
