<?php
/**
 * CalendarHomeM2 pour surcharger le CalendarHome de SabreDAV
 *
 * SabreDAVM2 Copyright (C) 2015  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\CalDAV;

use
    Sabre\DAV,
    Sabre\DAV\Exception\NotFound,
    Sabre\DAVACL,
    Sabre\HTTP\URLUtil;

/**
 * The CalendarHome represents a node that is usually in a users'
 * calendar-homeset.
 *
 * It contains all the users' calendars, and can optionally contain a
 * notifications collection, calendar subscriptions, a users' inbox, and a
 * users' outbox.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class CalendarHomeM2 extends CalendarHome {
  /**
   * Mise en cache des données
   * @var array
   */
  protected $cache;

  /**
   * Constructor
   *
   * @param Backend\BackendInterface $caldavBackend
   * @param mixed $userUri
   */
  function __construct(Backend\BackendInterface $caldavBackend, $principalInfo) {

    $this->caldavBackend = $caldavBackend;
    $this->principalInfo = $principalInfo;
    $this->cache = [];

  }
  /**
   * Returns a single calendar, by name
   *
   * @param string $name
   * @return Calendar
   */
  function getChild($name) {
    error_log("CalendarHomeM2.getChild($name)");
    if (isset($this->cache[$name])) {
      return $this->cache[$name];
    }

    // Special nodes
    if ($name === 'inbox' && $this->caldavBackend instanceof Backend\SchedulingSupport) {
        $this->cache[$name] = new Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
        return $this->cache[$name];
    }
    if ($name === 'outbox' && $this->caldavBackend instanceof Backend\SchedulingSupport) {
        $this->cache[$name] = new Schedule\Outbox($this->principalInfo['uri']);
        return $this->cache[$name];
    }
    if ($name === 'notifications' && $this->caldavBackend instanceof Backend\NotificationSupport) {
        $this->cache[$name] = new Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        return $this->cache[$name];
    }
    // PAMELA
    if ($this->caldavBackend instanceof Backend\Melanie2Support) {
      $this->cache[$name] = new Calendar($this->caldavBackend, $this->caldavBackend->getCalendarForPrincipal($this->principalInfo['uri'], $name));
      return $this->cache[$name];
    }

    // Calendars
    foreach($this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']) as $calendar) {
        if ($calendar['uri'] === $name) {
            if ($this->caldavBackend instanceof Backend\SharingSupport) {
                if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
                    $this->cache[$name] = new SharedCalendar($this->caldavBackend, $calendar);
                    return $this->cache[$name];
                } else {
                    $this->cache[$name] = new ShareableCalendar($this->caldavBackend, $calendar);
                    return $this->cache[$name];
                }
            } else {
                $this->cache[$name] = new Calendar($this->caldavBackend, $calendar);
                return $this->cache[$name];
            }
        }
    }

    if ($this->caldavBackend instanceof Backend\SubscriptionSupport) {
        foreach($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
            if ($subscription['uri'] === $name) {
                $this->cache[$name] = new Subscriptions\Subscription($this->caldavBackend, $subscription);
                return $this->cache[$name];
            }
        }

    }

    throw new NotFound('Node with name \'' . $name . '\' could not be found');

  }

  /**
   * Checks if a calendar exists.
   *
   * @param string $name
   * @return bool
   */
  function childExists($name) {

    try {
      // Gestion du cache
      if (isset($this->cache[$name])) {
        return true;
      }
      return !!$this->getChild($name);
    } catch (NotFound $e) {
      return false;
    }

  }
}
