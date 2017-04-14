<?php
/**
 * Fichier de gestion du backend Auth pour l'application SabreDAVM2
 * Utilise l'ORM M2 pour l'accès aux données Mélanie2
 *
 * SabreDAVM2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
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
namespace Sabre\DAV\Auth\Backend;

/**
 * This is an authentication backend that uses a database to manage passwords.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBasic {
	/**
	 * 
	 * @var boolean
	 */
	protected $noauth;
	
  /**
   * Creates the backend object.
   *
   * If the filename argument is passed in, it will parse out the specified file fist.
   */
  public function __construct() {
  	$this->noauth = false;
  }
  /**
   * Défini si on est dans le cas d'une method REPORT sans authentification (webdav sync)
   * @param boolean $noAuth
   */
  public function setNoAuthReportMethod($noAuth = false) {
  	$this->noauth = $noAuth;
  }
  
  /**
   * Validates a username and password
   *
   * This method should return true or false depending on if login
   * succeeded.
   *
   * @param string $username
   * @param string $password
   * @return bool
   */
  protected function validateUserPass($username, $password) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[AuthBackend] LibM2.validateUserPass($username) noauth = " . $this->noauth);
    // Si c'est une boite partagée, on s'authentifie sur l'utilisateur pas sur la bal
    if (strpos($username, '.-.') !== false) {
      // MANTIS 3791: Gestion de l'authentification via des boites partagées
      $tmp = explode('.-.', $username, 2);
      $username = $tmp[0];
      if (isset($tmp[1])) {
        // TODO: Valider que la bali a bien les droits emission sur la balp
        if (!$this->checkBalfPrivileges($username, $tmp[1])) {
          return false;
        }
      }
    }
    // Gestion de l'authentification via l'ORM M2
    if ($this->noauth || \LibMelanie\Ldap\Ldap::Authentification($username, $password)) {
      return true;
    }
    else {
      // MANTIS 1709: Quand un utilisateur à un probleme de connexion sur le LDAP, l'authentification CalDAV boucle
      sleep(2);
      return false;
    }
  }
  /**
   * Permet de valider qu'un utilisateur à bien les droits d'écriture sur une balf
   * Nécessaire pour les droits sur les agendas
   * @param string $username
   * @param string $balf
   * @return boolean
   */
  private function checkBalfPrivileges($username, $balf) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[AuthBackend] LibM2.checkBalfPrivileges($username, $balf)");
    // Liste des droits pour l'écriture
    $droits = ['C','G'];
    // Récupère le champ mineqmelpartages pour les partages de boites dans le LDAP
    $infos = \LibMelanie\Ldap\Ldap::GetUserInfos($balf, null, ["mineqmelpartages"]);
    if ($infos !== false) {
      foreach ($infos['mineqmelpartages'] as $melPartage) {
        foreach ($droits as $droit) {
          // Si le droit matche, c'est bon
          if ($melPartage == "$username:$droit") {
            return true;
          }
        }
      }
    }
    // Pas de droit trouvé, l'utilisateur n'a pas les droits sur la balf
    return false;
  }
}
