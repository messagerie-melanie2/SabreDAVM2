<?php
/**
 * Fichier de gestion du backend Principal pour l'application SabreDAVM2
 * Utilise l'ORM M2 pour l'accès aux données Mélanie2
 *
 * SabreDAVM2 Copyright (C) 2015  PNE Annuaire et Messagerie/MEDDE
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
namespace Sabre\DAVACL\PrincipalBackend;

use
    Sabre\DAV,
    Sabre\DAVACL,
    Sabre\HTTP\URLUtil;

/**
 * LibM2 principal backend
 *
 *
 * This backend assumes all principals are in a single collection. The default collection
 * is 'principals/', but this can be overriden.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBackend {
  /**
   * Authenfication backend
   *
   * @var \Sabre\DAV\Auth\Backend\LibM2
   */
  protected $authBackend;

  /**
   * A list of additional fields to support
   *
   * @var array
   */
  protected $fieldMap = [

      /**
       * This property can be used to display the users' real name.
       */
      '{DAV:}displayname' => [
          'ldapField' => 'cn',
      ],

      /**
       * This is the users' primary email-address.
       */
      '{http://sabredav.org/ns}email-address' =>[
          'ldapField' => 'mail',
      ],
  ];

  /**
   * Sets up the backend.
   *
   * @param \Sabre\DAV\Auth\Backend\LibM2 $authBackend
   */
  function __construct(\Sabre\DAV\Auth\Backend\LibM2 $authBackend) {
    error_log("[PrincipalBackend] LibM2.__construct()");
    $this->authBackend = $authBackend;
  }

  /**
   * Récupère l'utilisateur lié au principalURI
   */
  protected function getUserFromPrincipalUri($principalUri) {
    $var = explode('/', $principalUri);
    return $var[1];
  }


  /**
   * Returns a list of principals based on a prefix.
   *
   * This prefix will often contain something like 'principals'. You are only
   * expected to return principals that are in this base path.
   *
   * You are expected to return at least a 'uri' for every user, you can
   * return any additional properties if you wish so. Common properties are:
   *   {DAV:}displayname
   *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
   *     field that's actualy injected in a number of other properties. If
   *     you have an email address, use this property.
   *
   * @param string $prefixPath
   * @return array
   */
  function getPrincipalsByPrefix($prefixPath) {
    error_log("[PrincipalBackend] LibM2.getPrincipalsByPrefix($prefixPath)");

    $user_uid = $this->authBackend->getCurrentUser();
    $infos = \LibMelanie\Ldap\Ldap::GetUserInfos($user_uid);

    $principal = [
      'id'  => $prefixPath.'/'.$user_uid,
      'uri' => $prefixPath.'/'.$user_uid,
    ];

    if (isset($infos)
        && count($infos) > 0) {
      foreach($this->fieldMap as $key => $val) {
        $value = $infos[$val['ldapField']];
        if (is_array($value)) {
          if (isset($value[0])) {
            $principal[$key] = $value[0];
          }
        } else {
          $principal[$key] = $value;
        }
      }
    }
    return [$principal];
  }

  /**
   * Returns a specific principal, specified by it's path.
   * The returned structure should be the exact same as from
   * getPrincipalsByPrefix.
   *
   * @param string $path
   * @return array
   */
  function getPrincipalByPath($path) {
    error_log("[PrincipalBackend] LibM2.getPrincipalByPath($path)");

    $user_uid = $this->getUserFromPrincipalUri($path);
    $infos = \LibMelanie\Ldap\Ldap::GetUserInfos($user_uid);
    $principal = [
      'id'  => $path,
      'uri' => $path,
    ];

    if (isset($infos)
        && count($infos) > 0) {
      foreach($this->fieldMap as $key => $val) {
        $value = $infos[$val['ldapField']];
        if (is_array($value)) {
          if (isset($value[0])) {
            $principal[$key] = $value[0];
          }
        } else {
          $principal[$key] = $value;
        }
      }
    }

    return $principal;
  }

  /**
   * Updates one ore more webdav properties on a principal.
   *
   * The list of mutations is stored in a Sabre\DAV\PropPatch object.
   * To do the actual updates, you must tell this object which properties
   * you're going to process with the handle() method.
   *
   * Calling the handle method is like telling the PropPatch object "I
   * promise I can handle updating this property".
   *
   * Read the PropPatch documenation for more info and examples.
   *
   * @param string $path
   * @param \Sabre\DAV\PropPatch $propPatch
   */
  function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {
    error_log("[PrincipalBackend] LibM2.updatePrincipal($path)");
    return false;
  }

  /**
   * This method is used to search for principals matching a set of
   * properties.
   *
   * This search is specifically used by RFC3744's principal-property-search
   * REPORT.
   *
   * The actual search should be a unicode-non-case-sensitive search. The
   * keys in searchProperties are the WebDAV property names, while the values
   * are the property values to search on.
   *
   * By default, if multiple properties are submitted to this method, the
   * various properties should be combined with 'AND'. If $test is set to
   * 'anyof', it should be combined using 'OR'.
   *
   * This method should simply return an array with full principal uri's.
   *
   * If somebody attempted to search on a property the backend does not
   * support, you should simply return 0 results.
   *
   * You can also just return 0 results if you choose to not support
   * searching at all, but keep in mind that this may stop certain features
   * from working.
   *
   * @param string $prefixPath
   * @param array $searchProperties
   * @param string $test
   * @return array
   */
  function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
    error_log("[PrincipalBackend] LibM2.searchPrincipals($prefixPath, ".var_export($searchProperties, true).", $test)");

    if (isset($searchProperties)) {
      if (isset($searchProperties['{http://sabredav.org/ns}email-address'])) {
        $infos = \LibMelanie\Ldap\Ldap::GetUserInfosFromEmail($searchProperties['{http://sabredav.org/ns}email-address']);
      }
    }

    if (isset($infos)) {
      $user_uid = $infos['uid'][0];
      $principal = [
      'uri' => $prefixPath.'/'.$user_uid,
      ];
      return $principal;
    }

    return [];
  }

  /**
   * Returns the list of members for a group-principal
   *
   * @param string $principal
   * @return array
   */
  function getGroupMemberSet($principal) {
    error_log("[PrincipalBackend] LibM2.getGroupMemberSet($principal)");
    return [];
  }

  /**
   * Returns the list of groups a principal is a member of
   *
   * @param string $principal
   * @return array
   */
  function getGroupMembership($principal) {
    error_log("[PrincipalBackend] LibM2.getGroupMembership($principal)");
    return [];
  }

  /**
   * Updates the list of group members for a group principal.
   *
   * The principals should be passed as a list of uri's.
   *
   * @param string $principal
   * @param array $members
   * @return void
   */
  function setGroupMemberSet($principal, array $members) {
    error_log("[PrincipalBackend] LibM2.setGroupMemberSet($principal)");
  }
}
