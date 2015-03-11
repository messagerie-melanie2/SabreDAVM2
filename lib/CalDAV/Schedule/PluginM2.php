<?php
/**
 * Schedule/PluginM2 pour surcharger le plugin Schedule de SabreDAV
 *
 * SabreDAVM2 Copyright (C) 2015  PNE Annuaire et Messagerie/MEDDE
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
    Sabre\CalDAV\Property\ScheduleCalendarTransp,
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

}
