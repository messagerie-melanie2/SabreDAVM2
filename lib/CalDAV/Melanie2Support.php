<?php
/**
 * Classe personnalisée pour Melanie2
 *
 * SabreDAVM2 Copyright (C) 2015  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\CalDAV\Backend;

/**
 * Implementing this interface adds CalDAV Melanie2 support to your caldav
 * server, as defined in rfc6638.
 *
 * @author Thomas Payen/PNE Annuaire et Messagerie (MEDDE)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
interface Melanie2Support extends BackendInterface {

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
  function getCalendarForPrincipal($principalUri, $calendarId);


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
  function getCalendarCTag($principalUri, $calendarId);

}
