<?php

/**
 * Fichier de gestion du backend Principal pour l'application SabreDAVM2
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
namespace Sabre\DAVACL\PrincipalBackend;

use Sabre\DAV, Sabre\DAVACL, Sabre\HTTP\URLUtil;

/**
 * LibM2 principal backend
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
   * Calendar backend
   *
   * @var \Sabre\CalDAV\Backend\LibM2
   */
  protected $calendarBackend;
  /**
   * Contact backend
   *
   * @var \Sabre\CardDAV\Backend\LibM2
   */
  protected $contactBackend;
  /**
   * Instance du serveur SabreDAV
   * Permet d'accéder à la requête et à la réponse
   *
   * @var \Sabre\DAV\Server
   */
  protected $server;
  /**
   * A list of additional fields to support
   *
   * @var array
   */
  protected $fieldMap = [
	  /**
	   * This property can be used to display the users' real name.
	   */
	  '{DAV:}displayname' => ['ldapField' => 'cn'],/**
	   * This is the users' primary email-address.
	   */
	  '{http://sabredav.org/ns}email-address' => ['ldapField' => 'mineqmelmailemission']  		
  ];
  /**
   * Ne pas faire de recherche du principal dans le LDAP
   * @var boolean
   */
  protected $noPrincipalSearch;
  /**
   * Sets up the backend.
   *
   * @param \Sabre\DAV\Auth\Backend\LibM2 $authBackend
   */
  public function __construct(\Sabre\DAV\Auth\Backend\LibM2 $authBackend) {
    $this->authBackend = $authBackend;
    $this->noPrincipalSearch = false;
  }
  /**
   * Récupération de l'instance du serveur SabreDAV
   *
   * @param \Sabre\DAV\Server $server
   */
  public function setServer(\Sabre\DAV\Server $server) {
    $this->server = $server;
  }
  /**
   * Sets up the calendar backend
   * 
   * @param \Sabre\CalDAV\Backend\LibM2 $calendarBackend
   */
  public function setCalendarBackend(\Sabre\CalDAV\Backend\LibM2 $calendarBackend) {
  	$this->calendarBackend = $calendarBackend;
  }
  /**
   * Sets up the contact backend
   * 
   * @param \Sabre\CardDAV\Backend\LibM2 $contactBackend
   */
  public function setContactBackend(\Sabre\CardDAV\Backend\LibM2 $contactBackend) {
  	$this->contactBackend = $contactBackend;
  }
  /**
   * Set up the noPrincipalSearch variable
   * 
   * @param boolean $noPrincipalSearch
   */
  public function setNoPrincipalSearch($noPrincipalSearch = false) {
  	$this->noPrincipalSearch = $noPrincipalSearch;
  }
  /**
   * Récupère l'utilisateur lié au principalURI
   */
  protected function getUserFromPrincipalUri($principalUri) {
    $var = explode('/', $principalUri);
    $username = $var[1];
    return $username;
  }
  /**
   * Returns a list of principals based on a prefix.
   * This prefix will often contain something like 'principals'. You are only
   * expected to return principals that are in this base path.
   * You are expected to return at least a 'uri' for every user, you can
   * return any additional properties if you wish so. Common properties are:
   * {DAV:}displayname
   * {http://sabredav.org/ns}email-address - This is a custom SabreDAV
   * field that's actualy injected in a number of other properties. If
   * you have an email address, use this property.
   *
   * @param string $prefixPath
   * @return array
   */
  public function getPrincipalsByPrefix($prefixPath) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getPrincipalsByPrefix($prefixPath)");

    if (isset($this->calendarBackend)) {
    	// Get shared calendar
    	$calendars = $this->calendarBackend->loadUserCalendars();
    	$calendars_owners = [];
    	
    	foreach ($calendars as $calendar) {
    		if (! in_array($calendar->owner, $calendars_owners)) {
    			$calendars_owners[] = $calendar->owner;
    		}
    	}
    	// List principals
    	$principals = [];
    	foreach ($calendars_owners as $owner) {
    		$infos = \LibMelanie\Ldap\Ldap::GetUserInfos($owner);
    	
    		$principal = ['id' => $prefixPath . '/' . $owner, 'uri' => $prefixPath . '/' . $owner];
    	
    		if (isset($infos) && count($infos) > 0) {
    			foreach ($this->fieldMap as $key => $val) {
    				$value = $infos[$val['ldapField']];
    				if (is_array($value)) {
    					if (isset($value[0])) {
    						$principal[$key] = $value[0];
    					}
    				}
    				else {
    					$principal[$key] = $value;
    				}
    			}
    		}
    		$principals[] = $principal;
    	}
    }    

    return $principals;
  }
  /**
   * Returns a specific principal, specified by it's path.
   * The returned structure should be the exact same as from
   * getPrincipalsByPrefix.
   *
   * @param string $path
   * @return array
   */
  public function getPrincipalByPath($path) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getPrincipalByPath($path)");

    $user_uid = $this->getUserFromPrincipalUri($path);
    $principal = ['id' => "principals/$user_uid",'uri' => "principals/$user_uid"];
    
    if ($this->noPrincipalSearch) {
    	$principal['{DAV:}displayname'] = $user_uid;
    }
    else {
      $filter = null;
      if (strpos($user_uid, '.-.')) {
        $filter = "(&(objectClass=mineqMelObjetPartage)(uid=$user_uid))";
      }      
      $infos = \LibMelanie\Ldap\Ldap::GetUserInfos($user_uid, $filter);
    	
    	if (isset($infos) && count($infos) > 0) {
    		foreach ($this->fieldMap as $key => $val) {
    			$value = $infos[$val['ldapField']];
    			if (is_array($value)) {
    				if (isset($value[0])) {
    					$principal[$key] = $value[0];
    				}
    			}
    			else {
    				$principal[$key] = $value;
    			}
    		}
    	}
    }
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getPrincipalByPath($path) principal: " . var_export($principal, true));
    return $principal;
  }
  /**
   * Updates one ore more webdav properties on a principal.
   * The list of mutations is stored in a Sabre\DAV\PropPatch object.
   * To do the actual updates, you must tell this object which properties
   * you're going to process with the handle() method.
   * Calling the handle method is like telling the PropPatch object "I
   * promise I can handle updating this property".
   * Read the PropPatch documenation for more info and examples.
   *
   * @param string $path
   * @param \Sabre\DAV\PropPatch $propPatch
   */
  public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.updatePrincipal($path)");
    return false;
  }
  /**
   * This method is used to search for principals matching a set of
   * properties.
   * This search is specifically used by RFC3744's principal-property-search
   * REPORT.
   * The actual search should be a unicode-non-case-sensitive search. The
   * keys in searchProperties are the WebDAV property names, while the values
   * are the property values to search on.
   * By default, if multiple properties are submitted to this method, the
   * various properties should be combined with 'AND'. If $test is set to
   * 'anyof', it should be combined using 'OR'.
   * This method should simply return an array with full principal uri's.
   * If somebody attempted to search on a property the backend does not
   * support, you should simply return 0 results.
   * You can also just return 0 results if you choose to not support
   * searching at all, but keep in mind that this may stop certain features
   * from working.
   *
   * @param string $prefixPath
   * @param array $searchProperties
   * @param string $test
   * @return array
   */
  public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.searchPrincipals($prefixPath, " . var_export($searchProperties, true) . ", $test)");

    if ($this->server->httpRequest->getMethod() == 'POST') {
      if (isset($searchProperties)) {
        if (isset($searchProperties['{http://sabredav.org/ns}email-address'])) {
          $email = $searchProperties['{http://sabredav.org/ns}email-address'];
          $filter = null;
          if (strpos($email, '.-.')) {
            $filter = "(&(objectClass=mineqMelObjetPartage)(mineqmelmailemission=$email))";
          }      
          $infos = \LibMelanie\Ldap\Ldap::GetUserInfosFromEmail($email, $filter);
        }
      }

      if (isset($infos)) {
        $user_uid = $infos['uid'][0];
        $principal = [$prefixPath . '/' . $user_uid];
        return $principal;
      }
    }
    return [];
  }
  /**
   * Returns the list of members for a group-principal
   *
   * @param string $principal
   * @return array
   */
  public function getGroupMemberSet($principal) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getGroupMemberSet($principal)");
    return [];
  }
  /**
   * Returns the list of groups a principal is a member of
   *
   * @param string $principal
   * @return array
   */
  public function getGroupMembership($principal) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getGroupMembership($principal)");
    $username = $this->calendarBackend->getCurrentUser();
    $result = [];
    
    // Get shared calendar
    $calendars = $this->calendarBackend->loadUserCalendars();
    $calendars_owners = [];
    foreach ($calendars as $calendar) {
      if (! in_array("principals/".$calendar->owner, $result)) {
        $result[] = "principals/".$calendar->owner;
      }
    }
    
    $infos = \LibMelanie\Ldap\Ldap::GetUserBalEmission($username);
    if (is_array($infos) && count($infos) > 0) {
      foreach ($infos as $info) {
        if (isset($info['uid'][0])) {
          $result[] = "principals/".$info['uid'][0];
        }
      }
    }
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.getGroupMembership($principal) result: " . var_export($result, 1));
    return $result;
  }
  /**
   * Updates the list of group members for a group principal.
   * The principals should be passed as a list of uri's.
   *
   * @param string $principal
   * @param array $members
   * @return void
   */
  public function setGroupMemberSet($principal, array $members) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[PrincipalBackend] LibM2.setGroupMemberSet($principal)");
  }
}
