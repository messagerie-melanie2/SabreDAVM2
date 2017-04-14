<?php
/**
 * Schedule/PluginM2 pour surcharger le plugin Schedule de SabreDAV
 *
 * SabreDAVM2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\CalDAV\Schedule;

use
    DateTimeZone,
    Sabre\DAV\Server,
    Sabre\DAV\ServerPlugin,
    Sabre\DAV\Property\Href,
    Sabre\DAV\PropFind,
    Sabre\DAV\INode,
    Sabre\DAV\IFile,
    Sabre\HTTP\RequestInterface,
    Sabre\HTTP\ResponseInterface,
    Sabre\VObject,
    Sabre\VObject\Reader,
    Sabre\VObject\Component\VCalendar,
    Sabre\VObject\ITip,
    Sabre\VObject\ITip\Message,
    Sabre\DAVACL,
    Sabre\CalDAV\ICalendar,
    Sabre\CalDAV\ICalendarObject,
    Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp,
    Sabre\DAV\Exception\NotFound,
    Sabre\DAV\Exception\Forbidden,
    Sabre\DAV\Exception\BadRequest,
    Sabre\DAV\Exception\NotImplemented;

/**
 * CalDAV scheduling plugin.
 * =========================
 *
 * This plugin provides the functionality added by the "Scheduling Extensions
 * to CalDAV" standard, as defined in RFC6638.
 *
 * calendar-auto-schedule largely works by intercepting a users request to
 * update their local calendar. If a user creates a new event with attendees,
 * this plugin is supposed to grab the information from that event, and notify
 * the attendees of this.
 *
 * There's 3 possible transports for this:
 * * local delivery
 * * delivery through email (iMip)
 * * server-to-server delivery (iSchedule)
 *
 * iMip is simply, because we just need to add the iTip message as an email
 * attachment. Local delivery is harder, because we both need to add this same
 * message to a local DAV inbox, as well as live-update the relevant events.
 *
 * iSchedule is something for later.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class PluginM2 extends Plugin {
    /**
     * Returns a list of features for the DAV: HTTP header.
     *
     * @return array
     */
    function getFeatures() {
        // Ne pas utiliser calendar-auto-schedule mais calendar-schedule
        return ['calendar-schedule'];

    }

    /**
     * Returns free-busy information for a specific address. The returned
     * data is an array containing the following properties:
     *
     * calendar-data : A VFREEBUSY VObject
     * request-status : an iTip status code.
     * href: The principal's email address, as requested
     *
     * The following request status codes may be returned:
     *   * 2.0;description
     *   * 3.7;description
     *
     * @param string $email address
     * @param \DateTime $start
     * @param \DateTime $end
     * @param VObject\Component $request
     * @return array
     */
    protected function getFreeBusyForEmail($email, \DateTime $start, \DateTime $end, VObject\Component $request) {
    	if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAV/Schedule] PluginM2.getFreeBusyForEmail($email)");
    	
      $caldavNS = '{' . Plugin::NS_CALDAV . '}';

      $aclPlugin = $this->server->getPlugin('acl');
      if (substr($email,0,7)==='mailto:') $email = substr($email,7);

      $result = $aclPlugin->principalSearch(
          ['{http://sabredav.org/ns}email-address' => $email],
          [
            '{DAV:}principal-URL', $caldavNS . 'calendar-home-set',
            '{http://sabredav.org/ns}email-address',
          ]
      );

      if (!count($result)) {
        return [
          'request-status' => '3.7;Could not find principal',
          'href' => 'mailto:' . $email,
        ];
      }

      if (!isset($result[0][200][$caldavNS . 'calendar-home-set'])) {
        return [
          'request-status' => '3.7;No calendar-home-set property found',
          'href' => 'mailto:' . $email,
        ];
      }
      $homeSet = $result[0][200][$caldavNS . 'calendar-home-set']->getHref();

      // Grabbing the calendar list
      $objects = [];
      $calendarTimeZone = new DateTimeZone('UTC');

      foreach($this->server->tree->getNodeForPath($homeSet)->getChildren() as $node) {
        if (!$node instanceof ICalendar) {
          continue;
        }

        $sct = $caldavNS . 'schedule-calendar-transp';
        $ctz = $caldavNS . 'calendar-timezone';
        $props = $node->getProperties([$sct, $ctz]);

        if (isset($props[$sct]) && $props[$sct]->getValue() == ScheduleCalendarTransp::TRANSPARENT) {
          // If a calendar is marked as 'transparent', it means we must
          // ignore it for free-busy purposes.
          continue;
        }

        $aclPlugin->checkPrivileges($homeSet . $node->getName() ,$caldavNS . 'read-free-busy');

        if (isset($props[$ctz])) {
          $vtimezoneObj = VObject\Reader::read($props[$ctz]);
          $calendarTimeZone = $vtimezoneObj->VTIMEZONE->getTimeZone();
        }

        // Getting the list of object uris within the time-range
        $urls = $node->calendarQuery([
              'name' => 'VCALENDAR',
              'comp-filters' => [
                [
                  'name' => 'VEVENT',
                  'comp-filters' => [],
                  'prop-filters' => [],
                  'is-not-defined' => false,
                  'time-range' => [
                    'start' => $start,
                    'end' => $end,
                  ],
                ],
              ],
              'prop-filters' => [],
              'is-not-defined' => false,
              'time-range' => null,
            ]);

        $calObjects = array_map(function($url) use ($node) {
          $obj = $node->getChild($url)->get();
          return $obj;
        }, $urls);

          $objects = array_merge($objects,$calObjects);

      }

      $vcalendar = new VObject\Component\VCalendar();
      $vcalendar->METHOD = 'REPLY';

      $generator = new VObject\FreeBusyGenerator();
      $generator->setObjects($objects);
      $generator->setTimeRange($start, $end);
      $generator->setBaseObject($vcalendar);
      $generator->setTimeZone($calendarTimeZone);

      $result = $generator->getResult();

      $vcalendar->VFREEBUSY->ATTENDEE = 'mailto:' . $email;
      $vcalendar->VFREEBUSY->UID = (string)$request->VFREEBUSY->UID;
      $vcalendar->VFREEBUSY->ORGANIZER = clone $request->VFREEBUSY->ORGANIZER;

      return [
        'calendar-data' => $result,
        'request-status' => '2.0;Success',
        'href' => 'mailto:' . $email,
      ];
    }

}
