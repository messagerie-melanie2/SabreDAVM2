<?php
/**
 * Fichier de gestion du backend CalDAV pour l'application SabreDAVM2
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
namespace Sabre\CalDAV\Backend;

use LibMelanie\Config\ConfigMelanie;

use
    Sabre\VObject,
    Sabre\CalDAV,
    Sabre\DAV,
    Sabre\DAV\Exception\Forbidden;

/**
 * LibM2 CalDAV backend
 *
 * Utilisation de l'ORM Mélanie2 pour l'implémentation de ce backend
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBackend implements SchedulingSupport, Melanie2Support {
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
     * Date limite maximum pour l'ancienneté des évènements retournés
     * @var string
     */
    const DATE_MAX = "-18 months";

    /**
     * List of CalDAV properties, and how they map to database fieldnames
     * Add your own properties by simply adding on to this array.
     *
     * Note that only string-based properties are supported here.
     *
     * @var array
     */
    public $propertyMap = [
        '{DAV:}displayname'                          => 'displayname',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
        '{urn:ietf:params:xml:ns:caldav}calendar-timezone'    => 'timezone',
        '{http://apple.com/ns/ical/}calendar-order'  => 'calendarorder',
        '{http://apple.com/ns/ical/}calendar-color'  => 'calendarcolor',
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
    function __construct(\Sabre\DAV\Auth\Backend\LibM2 $authBackend) {
        error_log("[AbstractBackend] LibM2.__construct()");
        $this->authBackend = $authBackend;
        $this->calendars = [];
        $this->cache_events = [];
    }

    /**
     * Récupère l'utilisateur lié au principalURI
     */
    protected function getUserFromPrincipalUri($principalUri) {
        $var = explode('/', $principalUri);
        return $var[1];
    }

    /**
     * Charge la liste des calendriers de l'utilisateur
     */
    protected function loadUserCalendars(\LibMelanie\Api\Melanie2\User $user) {
      if (!isset($this->calendars)
          || count($this->calendars) === 0) {
        $this->calendars = $user->getSharedCalendars();
      }
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
     * Sabre\CalDAV\Property\SupportedCalendarComponentSet.
     *
     * If you return {http://sabredav.org/ns}read-only and set the value to 1,
     * ACL will automatically be put in read-only mode.
     *
     * @param string $principalUri
     * @return array
     */
    function getCalendarsForUser($principalUri) {
        error_log("[AbstractBackend] LibM2.getCalendarsForUser($principalUri)");
        $user = new \LibMelanie\Api\Melanie2\User();
        $user->uid = $this->getUserFromPrincipalUri($principalUri);

        // Charge la liste des calendriers
        $this->loadUserCalendars($user);

        if (!isset($this->calendars_prop)) {
          // Récupération des prefs supplémentaires
          $pref = new \LibMelanie\Api\Melanie2\UserPrefs($user);
          $pref->scope = \LibMelanie\Config\ConfigMelanie::CALENDAR_PREF_SCOPE;
          $pref->name = "caldav_properties";
          $this->calendars_prop = [];
          if ($pref->load()) {
            $this->calendars_prop = unserialize($pref->value);
          }
        }
        $calendar = [];
        foreach ($this->calendars as $_calendar) {
          // Gestion du ctag
          $ctag = $this->getCalendarCTag($principalUri, $_calendar->id);
          // Utilisation seul des VEVENTS pour l'instant
          if ($_calendar->id == $_calendar->owner
              && $user->uid == $_calendar->owner) {
            // Calendrier principal de l'utilisateur
            $components = ['VEVENT', 'VTODO', 'VTIMEZONE', 'VFREEBUSY'];
            $transp = 'opaque';
            // Gestion du ctag pour les tâches
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
            $taskslist->id = $_calendar->id;
            $ctag_tasks = $taskslist->getCTag();
            $ctag = md5($ctag . $ctag_tasks);
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
              '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => $ctag,
              '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Property\SupportedCalendarComponentSet($components),
              '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new CalDAV\Property\ScheduleCalendarTransp($transp),
              '{DAV:}displayname' => $_calendar->name,
          ];
          if (isset($this->calendars_prop[$_calendar->id])) {
            $calendar = array_merge($calendar, $this->calendars_prop[$_calendar->id]);
          }
          $calendars[] = $calendar;
        }
        error_log("[AbstractBackend] LibM2.getCalendarsForUser($principalUri) : " . var_export($calendars, true));
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
     * Sabre\CalDAV\Property\SupportedCalendarComponentSet.
     *
     * If you return {http://sabredav.org/ns}read-only and set the value to 1,
     * ACL will automatically be put in read-only mode.
     *
     * @param string $principalUri
     * @param string $calendarId
     * @return array
     */
    function getCalendarForPrincipal($principalUri, $calendarId) {
      error_log("[AbstractBackend] LibM2.getCalendarForPrincipal($principalUri, $calendarId)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->getUserFromPrincipalUri($principalUri);

      if (!isset($this->calendars[$calendarId])) {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if (!$this->calendars[$calendarId]->load()) {
          unset($this->calendars[$calendarId]);
        }
      }

      if (isset($this->calendars[$calendarId])
          && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
        if ($this->calendars[$calendarId]->id == $this->calendars[$calendarId]->owner
            && $user->uid == $this->calendars[$calendarId]->owner) {
          // Calendrier principal de l'utilisateur
          $components = ['VEVENT', 'VTODO', 'VTIMEZONE', 'VFREEBUSY'];
          $transp = 'opaque';
        }
        else {
          // Calendrier secondaire ou partagé (pas de freebusy et de taches
          $components = ['VEVENT', 'VTIMEZONE'];
          $transp = 'transparent';
        }
        return [
          'id' => $this->calendars[$calendarId]->id,
          'uri' => $this->calendars[$calendarId]->id,
          'principaluri' => $principalUri,
          '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => $this->getCalendarCTag($principalUri, $calendarId),
          '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Property\SupportedCalendarComponentSet($components),
          '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' =>  new CalDAV\Property\ScheduleCalendarTransp($transp),
          '{DAV:}displayname' => $this->calendars[$calendarId]->name,
        ];
      }
      return [];
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
    function getCalendarCTag($principalUri, $calendarId) {
      if (!isset($this->calendars[$calendarId])) {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if (!$this->calendars[$calendarId]->load()) {
          unset($this->calendars[$calendarId]);
        }
      }
      $ctag = null;
      if (isset($this->calendars[$calendarId])) {
        $ctag = $this->calendars[$calendarId]->getCTag();
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
    function createCalendar($principalUri, $calendarUri, array $properties) {
      error_log("[AbstractBackend] LibM2.createCalendar($principalUri, $calendarUri)");
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
    function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {
      error_log("[AbstractBackend] LibM2.updateCalendar($calendarId, ".var_export($propPatch, true).")");

      $supportedProperties = array_keys($this->propertyMap);

      $propPatch->handle($supportedProperties, function($mutations) use ($calendarId) {
        $user = new \LibMelanie\Api\Melanie2\User();
        $user->uid = $this->authBackend->getCurrentUser();

        // Récupération des prefs supplémentaires
        $pref = new \LibMelanie\Api\Melanie2\UserPrefs($user);
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
    function deleteCalendar($calendarId) {
      error_log("[AbstractBackend] LibM2.deleteCalendar($calendarId)");
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
    function getCalendarObjects($calendarId) {
      error_log("[AbstractBackend] LibM2.getCalendarObjects($calendarId)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();
      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = [];
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
        $start = new \DateTime();
        $start->modify(self::DATE_MAX);
        $this->cache_events = $this->calendars[$calendarId]->getRangeEvents($start->format(self::DB_DATE_FORMAT));
        //$this->cache_events = $this->calendars[$calendarId]->getAllEvents();
        foreach($this->cache_events as $_event) {
          $event = [
              'id'           => $_event->uid,
              'uri'          => $_event->uid.'.ics',
              'lastmodified' => $_event->modified,
              'etag'         => '"' . md5($_event->modified) . '"',
              'calendarid'   => $_event->calendar,
              'calendardata'  => $_event->ics,
              'component'    => 'vevent',
          ];
          $event['size'] = strlen($event['calendardata']);
          $result[] = $event;
        }
        // Test si on est dans un calendrier principal, auquel cas on doit charger les tâches
        if ($this->calendars[$calendarId]->id == $this->calendars[$calendarId]->owner
            && $user->uid == $this->calendars[$calendarId]->owner) {
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
          $taskslist->id = $this->calendars[$calendarId]->id;
          $this->cache_tasks = $taskslist->getAllTasks();
          foreach($this->cache_tasks as $_task) {
            $task = [
              'id'           => $_task->uid,
              'uri'          => $_task->uid.'.ics',
              'lastmodified' => $_task->modified,
              'etag'         => '"' . md5($_task->modified) . '"',
              'calendarid'   => $_task->taskslist,
              'calendardata'  => $_task->ics,
              'component'    => 'vtodo',
            ];
            $task['size'] = strlen($task['calendardata']);
            $result[] = $task;
          }
        }
      }
      error_log("[AbstractBackend] LibM2.getCalendarObjects($calendarId) : " . var_export($result, true));
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
    function getCalendarObject($calendarId, $objectUri) {
      error_log("[AbstractBackend] LibM2.getCalendarObject($calendarId,$objectUri)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = [];
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
          $event_uid = str_replace('.ics', '', $objectUri);
          // Cherche si l'évènement n'est pas déjà dans le cache
          if (!isset($this->cache_events[$event_uid.$calendarId])) {
            $event = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
            $event->uid = $event_uid;
            if ($event->load()) {
              $this->cache_events[$event_uid.$calendarId] = $event;
            }
            else if ($calendarId == $user->uid
                && $this->calendars[$calendarId]->owner == $calendarId) {
              // Cas du calendrier principal, on gère les tâches
              if (!isset($this->cache_tasks[$event_uid.$calendarId])) {
                $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
                $taskslist->id = $calendarId;
                $task = new \LibMelanie\Api\Melanie2\Task($user, $taskslist);
                $task->uid = $event_uid;
                if ($task->load()) {
                  $this->cache_tasks[$event_uid.$calendarId] = $task;
                }
              }
            }
          }
          // Si l'évènement existe on retourne le resultat
          if (isset($this->cache_events[$event_uid.$calendarId])) {
            if (is_object($this->cache_events[$event_uid.$calendarId])) {
              $result = [
                'id'            => $this->cache_events[$event_uid.$calendarId]->uid,
                'uri'           => $this->cache_events[$event_uid.$calendarId]->uid.'.ics',
                'lastmodified'  => $this->cache_events[$event_uid.$calendarId]->modified,
                'etag'          => '"' . md5($this->cache_events[$event_uid.$calendarId]->modified) . '"',
                'calendarid'    => $this->cache_events[$event_uid.$calendarId]->calendar,
                'calendardata'  => $this->cache_events[$event_uid.$calendarId]->ics,
                'component'     => 'vevent',
              ];
              $result['size'] = strlen($result['calendardata']);
            }
          }
          elseif (isset($this->cache_tasks[$event_uid.$calendarId])) {
            if (is_object($this->cache_tasks[$event_uid.$calendarId])) {
              $result = [
                'id'            => $this->cache_tasks[$event_uid.$calendarId]->uid,
                'uri'           => $this->cache_tasks[$event_uid.$calendarId]->uid.'.ics',
                'lastmodified'  => $this->cache_tasks[$event_uid.$calendarId]->modified,
                'etag'          => '"' . md5($this->cache_tasks[$event_uid.$calendarId]->modified) . '"',
                'calendarid'    => $this->cache_tasks[$event_uid.$calendarId]->taskslist,
                'calendardata'  => $this->cache_tasks[$event_uid.$calendarId]->ics,
                'component'     => 'vtodo',
              ];
              $result['size'] = strlen($result['calendardata']);
            }
          }
      }
      error_log("[AbstractBackend] LibM2.getCalendarObject($calendarId,$objectUri) : " . var_export($result, true));
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
    function getMultipleCalendarObjects($calendarId, array $uris) {
      error_log("[AbstractBackend] LibM2.getMultipleCalendarObjects($calendarId, ".var_export($uris, true).")");
      $list_event_uid = [];
      // Remove .ics from the uri
      foreach ($uris as $uri) {
          $list_event_uid[] = str_replace('.ics', '', $uri);
      }

      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = [];
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
          $_events = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
          $_events->uid = $list_event_uid;
          $this->cache_events = $_events->getList();
          $itemsFound = 0;
          foreach($this->cache_events as $_event) {
              $event = [
                'id'           => $_event->uid,
                'uri'          => $_event->uid.'.ics',
                'lastmodified' => $_event->modified,
                'etag'         => '"' . md5($_event->modified) . '"',
                'calendarid'   => $_event->calendar,
                'component'    => 'vevent',
                'calendardata' => $_event->ics,
              ];
              $event['size'] = strlen($event['calendardata']);
              $result[] = $event;
              $itemsFound++;
              // Vide la liste des uid
              if ($index = array_search($_event->uid, $list_event_uid)) {
                unset($list_event_uid[$index]);
              }
          }
          // Si c'est un calendrier principal, on cherche les tâches
          if ($calendarId == $user->uid
                && $this->calendars[$calendarId]->owner == $calendarId) {
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
            $taskslist->id = $calendarId;
            $_tasks = new \LibMelanie\Api\Melanie2\Task($user, $taskslist);
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
                'calendardata' => $_task->ics,
              ];
              $task['size'] = strlen($task['calendardata']);
              $result[] = $task;
              $itemsFound++;
              // Vide la liste des uid
              if ($index = array_search($_task->uid, $list_event_uid)) {
                unset($list_event_uid[$index]);
              }
            }
          }
          // Si on ne trouve pas les évènements recherchés, il s'agit certainement de FAKED MASTER
          if (count($uris) > $itemsFound) {
            // Remove .ics from the uri
            foreach ($list_event_uid as $uid) {
              $list_event_uid[] = $uid.'%@RECURRENCE-ID';
            }
            $_events = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
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
                'calendardata' => $_event->ics,
              ];
              $event['size'] = strlen($event['calendardata']);
              $result[] = $event;
            }
          }
      }
      error_log("[AbstractBackend] LibM2.getMultipleCalendarObjects($calendarId) : " . var_export($result, true));
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
    function createCalendarObject($calendarId,$objectUri,$calendarData) {
      error_log("[AbstractBackend] LibM2.createCalendarObject($calendarId,$objectUri,$calendarData)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = null;
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
        // Création d'une tâche ou d'un évènement ?
        $is_task = false;
        if ($calendarId == $user->uid
                && $this->calendars[$calendarId]->owner == $calendarId) {
          if (strpos($calendarData, 'BEGIN:VTODO') !== false) {
            $is_task = true;
          }
        }
        $event_uid = str_replace('.ics', '', $objectUri);
        if ($is_task) {
          // Cherche si la tâche n'est pas déjà dans le cache
          if (isset($this->cache_tasks[$event_uid.$calendarId])) {
            $task = $this->cache_tasks[$event_uid.$calendarId];
          }
          else {
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
            $taskslist->id = $calendarId;
            $task = new \LibMelanie\Api\Melanie2\Task($user, $taskslist);
            $task->uid = $event_uid;
          }
          $task->owner = $this->authBackend->getCurrentUser();
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
          if (isset($this->cache_events[$event_uid.$calendarId])) {
            $event = $this->cache_events[$event_uid.$calendarId];
          }
          else {
            $event = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
            $event->uid = $event_uid;
          }
          $event->owner = $this->authBackend->getCurrentUser();
          $event->ics = $calendarData;
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
    function updateCalendarObject($calendarId,$objectUri,$calendarData) {
      error_log("[AbstractBackend] LibM2.updateCalendarObject($calendarId,$objectUri,$calendarData)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = null;
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
        // Création d'une tâche ou d'un évènement ?
        $is_task = false;
        if ($calendarId == $user->uid
            && $this->calendars[$calendarId]->owner == $calendarId) {
          if (strpos($calendarData, 'BEGIN:VTODO') !== false) {
            $is_task = true;
          }
        }
        $event_uid = str_replace('.ics', '', $objectUri);
        if ($is_task) {
          // Cherche si la tâche n'est pas déjà dans le cache
          if (isset($this->cache_tasks[$event_uid.$calendarId])) {
            $task = $this->cache_tasks[$event_uid.$calendarId];
          }
          else {
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
            $taskslist->id = $calendarId;
            $task = new \LibMelanie\Api\Melanie2\Task($user, $taskslist);
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
          if (isset($this->cache_events[$event_uid.$calendarId])) {
            $event = $this->cache_events[$event_uid.$calendarId];
          }
          else {
            $event = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
            $event->uid = $event_uid;
          }
          if ($event->load()) {
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
    function deleteCalendarObject($calendarId,$objectUri) {
      error_log("[AbstractBackend] LibM2.deleteCalendarObject($calendarId,$objectUri)");
      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      if ($loaded
            && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::WRITE)) {
        $event = new \LibMelanie\Api\Melanie2\Event($user, $this->calendars[$calendarId]);
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
        elseif ($calendarId == $user->uid
            && $this->calendars[$calendarId]->owner == $calendarId) {
          // Si on est dans le calendrier principal, on cherche peut être une tâche
          $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
          $taskslist->id = $calendarId;
          $task = new \LibMelanie\Api\Melanie2\Task($user, $taskslist);
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
    function calendarQuery($calendarId, array $filters) {
      error_log("[AbstractBackend] LibM2.calendarQuery($calendarId, ".var_export($filters, true).")");
      $componentType = null;
      $requirePostFilter = true;
      $timeRange = null;

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

      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Cherche si le calendrier est présent en mémoire
      if (isset($this->calendars)
          && isset($this->calendars[$calendarId])) {
        $loaded = true;
      }
      else {
        $this->calendars[$calendarId] = new \LibMelanie\Api\Melanie2\Calendar($user);
        $this->calendars[$calendarId]->id = $calendarId;
        if ($this->calendars[$calendarId]->load()) {
          $loaded = true;
        }
        else {
          unset($this->calendars[$calendarId]);
        }
      }
      $result = [];
      if ($loaded
          && $this->calendars[$calendarId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
        if ($componentType == 'VTODO') {
          if ($calendarId == $user->uid
              && $this->calendars[$calendarId]->owner == $calendarId) {
            $taskslist = new \LibMelanie\Api\Melanie2\Taskslist($user);
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
            $start->modify(self::DATE_MAX);
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
    function getCalendarObjectByUID($principalUri, $uid) {
      error_log("[AbstractBackend] LibM2.getCalendarObjectByUID($principalUri, $uid)");

      $list_event_uid = [];
      // Remove .ics from the uri
      foreach ($uris as $uri) {
        $list_event_uid[] = str_replace('.ics', '', $uri);
      }

      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->authBackend->getCurrentUser();

      // Récupère la liste des agendas de l'utilisateur
      $this->loadUserCalendars($user);
      $calendar_uids = [];
      foreach ($this->calendars as $calendar) {
        $calendar_uids[] = $calendar->id;
      }

      if (count($calendar_uids) > 0) {
        $_events = new \LibMelanie\Api\Melanie2\Event($user);
        $_events->uid = $list_event_uid;
        $_events->calendar = $calendar_uids;
        foreach($_events->getList() as $_event) {
          return $_event->calendar.'/'.$_event->uid.'.ics';
        }
      }
      return null;
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
     *    Sabre\CalDAV\Property\SupportedCalendarComponentSet, with a bunch of
     *    default components).
     *
     * @param string $principalUri
     * @return array
     */
    function getSubscriptionsForUser($principalUri) {
      error_log("[AbstractBackend] LibM2.getSubscriptionsForUser($principalUri)");
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
    function createSubscription($principalUri, $uri, array $properties) {
      error_log("[AbstractBackend] LibM2.createSubscription($principalUri, $uri)");
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
    function updateSubscription($subscriptionId, DAV\PropPatch $propPatch) {
      error_log("[AbstractBackend] LibM2.updateSubscription($subscriptionId)");
    }

    /**
     * Deletes a subscription
     *
     * @param mixed $subscriptionId
     * @return void
     */
    function deleteSubscription($subscriptionId) {
      error_log("[AbstractBackend] LibM2.deleteSubscription($subscriptionId)");
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
    function getSchedulingObject($principalUri, $objectUri) {
      error_log("[AbstractBackend] LibM2.getSchedulingObject($principalUri, $objectUri)");

      return [];

      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->getUserFromPrincipalUri($principalUri);
      $result = [];

      // Récupération de l'agenda par défaut de l'utilisateur
      $calendar = $user->getDefaultCalendar();
      if (!isset($calendar)) {
        if (isset($this->calendars)
            && isset($this->calendars[$user->uid])) {
          // Si pas d'agenda par défaut on essaye de récupérer un agenda chargé
          $calendar = $this->calendars[$user->uid];
        }
        else {
          // Si pas d'agenda chargé, on essaye de prendre le principal
          $calendar = new \LibMelanie\Api\Melanie2\Calendar($user);
          $calendar->id = $user->uid;
          if (!$calendar->load()) {
            // Si pas de calendrier chargé on retourne vide
            return $result;
          }
        }
      }

      $event_uid = str_replace('.ics', '', $objectUri);
      // Cherche si l'évènement n'est pas déjà dans le cache
      if (isset($this->cache_events[$event_uid.$calendar->id])) {
        $event = $this->cache_events[$event_uid.$calendar->id];
      }
      else {
        $event = new \LibMelanie\Api\Melanie2\Event($user, $calendar);
        $event->uid = $event_uid;
        if (!$event->load()) {
          $event = null;
        }
      }
      // Si l'évènement existe on retourne le resultat
      if (isset($event)) {
        $this->cache_events[$event->uid.$calendarId] = $event;
        $result = [
          'id'            => $event->uid,
          'uri'           => $event->uid.'.ics',
          'lastmodified'  => $event->modified,
          'etag'          => '"' . md5($event->modified) . '"',
          'calendarid'    => $event->calendar,
          'calendardata'  => $event->ics,
          'component'     => 'vevent',
        ];
        $result['size'] = strlen($result['calendardata']);
      }
      error_log("[AbstractBackend] LibM2.getSchedulingObject($principalUri, $objectUri) : " . var_export($result, true));
      return $result;
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
    function getSchedulingObjects($principalUri) {
      error_log("[AbstractBackend] LibM2.getSchedulingObjects($principalUri)");

      return [];

      $user = new \LibMelanie\Api\Melanie2\User();
      $user->uid = $this->getUserFromPrincipalUri($principalUri);
      $result = [];

      // Récupération de l'agenda par défaut de l'utilisateur
      $calendar = $user->getDefaultCalendar();
      if (!isset($calendar)) {
        if (isset($this->calendars)
            && isset($this->calendars[$user->uid])) {
          // Si pas d'agenda par défaut on essaye de récupérer un agenda chargé
          $calendar = $this->calendars[$user->uid];
        }
        else {
          // Si pas d'agenda chargé, on essaye de prendre le principal
          $calendar = new \LibMelanie\Api\Melanie2\Calendar($user);
          $calendar->id = $user->uid;
          if (!$calendar->load()) {
            // Si pas de calendrier chargé on retourne vide
            return $result;
          }
        }
      }

      $start = new \DateTime();
      $start->modify(self::DATE_MAX);
      $_events = $calendar->getRangeEvents($start->format(self::DB_DATE_FORMAT));
      foreach ($_events as $_event) {
        $event = [
          'id'            => $_event->uid,
          'uri'           => $_event->uid.'.ics',
          'lastmodified'  => $_event->modified,
          'etag'          => '"' . md5($_event->modified) . '"',
          'calendarid'    => $_event->calendar,
          'calendardata'  => $_event->ics,
          'component'     => 'vevent',
        ];
        $event['size'] = strlen($event['calendardata']);
        $result[] = $event;
      }
      error_log("[AbstractBackend] LibM2.getSchedulingObjects($principalUri) : " . var_export($result, true));
      return $result;
    }

    /**
     * Deletes a scheduling object
     *
     * @param string $principalUri
     * @param string $objectUri
     * @return void
     */
    function deleteSchedulingObject($principalUri, $objectUri) {
      error_log("[AbstractBackend] LibM2.deleteSchedulingObject($principalUri, $objectUri)");
    }

    /**
     * Creates a new scheduling object. This should land in a users' inbox.
     *
     * @param string $principalUri
     * @param string $objectUri
     * @param string $objectData
     * @return void
     */
    function createSchedulingObject($principalUri, $objectUri, $objectData) {
      error_log("[AbstractBackend] LibM2.createSchedulingObject($principalUri, $objectUri, $objectData)");
    }

}
