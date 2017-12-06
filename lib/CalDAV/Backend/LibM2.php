<?php
/**
 * Fichier de gestion du backend CalDAV pour l'application SabreDAVM2
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
namespace Sabre\CalDAV\Backend;

use
    Sabre\CalDAV,
    Sabre\DAV,
    Sabre\DAV\Exception\Forbidden,
    Config\Config,
    LibMelanie\Config\ConfigMelanie;

/**
 * LibM2 CalDAV backend
 *
 * Utilisation de l'ORM Mélanie2 pour l'implémentation de ce backend
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBackend implements SchedulingSupport, Melanie2Support, SyncSupport {
  /**
   * Authenfication backend
   *
   * @var \Sabre\DAV\Auth\Backend\LibM2
   */
  protected $authBackend;
  /**
   * Liste des calendriers M2 de l'utilisateur
   *
   * @var \LibMelanie\Api\Melanie2\Calendar[]
   */
  protected $calendars;
  /**
   * Liste des listes de tâches M2 de l'utilisateur
   *
   * @var \LibMelanie\Api\Melanie2\Taskslist
   */
  protected $taskslist;
  /**
   * Est-ce que la liste des tâches M2 de l'utilisateur est chargée (donc existe)
   *
   * @var boolean
   */
  protected $taskslist_loaded;
  /**
   * Cache evenements courants, qui peuvent être utilises plusieurs fois
   *
   * @var \LibMelanie\Api\Melanie2\Event
   */
  protected $cache_events;
  /**
   * Cache tâches courantes, qui peuvent être utilisees plusieurs fois
   *
   * @var \LibMelanie\Api\Melanie2\Task
   */
  protected $cache_tasks;
  /**
   * Properties for the calendar
   * @var array
   */
  protected $calendars_prop;
  /**
   * UID de l'utilisateur connecté (pas forcément le propriétaire de l'agenda)
   * @var string
   */
  protected $current_user;
  /**
   * UID de la boite partagée
   * Utilisée dans le cas d'une connexion via un objet de partage
   * Sinon doit être à null
   * @var string
   */
  protected $current_balp;
  /**
   * UID de l'objet de partage
   * Utilisée dans le cas d'une connexion via un objet de partage
   * Sinon doit être à null
   * @var string
   */
  protected $current_share_object;
  /**
   * Utilisateur courant dans un objet User de l'ORM M2
   * @var \LibMelanie\Api\Melanie2\User
   */
  protected $user_melanie;
  /**
   * Instance du serveur SabreDAV
   * Permet d'accéder à la requête et à la réponse
   * @var \Sabre\DAV\Server
   */
  protected $server;
  /**
   * Format de datetime pour la base de données
   * @var string
   */
  const DB_DATE_FORMAT = 'Y-m-d H:i:s';
  /**
   * Format court de datetime pour la base de données
   * @var string
   */
  const SHORT_DB_DATE_FORMAT = 'Y-m-d';

  /**
   * Délimiteur pour le syncToken entre les événements et les tâches
   * @var string
   */
  const WEBSYNC_DELIMITER = '/';

  /**
   * List of CalDAV properties, and how they map to database fieldnames
   * Add your own properties by simply adding on to this array.
   *
   * Note that only string-based properties are supported here.
   *
   * @var array
   */
  public $propertyMap = [
    '{DAV:}displayname'                                    => 'displayname',
    '{urn:ietf:params:xml:ns:caldav}calendar-description'  => 'description',
    '{urn:ietf:params:xml:ns:caldav}calendar-timezone'     => 'timezone',
    '{http://apple.com/ns/ical/}calendar-order'            => 'calendarorder',
    '{http://apple.com/ns/ical/}calendar-color'            => 'calendarcolor',
  ];

  /**
   * List of subscription properties, and how they map to database fieldnames.
   *
   * @var array
   */
  public $subscriptionPropertyMap = [
    '{DAV:}displayname'                                           => 'displayname',
    '{http://apple.com/ns/ical/}refreshrate'                      => 'refreshrate',
    '{http://apple.com/ns/ical/}calendar-order'                   => 'calendarorder',
    '{http://apple.com/ns/ical/}calendar-color'                   => 'calendarcolor',
    '{http://calendarserver.org/ns/}subscribed-strip-todos'       => 'striptodos',
    '{http://calendarserver.org/ns/}subscribed-strip-alarms'      => 'stripalarms',
    '{http://calendarserver.org/ns/}subscribed-strip-attachments' => 'stripattachments',
  ];
  /**
   * Creates the backend
   *
   * @param \Sabre\DAV\Auth\Backend\LibM2 $authBackend
   */
  public function __construct(\Sabre\DAV\Auth\Backend\LibM2 $authBackend) {
    $this->authBackend = $authBackend;
    $this->calendars = [];
    $this->cache_events = [];
    $this->taskslist = null;
    $this->taskslist_loaded = false;
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.__construct() current_user : " . $this->current_user);
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
   * Définition du user courant
   */
  protected function setCurrentUser() {
    if (!isset($this->current_user)) {
      list($basename, $this->current_user) = \Sabre\Uri\split($this->server->getPlugin('auth')->getCurrentPrincipal());
      //$this->current_user = $this->authBackend->getCurrentUser();
      if (strpos($this->current_user, '.-.') !== false) {
        // Gestion des boites partagées
        $this->current_share_object = $this->current_user;
        // MANTIS 3791: Gestion de l'authentification via des boites partagées
        $tmp = explode('.-.', $this->current_user, 2);
        $this->current_user = $tmp[0];
        if (isset($tmp[1])) {
          $this->current_balp = $tmp[1];
        }
      }
      $this->user_melanie = new \LibMelanie\Api\Melanie2\User();
      $this->user_melanie->uid = $this->current_user;
    }
  }
  /**
   * Récupère l'utilisateur lié au principalURI
   */
  protected function getUserFromPrincipalUri($principalUri) {
    $var = explode('/', $principalUri);
    $username = $var[1];
    // Si c'est une boite partagée, on s'authentifie sur l'utilisateur pas sur la bal
    if (strpos($username, '.-.') !== false) {
      // MANTIS 3791: Gestion de l'authentification via des boites partagées
      $tmp = explode('.-.', $username, 2);
      $username = $tmp[0];
    }
    return $username;
  }
  /**
   * Charge la liste des calendriers de l'utilisateur connecté
   *
   * @param string $user Utilisateur pour les calendriers
   *
   * @return \LibMelanie\Api\Melanie2\Calendar[]
   */
  public function loadUserCalendars($user = null) {
  	if ($this->server->httpRequest->getMethod() == 'POST' && isset($user)) {
  		$usermelanie = new \LibMelanie\Api\Melanie2\User();
  		$usermelanie->uid = $user;
  		$calendar = new \LibMelanie\Api\Melanie2\Calendar($usermelanie);
  		$calendar->id = $user;
  		if ($calendar->load()) {
  			$this->calendars = [ $user => $calendar ];
  		}
  	}
  	else {
  		$this->setCurrentUser();

  		if (!isset($this->calendars)
  				|| count($this->calendars) === 0) {
  			$this->calendars = $this->user_melanie->getSharedCalendars();
  		}
  	}

    return $this->calendars;
  }
  /**
   * Charge la liste de tâches principale de l'utilisateur connecté
   *
   * @return \LibMelanie\Api\Melanie2\Taskslist
   */
  protected function loadUserTaskslist() {
  	$this->setCurrentUser();

  	if (!isset($this->taskslist)) {
  		$this->taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
  		$this->taskslist->id = $this->user_melanie->uid;
  		$this->taskslist_loaded = $this->taskslist->load();
  	}

  	return $this->taskslist;
  }
  /**
   * Returns a list of calendars for a principal.
   *
   * Every project is an array with the following keys:
   *  * id, a unique id that will be used by other functions to modify the
   *    calendar. This can be the same as the uri or a database key.
   *  * uri. This is just the 'base uri' or 'filename' of the calendar.
   *  * principaluri. The owner of the calendar. Almost always the same as
   *    principalUri passed to this method.
   *
   * Furthermore it can contain webdav properties in clark notation. A very
   * common one is '{DAV:}displayname'.
   *
   * Many clients also require:
   * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
   * For this property, you can just return an instance of
   * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
   *
   * If you return {http://sabredav.org/ns}read-only and set the value to 1,
   * ACL will automatically be put in read-only mode.
   *
   * @param string $principalUri
   * @return array
   */
  public function getCalendarsForUser($principalUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarsForUser($principalUri)");
    $this->setCurrentUser();
    $owner = $this->getUserFromPrincipalUri($principalUri);

    // Charge la liste des calendriers
    $this->loadUserCalendars($owner);

    if (!isset($this->calendars_prop) && $this->server->httpRequest->getMethod() != 'POST') {
      // Récupération des prefs supplémentaires
      $pref = new \LibMelanie\Api\Melanie2\UserPrefs($this->user_melanie);
      $pref->scope = \LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
      $pref->name = "caldav_properties";
      $this->calendars_prop = [];
      if ($pref->load()) {
        $this->calendars_prop = unserialize($pref->value);
      }
    }
    $calendars = [];
    foreach ($this->calendars as $_calendar) {
    	if ($_calendar->owner != $owner && $this->server->httpRequest->getMethod() != 'POST') {
        continue;
      }
      // Gestion du ctag
      $ctag = $this->getCalendarCTag($principalUri, $_calendar->id);
      // Gestion du SyncToken
      $syncToken = $_calendar->synctoken === 0 ? "s" : $_calendar->synctoken;
      // Utilisation seul des VEVENTS pour l'instant
      if ($_calendar->id == $_calendar->owner
          && $this->user_melanie->uid == $_calendar->owner) {
        // Calendrier principal de l'utilisateur
        $components = ['VEVENT', 'VTODO', 'VTIMEZONE', 'VFREEBUSY'];
        $transp = 'opaque';
        // Gestion du ctag pour les tâches
        $this->loadUserTaskslist();
        if ($this->taskslist_loaded) {
        	$ctag = md5($ctag . $this->taskslist->ctag);
        	$syncToken = $syncToken . self::WEBSYNC_DELIMITER . $this->taskslist->synctoken;
        }
        else {
        	$ctag = md5($ctag . md5($_calendar->id));
        }
      }
      else if ($_calendar->id == $_calendar->owner && $this->server->httpRequest->getMethod() == 'POST') {
      	// Calendrier principal pour les freebusy
      	$components = ['VFREEBUSY'];
      	$transp = 'opaque';
      }
      else {
        // Calendrier secondaire ou partagé (pas de freebusy et de taches
      	$components = ['VEVENT', 'VTIMEZONE'];
        $transp = 'transparent';
      }
      $calendar = [
          'id' => $_calendar->id,
          'uri' => $_calendar->id,
          'principaluri' => $principalUri,
          '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => '"'.$ctag.'"',
          '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
          '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new CalDAV\Xml\Property\ScheduleCalendarTransp($transp),
          '{DAV:}displayname' => $_calendar->name,
      ];
      if (\Config\Config::enableWebDavSync) {
      	$calendar['{http://sabredav.org/ns}sync-token'] = $syncToken;
      }
      else {
      	$calendar['{http://sabredav.org/ns}sync-token'] = '"'.$ctag.'"';
      }
      if (isset($this->calendars_prop[$_calendar->id])) {
        $calendar = array_merge($calendar, $this->calendars_prop[$_calendar->id]);
      }
      $calendars[] = $calendar;
    }
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarsForUser($principalUri) : " . var_export($calendars, true));
    return $calendars;
  }
  /**
   * Returns a calendar for a principal.
   *
   * Every project is an array with the following keys:
   *  * id, a unique id that will be used by other functions to modify the
   *    calendar. This can be the same as the uri or a database key.
   *  * uri. This is just the 'base uri' or 'filename' of the calendar.
   *  * principaluri. The owner of the calendar. Almost always the same as
   *    principalUri passed to this method.
   *
   * Furthermore it can contain webdav properties in clark notation. A very
   * common one is '{DAV:}displayname'.
   *
   * Many clients also require:
   * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
   * For this property, you can just return an instance of
   * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
   *
   * If you return {http://sabredav.org/ns}read-only and set the value to 1,
   * ACL will automatically be put in read-only mode.
   *
   * @param string $principalUri
   * @param string $calendarId
   * @return array
   */
  public function getCalendarForPrincipal($principalUri, $calendarId) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarForPrincipal($principalUri, $calendarId)");
    $this->setCurrentUser();

    if (!isset($this->calendars[$calendarId])) {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if (!$this->calendars[$calendarId]->load()) {
        unset($this->calendars[$calendarId]);
      }
    }

    if (isset($this->calendars[$calendarId])) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)
    			&& !$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
    	// Gestion du ctag
    	$ctag = $this->getCalendarCTag($principalUri, $calendarId);
    	// Gestion du syncToken
    	$syncToken = $this->calendars[$calendarId]->synctoken === 0 ? "s" : $this->calendars[$calendarId]->synctoken;
      if ($this->calendars[$calendarId]->id == $this->calendars[$calendarId]->owner
          && $this->user_melanie->uid == $this->calendars[$calendarId]->owner) {
        // Calendrier principal de l'utilisateur
        $components = ['VEVENT', 'VTODO', 'VTIMEZONE', 'VFREEBUSY'];
        $transp = 'opaque';
        // Gestion du ctag pour les tâches
        $this->loadUserTaskslist();
        if ($this->taskslist_loaded) {
        	$ctag = md5($ctag . $this->taskslist->ctag);
        	$syncToken = $syncToken . self::WEBSYNC_DELIMITER . $this->taskslist->synctoken;
        }
        else {
        	$ctag = md5($ctag . md5($calendarId));
        }
      }
      else {
        // Calendrier secondaire ou partagé (pas de freebusy et de taches
        $components = ['VEVENT', 'VTIMEZONE'];
        $transp = 'transparent';
      }
      $result = [
        'id' => $this->calendars[$calendarId]->id,
        'uri' => $this->calendars[$calendarId]->id,
        'principaluri' => $principalUri,
        '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => '"'.$ctag.'"',
        '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
        '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' =>  new CalDAV\Xml\Property\ScheduleCalendarTransp($transp),
        '{DAV:}displayname' => $this->calendars[$calendarId]->name,
      ];
      if (\Config\Config::enableWebDavSync) {
      	$result['{http://sabredav.org/ns}sync-token'] = $syncToken;
      }
      else {
      	$result['{http://sabredav.org/ns}sync-token'] = '"'.$ctag.'"';
      }
      return $result;
    }
    return null;
  }
  /**
   * Return a calendar ctag for a principal and a calendar id
   *
   * Return the ctag string associate to the calendar id
   * Getting a ctag do not need an authenticate
   *
   * @param string $principalUri
   * @param string $calendarId
   * @return string
   */
  public function getCalendarCTag($principalUri, $calendarId) {
    // Current User
    $this->setCurrentUser();
    if (!isset($this->calendars[$calendarId])) {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if (!$this->calendars[$calendarId]->load()) {
        unset($this->calendars[$calendarId]);
      }
    }
    $ctag = null;
    if (isset($this->calendars[$calendarId])) {
      $ctag = $this->calendars[$calendarId]->ctag;
      if (empty($ctag)) {
      	$ctag = md5($calendarId);
      }
    }
    return $ctag;
  }
  /**
   * Creates a new calendar for a principal.
   *
   * If the creation was a success, an id must be returned that can be used
   * to reference this calendar in other methods, such as updateCalendar.
   *
   * @param string $principalUri
   * @param string $calendarUri
   * @param array $properties
   * @return string
   */
  public function createCalendar($principalUri, $calendarUri, array $properties) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.createCalendar($principalUri, $calendarUri)");
    return;
  }
  /**
   * Updates properties for a calendar.
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
   * @param string $calendarId
   * @param \Sabre\DAV\PropPatch $propPatch
   * @return void
   */
  public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.updateCalendar($calendarId, ".var_export($propPatch, true).")");
    // User courant
    $this->setCurrentUser();
    $supportedProperties = array_keys($this->propertyMap);

    $propPatch->handle($supportedProperties, function($mutations) use ($calendarId) {
      // Récupération des prefs supplémentaires
      $pref = new \LibMelanie\Api\Melanie2\UserPrefs($this->user_melanie);
      $pref->scope = \LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
      $pref->name = "caldav_properties";
      $calendars_prop = [];
      if ($pref->load()) {
        $calendars_prop = unserialize($pref->value);
      }

      if (isset($calendars_prop[$calendarId])) {
        $calendars_prop[$calendarId] = array_merge($calendars_prop[$calendarId], $mutations);
      }
      else {
        $calendars_prop[$calendarId] = $mutations;
      }

      $pref->value = serialize($calendars_prop);
      $pref->save();
      return true;
    });
  }
  /**
   * Delete a calendar and all it's objects
   *
   * @param string $calendarId
   * @return void
   */
  public function deleteCalendar($calendarId) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.deleteCalendar($calendarId)");
  }
  /**
   * Returns all calendar objects within a calendar.
   *
   * Every item contains an array with the following keys:
   *   * calendardata - The iCalendar-compatible calendar data
   *   * uri - a unique key which will be used to construct the uri. This can
   *     be any arbitrary string, but making sure it ends with '.ics' is a
   *     good idea. This is only the basename, or filename, not the full
   *     path.
   *   * lastmodified - a timestamp of the last modification time
   *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
   *   '  "abcdef"')
   *   * size - The size of the calendar objects, in bytes.
   *   * component - optional, a string containing the type of object, such
   *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
   *     the Content-Type header.
   *
   * Note that the etag is optional, but it's highly encouraged to return for
   * speed reasons.
   *
   * The calendardata is also optional. If it's not returned
   * 'getCalendarObject' will be called later, which *is* expected to return
   * calendardata.
   *
   * If neither etag or size are specified, the calendardata will be
   * used/fetched to determine these numbers. If both are specified the
   * amount of times this is needed is reduced by a great degree.
   *
   * @param string $calendarId
   * @return array
   */
  public function getCalendarObjects($calendarId) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObjects($calendarId)");
    // User courant
    $this->setCurrentUser();
    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
      }
    }
    $result = [];
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)
    			&& !$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      $start = new \DateTime();
      $start->modify(Config::DATE_MAX);
      $this->cache_events = $this->calendars[$calendarId]->getRangeEvents($start->format(self::DB_DATE_FORMAT));
      //$this->cache_events = $this->calendars[$calendarId]->getAllEvents();
      foreach($this->cache_events as $_event) {
        $event = [
            'id'           => $_event->uid,
            'uri'          => $_event->uid.'.ics',
            'lastmodified' => $_event->modified,
            'etag'         => '"' . md5($_event->modified) . '"',
            'calendarid'   => $_event->calendar,
            'component'    => 'vevent',
        ];
        if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
        	// MANTIS 0004477: Gérer le droit afficher
        	$_event->ics_freebusy = true;
        }
        if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
          $event['calendardata'] = $_event->ics;
          $event['size'] = strlen($event['calendardata']);
        }
        $result[] = $event;
      }
      // Test si on est dans un calendrier principal, auquel cas on doit charger les tâches
      if ($this->calendars[$calendarId]->id == $this->calendars[$calendarId]->owner
          && $this->user_melanie->uid == $this->calendars[$calendarId]->owner) {
        $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
        $taskslist->id = $this->calendars[$calendarId]->id;
        $this->cache_tasks = $taskslist->getAllTasks();
        foreach($this->cache_tasks as $_task) {
          $task = [
            'id'           => $_task->uid,
            'uri'          => $_task->uid.'.ics',
            'lastmodified' => $_task->modified,
            'etag'         => '"' . md5($_task->modified) . '"',
            'calendarid'   => $_task->taskslist,
            'component'    => 'vtodo',
          ];
          if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
            $task['calendardata'] = $_task->ics;
            $task['size'] = strlen($task['calendardata']);
          }
          $result[] = $task;
        }
      }
    }
    //if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObjects($calendarId) : " . var_export($result, true));
    return $result;
  }
  /**
   * Returns information from a single calendar object, based on it's object
   * uri.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * The returned array must have the same keys as getCalendarObjects. The
   * 'calendardata' object is required here though, while it's not required
   * for getCalendarObjects.
   *
   * This method must return null if the object did not exist.
   *
   * @param string $calendarId
   * @param string $objectUri
   * @return array|null
   */
  public function getCalendarObject($calendarId, $objectUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObject($calendarId,$objectUri)");
    // User courant
    $this->setCurrentUser();
    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
        $loaded = false;
      }
    }
    $result = null;
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)
    			&& !$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      $event_uid = urldecode(str_replace('.ics', '', $objectUri));
      // Cherche si l'évènement n'est pas déjà dans le cache
      if (!isset($this->cache_events[$event_uid.$calendarId])) {
        $event = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
        $event->uid = $event_uid;
        $this->cache_events[$event_uid.$calendarId] = $event;
        if (!$event->load()
            && $calendarId == $this->user_melanie->uid
            && $this->calendars[$calendarId]->owner == $calendarId) {
          // Cas du calendrier principal, on gère les tâches
          if (!isset($this->cache_tasks[$event_uid.$calendarId])) {
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
            $taskslist->id = $calendarId;
            $task = new \LibMelanie\Api\Melanie2\Task($this->user_melanie, $taskslist);
            $task->uid = $event_uid;
            $this->cache_tasks[$event_uid.$calendarId] = $task;
            $task->load();
          }
        }
      }
      // Si l'évènement existe on retourne le resultat
      if (isset($this->cache_events[$event_uid.$calendarId])
          && $this->cache_events[$event_uid.$calendarId]->exists()) {
        $result = [
          'id'            => $this->cache_events[$event_uid.$calendarId]->uid,
          'uri'           => $this->cache_events[$event_uid.$calendarId]->uid.'.ics',
          'lastmodified'  => $this->cache_events[$event_uid.$calendarId]->modified,
          'etag'          => '"' . md5($this->cache_events[$event_uid.$calendarId]->modified) . '"',
          'calendarid'    => $this->cache_events[$event_uid.$calendarId]->calendar,
          'component'     => 'vevent',
        ];
        if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ) || $this->server->httpRequest->getMethod() == 'POST') {
        	// MANTIS 0004477: Gérer le droit afficher
        	$this->cache_events[$event_uid.$calendarId]->ics_freebusy = true;
        }
        if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
          $result['calendardata'] = $this->cache_events[$event_uid.$calendarId]->ics;
          $result['size'] = strlen($result['calendardata']);
        }
      }
      elseif (isset($this->cache_tasks[$event_uid.$calendarId])
          && $this->cache_tasks[$event_uid.$calendarId]->exists()) {
        $result = [
          'id'            => $this->cache_tasks[$event_uid.$calendarId]->uid,
          'uri'           => $this->cache_tasks[$event_uid.$calendarId]->uid.'.ics',
          'lastmodified'  => $this->cache_tasks[$event_uid.$calendarId]->modified,
          'etag'          => '"' . md5($this->cache_tasks[$event_uid.$calendarId]->modified) . '"',
          'calendarid'    => $this->cache_tasks[$event_uid.$calendarId]->taskslist,
          'component'     => 'vtodo',
        ];
        if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
          $result['calendardata'] = $this->cache_tasks[$event_uid.$calendarId]->ics;
          $result['size'] = strlen($result['calendardata']);
        }
      }
    }
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObject($calendarId,$objectUri) : " . var_export($result, true));
    return $result;
  }
  /**
   * Returns a list of calendar objects.
   *
   * This method should work identical to getCalendarObject, but instead
   * return all the calendar objects in the list as an array.
   *
   * If the backend supports this, it may allow for some speed-ups.
   *
   * @param mixed $calendarId
   * @param array $uris
   * @return array
   */
  public function getMultipleCalendarObjects($calendarId, array $uris) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getMultipleCalendarObjects($calendarId, ".var_export($uris, true).")");
    // User courant
    $this->setCurrentUser();
    $list_event_uid = [];
    // Remove .ics from the uri
    foreach ($uris as $uri) {
        $list_event_uid[] = urldecode(str_replace('.ics', '', $uri));
    }

    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
      }
    }
    $result = [];
    if ($loaded) {
        if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)
        		&& !$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
	        // MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
	        throw new \Sabre\DAV\Exception\Forbidden();
        }
        $_events = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
        $_events->uid = $list_event_uid;
        $this->cache_events = $_events->getList();
        foreach($this->cache_events as $_event) {
	        	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
	        		// MANTIS 0004477: Gérer le droit afficher
	        		$_event->ics_freebusy = true;
	        	}
            $event = [
              'id'           => $_event->uid,
              'uri'          => $_event->uid.'.ics',
              'lastmodified' => $_event->modified,
              'etag'         => '"' . md5($_event->modified) . '"',
              'calendarid'   => $_event->calendar,
              'component'    => 'vevent',
            ];
            if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
            	$event['calendardata'] = $_event->ics;
            	$event['size'] = strlen($event['calendardata']);
            	// MANTIS 0004631: Nettoyer les données des pièces jointes après leur lecture
              foreach ($_event->attachments as $attachment) {
                unset($attachment->data);
              }
            }
            $result[] = $event;
            // Vide la liste des uid
            $index = array_search($_event->uid, $list_event_uid);
            if ($index !== false) {
              unset($list_event_uid[$index]);
            }
        }
        // Si c'est un calendrier principal, on cherche les tâches
        if ($calendarId == $this->user_melanie->uid
            && $this->calendars[$calendarId]->owner == $calendarId
            && count($list_event_uid) > 0) {
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
          $taskslist->id = $calendarId;
          $_tasks = new \LibMelanie\Api\Melanie2\Task($this->user_melanie, $taskslist);
          $_tasks->uid = $list_event_uid;
          $this->cache_tasks = $_tasks->getList();
          $itemsFound = 0;
          foreach($this->cache_tasks as $_task) {
            $task = [
              'id'           => $_task->uid,
              'uri'          => $_task->uid.'.ics',
              'lastmodified' => $_task->modified,
              'etag'         => '"' . md5($_task->modified) . '"',
              'calendarid'   => $_task->taskslist,
              'component'    => 'vtodo',
            ];
            if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
            	$task['calendardata'] = $_task->ics;
            	$task['size'] = strlen($task['calendardata']);
            }
            $result[] = $task;
            // Vide la liste des uid
            $index = array_search($_task->uid, $list_event_uid);
            if ($index !== false) {
              unset($list_event_uid[$index]);
            }
          }
        }
        // Si on ne trouve pas les évènements recherchés, il s'agit certainement de FAKED MASTER
        if (count($list_event_uid) > 0) {
          // Remove .ics from the uri
          foreach ($list_event_uid as $uid) {
            $list_event_uid[] = $uid.'%@RECURRENCE-ID';
          }
          $_events = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
          $_events->uid = $list_event_uid;
          $_events->calendar = $this->calendars[$calendarId]->id;
          $operators = [
            'uid' => \LibMelanie\Config\MappingMelanie::like,
            'calendar' => \LibMelanie\Config\MappingMelanie::eq ];
          foreach ($_events->getList(null, null, $operators, 'start') as $_event) {
            $event = [
              'id'           => $_event->uid,
              'uri'          => $_event->uid.'.ics',
              'lastmodified' => $_event->modified,
              'etag'         => '"' . md5($_event->modified) . '"',
              'calendarid'   => $_event->calendar,
              'component'    => 'vevent',
            ];
            if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
            	$event['calendardata'] = $_event->ics;
            	$event['size'] = strlen($event['calendardata']);
            }
            $result[] = $event;
          }
        }
    }
    //if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getMultipleCalendarObjects($calendarId) : " . var_export($result, true));
    return $result;
  }
  /**
   * Creates a new calendar object.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * It is possible return an etag from this function, which will be used in
   * the response to this PUT request. Note that the ETag must be surrounded
   * by double-quotes.
   *
   * However, you should only really return this ETag if you don't mangle the
   * calendar-data. If the result of a subsequent GET to this object is not
   * the exact same as this request body, you should omit the ETag.
   *
   * @param mixed $calendarId
   * @param string $objectUri
   * @param string $calendarData
   * @return string|null
   */
  public function createCalendarObject($calendarId,$objectUri,$calendarData) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.createCalendarObject($calendarId,$objectUri)");
    // User courant
    $this->setCurrentUser();
    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
        $loaded = false;
      }
    }
    $result = null;
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      // Création d'une tâche ou d'un évènement ?
      $is_task = false;
      if ($calendarId == $this->user_melanie->uid
          && $this->calendars[$calendarId]->owner == $calendarId) {
        if (strpos($calendarData, 'BEGIN:VTODO') !== false) {
          $is_task = true;
        }
      }
      $event_uid = str_replace('.ics', '', $objectUri);
      if ($is_task) {
        // Cherche si la tâche n'est pas déjà dans le cache
        if (isset($this->cache_tasks[$event_uid.$calendarId])
            && is_object($this->cache_tasks[$event_uid.$calendarId])) {
          $task = $this->cache_tasks[$event_uid.$calendarId];
        }
        else {
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
          $taskslist->id = $calendarId;
          $task = new \LibMelanie\Api\Melanie2\Task($this->user_melanie, $taskslist);
          $task->uid = $event_uid;
        }
        if (isset($this->current_balp)) {
          $task->owner = $this->current_share_object;
        } else {
          $task->owner = $this->current_user;
        }
        $task->ics = $calendarData;
        $task->modified = time();
        $task->id = md5(time().$task->uid.uniqid());
        $res = $task->save();
        if (!is_null($res)) {
          $this->cache_tasks[$event_uid.$calendarId] = $task;
          $result = '"'.md5($task->modified).'"';
        }
      }
      else {
        // Cherche si l'évènement n'est pas déjà dans le cache
        if (isset($this->cache_events[$event_uid.$calendarId])
            && is_object($this->cache_events[$event_uid.$calendarId])) {
          $event = $this->cache_events[$event_uid.$calendarId];
        }
        else {
          $event = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
          $event->uid = $event_uid;
        }
        if (isset($this->current_balp)) {
          $event->owner = $this->current_share_object;
        } else {
          $event->owner = $this->current_user;
        }
        $event->ics = $calendarData;
        // MANTIS 0004663: Problème de création d'une exception d'une récurrence non présente
        if (!isset($event->end) && count($event->exceptions) > 0) {
          $event->deleted = true;
        }
        $event->modified = time();
        $res = $event->save();
        if (!is_null($res)) {
          $this->cache_events[$event_uid.$calendarId] = $event;
          $result = '"'.md5($event->modified).'"';
        }
      }
    }
    return $result;
  }
  /**
   * Updates an existing calendarobject, based on it's uri.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * It is possible return an etag from this function, which will be used in
   * the response to this PUT request. Note that the ETag must be surrounded
   * by double-quotes.
   *
   * However, you should only really return this ETag if you don't mangle the
   * calendar-data. If the result of a subsequent GET to this object is not
   * the exact same as this request body, you should omit the ETag.
   *
   * @param mixed $calendarId
   * @param string $objectUri
   * @param string $calendarData
   * @return string|null
   */
  public function updateCalendarObject($calendarId,$objectUri,$calendarData) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.updateCalendarObject($calendarId,$objectUri)");
    // User courant
    $this->setCurrentUser();
    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
      }
    }
    $result = null;
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      // Création d'une tâche ou d'un évènement ?
      $is_task = false;
      if ($calendarId == $this->user_melanie->uid
          && $this->calendars[$calendarId]->owner == $calendarId) {
        if (strpos($calendarData, 'BEGIN:VTODO') !== false) {
          $is_task = true;
        }
      }
      $event_uid = str_replace('.ics', '', $objectUri);
      if ($is_task) {
        // Cherche si la tâche n'est pas déjà dans le cache
        if (isset($this->cache_tasks[$event_uid.$calendarId])
            && is_object($this->cache_tasks[$event_uid.$calendarId])) {
          $task = $this->cache_tasks[$event_uid.$calendarId];
        }
        else {
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
          $taskslist->id = $calendarId;
          $task = new \LibMelanie\Api\Melanie2\Task($this->user_melanie, $taskslist);
          $task->uid = $event_uid;
        }
        $task->ics = $calendarData;
        $task->modified = time();
        $res = $task->save();
        if (!is_null($res)) {
          $this->cache_tasks[$event_uid.$calendarId] = $task;
          $result = '"'.md5($task->modified).'"';
        }
      }
      else {
        // Cherche si l'évènement n'est pas déjà dans le cache
        if (isset($this->cache_events[$event_uid.$calendarId])
            && is_object($this->cache_events[$event_uid.$calendarId])) {
          $event = $this->cache_events[$event_uid.$calendarId];
        }
        else {
          $event = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
          $event->uid = $event_uid;
        }
        if ($event->load()) {
          if (!isset($event->owner)) {
            $event->owner = $this->user_melanie->uid;
          }
          $event->ics = $calendarData;
          $event->modified = time();
          $res = $event->save();
          if (!is_null($res)) {
            $this->cache_events[$event_uid.$calendarId] = $event;
            $result = '"'.md5($event->modified).'"';
          }
        }
      }
    }
    return $result;
  }
  /**
   * Deletes an existing calendar object.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * @param string $calendarId
   * @param string $objectUri
   * @return void
   */
  public function deleteCalendarObject($calendarId,$objectUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.deleteCalendarObject($calendarId,$objectUri)");
    if (!isset($objectUri)) {
    	return;
    }
    // User courant
    $this->setCurrentUser();
    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
      }
    }
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      $event = new \LibMelanie\Api\Melanie2\Event($this->user_melanie, $this->calendars[$calendarId]);
      $event->uid = str_replace('.ics', '', $objectUri);
      $res = false;
      if ($event->load()) {
        $exceptions = $event->exceptions;
        // Suppression de l'évènement
        $res = $event->delete();
        if (count($exceptions) > 0) {
          foreach($exceptions as $exception) {
            // Suppression des exceptions de l'évènement
            $exception->delete();
          }
        }
      }
      elseif ($calendarId == $this->user_melanie->uid
          && $this->calendars[$calendarId]->owner == $calendarId) {
        // Si on est dans le calendrier principal, on cherche peut être une tâche
        $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
        $taskslist->id = $calendarId;
        $task = new \LibMelanie\Api\Melanie2\Task($this->user_melanie, $taskslist);
        $task->uid = str_replace('.ics', '', $objectUri);
        $res = false;
        if ($task->load()) {
          // Suppression de la tâche
          $res = $task->delete();
        }
      }
      if (!$res) {
        throw new Exception();
      }
    }
  }
  /**
   * Performs a calendar-query on the contents of this calendar.
   *
   * The calendar-query is defined in RFC4791 : CalDAV. Using the
   * calendar-query it is possible for a client to request a specific set of
   * object, based on contents of iCalendar properties, date-ranges and
   * iCalendar component types (VTODO, VEVENT).
   *
   * This method should just return a list of (relative) urls that match this
   * query.
   *
   * The list of filters are specified as an array. The exact array is
   * documented by \Sabre\CalDAV\CalendarQueryParser.
   *
   * Note that it is extremely likely that getCalendarObject for every path
   * returned from this method will be called almost immediately after. You
   * may want to anticipate this to speed up these requests.
   *
   * This method provides a default implementation, which parses *all* the
   * iCalendar objects in the specified calendar.
   *
   * This default may well be good enough for personal use, and calendars
   * that aren't very large. But if you anticipate high usage, big calendars
   * or high loads, you are strongly adviced to optimize certain paths.
   *
   * The best way to do so is override this method and to optimize
   * specifically for 'common filters'.
   *
   * Requests that are extremely common are:
   *   * requests for just VEVENTS
   *   * requests for just VTODO
   *   * requests with a time-range-filter on a VEVENT.
   *
   * ..and combinations of these requests. It may not be worth it to try to
   * handle every possible situation and just rely on the (relatively
   * easy to use) CalendarQueryValidator to handle the rest.
   *
   * Note that especially time-range-filters may be difficult to parse. A
   * time-range filter specified on a VEVENT must for instance also handle
   * recurrence rules correctly.
   * A good example of how to interprete all these filters can also simply
   * be found in \Sabre\CalDAV\CalendarQueryFilter. This class is as correct
   * as possible, so it gives you a good idea on what type of stuff you need
   * to think of.
   *
   * This specific implementation (for the PDO) backend optimizes filters on
   * specific components, and VEVENT time-ranges.
   *
   * @param string $calendarId
   * @param array $filters
   * @return array
   */
  public function calendarQuery($calendarId, array $filters) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.calendarQuery($calendarId, ".var_export($filters, true).")");
    $componentType = null;
    $requirePostFilter = true;
    $timeRange = null;
    // User courant
    $this->setCurrentUser();
    // if no filters were specified, we don't need to filter after a query
    if (!$filters['prop-filters'] && !$filters['comp-filters']) {
        $requirePostFilter = false;
    }

    // Figuring out if there's a component filter
    if (count($filters['comp-filters']) > 0 && !$filters['comp-filters'][0]['is-not-defined']) {
        $componentType = $filters['comp-filters'][0]['name'];

        // Checking if we need post-filters
        if (!$filters['prop-filters'] && !$filters['comp-filters'][0]['comp-filters'] && !$filters['comp-filters'][0]['time-range'] && !$filters['comp-filters'][0]['prop-filters']) {
            $requirePostFilter = false;
        }
        // There was a time-range filter
        if ($componentType == 'VEVENT' && isset($filters['comp-filters'][0]['time-range'])) {
            $timeRange = $filters['comp-filters'][0]['time-range'];

            // If start time OR the end time is not specified, we can do a
            // 100% accurate mysql query.
            if (!$filters['prop-filters'] && !$filters['comp-filters'][0]['comp-filters'] && !$filters['comp-filters'][0]['prop-filters'] && (!$timeRange['start'] || !$timeRange['end'])) {
                $requirePostFilter = false;
            }
        }
    }

    // Cherche si le calendrier est présent en mémoire
    if (isset($this->calendars)
        && isset($this->calendars[$calendarId])) {
      $loaded = true;
    }
    else {
      $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
      $this->calendars[$calendarId]->id = $calendarId;
      if ($this->calendars[$calendarId]->load()) {
        $loaded = true;
      }
      else {
        unset($this->calendars[$calendarId]);
      }
    }
    $result = [];
    if ($loaded) {
    	if (!$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)
    			&& !$this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
    		// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
    		throw new \Sabre\DAV\Exception\Forbidden();
    	}
      if ($componentType == 'VTODO') {
        if ($calendarId == $this->user_melanie->uid
            && $this->calendars[$calendarId]->owner == $calendarId) {
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($this->user_melanie);
          $taskslist->id = $this->calendars[$calendarId]->id;
          $this->cache_tasks = $taskslist->getAllTasks();

          foreach($this->cache_tasks as $task) {
            $result[] = $task->uid . '.ics';
          }
        }
      }
      else {
        if (isset($timeRange)) {
          $start = null;
          if (isset($timeRange['start'])) {
            $start = $timeRange['start']->format(self::DB_DATE_FORMAT);
          }
          $end = null;
          if (isset($timeRange['end'])) {
            $end = $timeRange['end']->format(self::DB_DATE_FORMAT);
          }
          $this->cache_events = $this->calendars[$calendarId]->getRangeEvents($start, $end);
        }
        else {
          $start = new \DateTime();
          $start->modify(Config::DATE_MAX);
          $this->cache_events = $this->calendars[$calendarId]->getRangeEvents($start->format(self::DB_DATE_FORMAT));
        }

        foreach($this->cache_events as $event) {
          $result[] = $event->uid . '.ics';
        }
      }
    }

    return $result;
  }
  /**
   * Searches through all of a users calendars and calendar objects to find
   * an object with a specific UID.
   *
   * This method should return the path to this object, relative to the
   * calendar home, so this path usually only contains two parts:
   *
   * calendarpath/objectpath.ics
   *
   * If the uid is not found, return null.
   *
   * This method should only consider * objects that the principal owns, so
   * any calendars owned by other principals that also appear in this
   * collection should be ignored.
   *
   * @param string $principalUri
   * @param string $uid
   * @return string|null
   */
  public function getCalendarObjectByUID($principalUri, $uid) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObjectByUID($principalUri, $uid)");
    // User courant
    $this->setCurrentUser();
    $list_event_uid = [];
    // Remove .ics from the uri
    foreach ($uris as $uri) {
      $list_event_uid[] = str_replace('.ics', '', $uri);
    }
    // Récupère la liste des agendas de l'utilisateur
    $this->loadUserCalendars($this->user_melanie);
    $calendar_uids = [];
    foreach ($this->calendars as $calendar) {
      $calendar_uids[] = $calendar->id;
    }

    if (count($calendar_uids) > 0) {
      $_events = new \LibMelanie\Api\Melanie2\Event($this->user_melanie);
      $_events->uid = $list_event_uid;
      $_events->calendar = $calendar_uids;
      foreach($_events->getList() as $_event) {
        return $_event->calendar.'/'.$_event->uid.'.ics';
      }
    }
    return null;
  }

  /**
   * The getChanges method returns all the changes that have happened, since
   * the specified syncToken in the specified calendar.
   *
   * This function should return an array, such as the following:
   *
   * [
   *   'syncToken' => 'The current synctoken',
   *   'added'   => [
   *      'new.txt',
   *   ],
   *   'modified'   => [
   *      'modified.txt',
   *   ],
   *   'deleted' => [
   *      'foo.php.bak',
   *      'old.txt'
   *   ]
   * );
   *
   * The returned syncToken property should reflect the *current* syncToken
   * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
   * property This is * needed here too, to ensure the operation is atomic.
   *
   * If the $syncToken argument is specified as null, this is an initial
   * sync, and all members should be reported.
   *
   * The modified property is an array of nodenames that have changed since
   * the last token.
   *
   * The deleted property is an array with nodenames, that have been deleted
   * from collection.
   *
   * The $syncLevel argument is basically the 'depth' of the report. If it's
   * 1, you only have to report changes that happened only directly in
   * immediate descendants. If it's 2, it should also include changes from
   * the nodes below the child collections. (grandchildren)
   *
   * The $limit argument allows a client to specify how many results should
   * be returned at most. If the limit is not specified, it should be treated
   * as infinite.
   *
   * If the limit (infinite or not) is higher than you're willing to return,
   * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
   *
   * If the syncToken is expired (due to data cleanup) or unknown, you must
   * return null.
   *
   * The limit is 'suggestive'. You are free to ignore it.
   *
   * @param string $calendarId
   * @param string $syncToken
   * @param int $syncLevel
   * @param int $limit
   * @return array
   */
  function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {
  	if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit)");
  	// User courant
  	$this->setCurrentUser();
  	// Cherche si le calendrier est présent en mémoire
  	if (isset($this->calendars)
  			&& isset($this->calendars[$calendarId])) {
  		$loaded = true;
  	}
  	else {
  		$this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($this->user_melanie);
  		$this->calendars[$calendarId]->id = $calendarId;
  		if ($this->calendars[$calendarId]->load()) {
  			$loaded = true;
  		}
  		else {
  			unset($this->calendars[$calendarId]);
  		}
  	}
  	// Test si le syncToken concerne également les tâches
  	if (isset($syncToken) && strpos($syncToken, self::WEBSYNC_DELIMITER) !== false) {
  		$tokens = explode(self::WEBSYNC_DELIMITER, $syncToken, 2);
  		$syncToken = $tokens[0];
  		$syncTokenTasks = $tokens[1];
  	}
  	else if ($syncToken == "s") {
  		$syncToken = 0;
  	}
  	$results = [
  			'syncToken' => $this->calendars[$calendarId]->synctoken,
  	];
  	if ($loaded) {
  		if (isset($syncToken) && intval($this->calendars[$calendarId]->synctoken) === intval($syncToken)) {
  			$results = array_merge($results, [
  					'added' 		=> [],
  					'modified' 	=> [],
  					'deleted' 	=> [],
  			]);
  		}
  		else {
  			$syncs = new \LibMelanie\Api\Melanie2\CalendarSync($this->calendars[$calendarId]);
  			$syncs->token = $syncToken;
  			$start = new \DateTime();
  			$start->modify(Config::DATE_MAX);
  			$results = array_merge($results, $syncs->listCalendarSync($limit, $start->format(self::DB_DATE_FORMAT)));
  		}
  		// Gestion des tâches
  		if ($this->calendars[$calendarId]->id == $this->calendars[$calendarId]->owner && $this->calendars[$calendarId]->id == $this->user_melanie->uid) {
  			$this->loadUserTaskslist();
  			if ($this->taskslist_loaded) {
  				if (!isset($syncTokenTasks) || intval($this->taskslist->synctoken) !== intval($syncTokenTasks)) {
  					$syncsTasks = new \LibMelanie\Api\Melanie2\TaskslistSync($this->taskslist);
  					$syncsTasks->token = isset($syncTokenTasks) ? $syncTokenTasks : null;
  					$resultsTasks = $syncsTasks->listTaskslistSync($limit);
  					$results['added'] = array_merge($results['added'], $resultsTasks['added']);
  					$results['modified'] = array_merge($results['modified'], $resultsTasks['modified']);
  					$results['deleted'] = array_merge($results['deleted'], $resultsTasks['deleted']);
  				}
  				$results['syncToken'] = $results['syncToken'] . self::WEBSYNC_DELIMITER . $this->taskslist->synctoken;
  			}
  		}
  	}
  	if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getChangesForCalendar() results: " . var_export($results, true));
  	return $results;
  }

  /**
   * Returns a list of subscriptions for a principal.
   *
   * Every subscription is an array with the following keys:
   *  * id, a unique id that will be used by other functions to modify the
   *    subscription. This can be the same as the uri or a database key.
   *  * uri. This is just the 'base uri' or 'filename' of the subscription.
   *  * principaluri. The owner of the subscription. Almost always the same as
   *    principalUri passed to this method.
   *  * source. Url to the actual feed
   *
   * Furthermore, all the subscription info must be returned too:
   *
   * 1. {DAV:}displayname
   * 2. {http://apple.com/ns/ical/}refreshrate
   * 3. {http://calendarserver.org/ns/}subscribed-strip-todos (omit if todos
   *    should not be stripped).
   * 4. {http://calendarserver.org/ns/}subscribed-strip-alarms (omit if alarms
   *    should not be stripped).
   * 5. {http://calendarserver.org/ns/}subscribed-strip-attachments (omit if
   *    attachments should not be stripped).
   * 7. {http://apple.com/ns/ical/}calendar-color
   * 8. {http://apple.com/ns/ical/}calendar-order
   * 9. {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
   *    (should just be an instance of
   *    Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet, with a bunch of
   *    default components).
   *
   * @param string $principalUri
   * @return array
   */
  public function getSubscriptionsForUser($principalUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getSubscriptionsForUser($principalUri)");
    return [];
  }
  /**
   * Creates a new subscription for a principal.
   *
   * If the creation was a success, an id must be returned that can be used to reference
   * this subscription in other methods, such as updateSubscription.
   *
   * @param string $principalUri
   * @param string $uri
   * @param array $properties
   * @return mixed
   */
  public function createSubscription($principalUri, $uri, array $properties) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.createSubscription($principalUri, $uri)");
  }
  /**
   * Updates a subscription
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
   * @param mixed $subscriptionId
   * @param \Sabre\DAV\PropPatch $propPatch
   * @return void
   */
  public function updateSubscription($subscriptionId, DAV\PropPatch $propPatch) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.updateSubscription($subscriptionId)");
  }
  /**
   * Deletes a subscription
   *
   * @param mixed $subscriptionId
   * @return void
   */
  public function deleteSubscription($subscriptionId) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.deleteSubscription($subscriptionId)");
  }
  /**
   * Returns a single scheduling object.
   *
   * The returned array should contain the following elements:
   *   * uri - A unique basename for the object. This will be used to
   *           construct a full uri.
   *   * calendardata - The iCalendar object
   *   * lastmodified - The last modification date. Can be an int for a unix
   *                    timestamp, or a PHP DateTime object.
   *   * etag - A unique token that must change if the object changed.
   *   * size - The size of the object, in bytes.
   *
   * @param string $principalUri
   * @param string $objectUri
   * @return array
   */
  public function getSchedulingObject($principalUri, $objectUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getSchedulingObject($principalUri, $objectUri)");
    return [];
  }
  /**
   * Returns all scheduling objects for the inbox collection.
   *
   * These objects should be returned as an array. Every item in the array
   * should follow the same structure as returned from getSchedulingObject.
   *
   * The main difference is that 'calendardata' is optional.
   *
   * @param string $principalUri
   * @return array
   */
  public function getSchedulingObjects($principalUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getSchedulingObjects($principalUri)");
    return [];
  }
  /**
   * Deletes a scheduling object
   *
   * @param string $principalUri
   * @param string $objectUri
   * @return void
   */
  public function deleteSchedulingObject($principalUri, $objectUri) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.deleteSchedulingObject($principalUri, $objectUri)");
  }
  /**
   * Creates a new scheduling object. This should land in a users' inbox.
   *
   * @param string $principalUri
   * @param string $objectUri
   * @param string $objectData
   * @return void
   */
  public function createSchedulingObject($principalUri, $objectUri, $objectData) {
    if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.createSchedulingObject($principalUri, $objectUri, $objectData)");
  }
}
