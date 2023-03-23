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

use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * This is an authentication backend based on Kerberos and ORM MCE
 */
class LibM2Krb extends Apache implements LibM2AuthInterface {
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
    $remoteUser = $request->getRawServerValue('REMOTE_USER');
    if (is_null($remoteUser)) {
      $remoteUser = $request->getRawServerValue('REDIRECT_REMOTE_USER');
    }
    if (is_null($remoteUser)) {
      return [false, 'No REMOTE_USER property was found in the PHP $_SERVER super-global. This likely means your server is not configured correctly'];
    }

    $filter = str_replace('%%username%%', $remoteUser, \Config\Config::krbldapfilter);

    try {
      $infos = \LibMelanie\Ldap\Ldap::GetUserInfos(null, $filter, ["mail"]);
    } catch (\LibMelanie\Exceptions\Melanie2LdapException $e) {
      return [false, "Erreur LDAP à la recherche du principal $remoteUser: ".$e->getMessage()];
    }

    if ($infos === false) {
      return [false, "Principal Kerberos $remoteUser non trouvé dans l'annuaire"];
    }
    if (!is_countable($infos['mail']) || count($infos['mail']) < 1) {
      return [false, "Mail non trouvé pour le principal Kerberos $remoteUser"];
    }

    return [true, $this->principalPrefix . $infos['mail'][0]];
  }
}
