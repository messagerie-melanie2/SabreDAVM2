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

use LibMelanie\Config\Ldap;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * This is an authentication backend based on ORM MCE
 */
class LibM2 extends AbstractBasic implements LibM2AuthInterface {
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
    if (\driver::gi()->isBalp($username)) {
      // MANTIS 3791: Gestion de l'authentification via des boites partagées
      list($username, $balpname) = \driver::gi()->getBalpnameFromUsername($username);
      if (isset($balpname)) {
        // Valider que la bali a bien les droits emission sur la balp
        if (!$this->checkBalfPrivileges($username, $balpname)) {
          return false;
        }
      }
    }
    // Gestion du user
    $user = \driver::new('User', Ldap::$AUTH_LDAP);
    $user->uid = $username;
    // Gestion de l'authentification via l'ORM M2
    if ($this->noauth || $user->load() && $user->authentification($password)) {
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
   * 
   * @param string $username
   * @param string $balf
   * 
   * @return boolean
   */
  protected function checkBalfPrivileges($username, $balf) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[AuthBackend] LibM2.checkBalfPrivileges($username, $balf)");
    $user = \driver::new('User');
    $user->uid = $balf;
    if ($user->load('shares')) {
      // Est-ce que l'utilisateur a des droits d'émission sur la balf ?
      return $user->asRight($username, \driver::const('User', 'RIGHT_SEND'));
    }
    // La balf n'existe pas
    return false;
  }

  /**
   * When this method is called, the backend must check if authentication was
   * successful.
   *
   * The returned value must be one of the following
   *
   * [true, "principals/username"]
   * [false, "reason for failure"]
   *
   * If authentication was successful, it's expected that the authentication
   * backend returns a so-called principal url.
   *
   * Examples of a principal url:
   *
   * principals/admin
   * principals/user1
   * principals/users/joe
   * principals/uid/123457
   *
   * If you don't use WebDAV ACL (RFC3744) we recommend that you simply
   * return a string such as:
   *
   * principals/users/[username]
   *
   * @param RequestInterface $request
   * @param ResponseInterface $response
   * @return array
   */
  public function check(RequestInterface $request, ResponseInterface $response) {

    $auth = new HTTP\Auth\Basic(
        $this->realm,
        $request,
        $response
    );

    $userpass = $auth->getCredentials($request);
    if (!$userpass) {
        return [false, "No 'Authorization: Basic' header found. Either the client didn't send one, or the server is mis-configured"];
    }
    if (!$this->validateUserPass($userpass[0], $userpass[1])) {
        return [false, "Username or password was incorrect"];
    }
    return [true, $this->principalPrefix . \driver::gi()->convertUser($userpass[0])];
  }
}
