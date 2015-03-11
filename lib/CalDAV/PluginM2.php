<?php
/**
 * CalDAV/PluginM2 pour surcharger le plugin CalDAV de SabreDAV
 *
 * SabreDAVM2 Copyright (C) 2015  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\CalDAV;

use DateTimeZone;
use Sabre\DAV;
use Sabre\DAV\Property\HrefList;
use Sabre\DAVACL;
use Sabre\VObject;
use Sabre\HTTP;
use Sabre\HTTP\URLUtil;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * CalDAV plugin Melanie2
 *
 * This plugin provides functionality added by CalDAV (RFC 4791)
 * It implements new reports, and the MKCALENDAR method.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class PluginM2 extends Plugin {
  /**
   * PropFind
   *
   * This method handler is invoked before any after properties for a
   * resource are fetched. This allows us to add in any CalDAV specific
   * properties.
   *
   * @param DAV\PropFind $propFind
   * @param DAV\INode $node
   * @return void
   */
  function propFind(DAV\PropFind $propFind, DAV\INode $node) {
      $ns = '{' . self::NS_CALDAV . '}';

      if ($node instanceof ICalendarObjectContainer) {

          $propFind->handle($ns . 'max-resource-size', $this->maxResourceSize);
          $propFind->handle($ns . 'supported-calendar-data', function() {
              return new Property\SupportedCalendarData();
          });
          $propFind->handle($ns . 'supported-collation-set', function() {
              return new Property\SupportedCollationSet();
          });

      }

      if ($node instanceof DAVACL\IPrincipal) {

          $principalUrl = $node->getPrincipalUrl();

          $propFind->handle('{' . self::NS_CALDAV . '}calendar-home-set', function() use ($principalUrl) {

              $calendarHomePath = $this->getCalendarHomeForPrincipal($principalUrl) . '/';
              return new DAV\Property\Href($calendarHomePath);

          });
          // The calendar-user-address-set property is basically mapped to
          // the {DAV:}alternate-URI-set property.
          $propFind->handle('{' . self::NS_CALDAV . '}calendar-user-address-set', function() use ($node) {
              $addresses = $node->getAlternateUriSet();
              $addresses[] = $this->server->getBaseUri() . $node->getPrincipalUrl() . '/';
              return new HrefList($addresses, false);
          });
          // For some reason somebody thought it was a good idea to add
          // another one of these properties. We're supporting it too.
          $propFind->handle('{' . self::NS_CALENDARSERVER . '}email-address-set', function() use ($node) {
              $addresses = $node->getAlternateUriSet();
              $emails = [];
              foreach($addresses as $address) {
                  if (substr($address,0,7)==='mailto:') {
                      $emails[] = substr($address,7);
                  }
              }
              return new Property\EmailAddressSet($emails);
          });

          // These two properties are shortcuts for ical to easily find
          // other principals this principal has access to.
          $propRead = '{' . self::NS_CALENDARSERVER . '}calendar-proxy-read-for';
          $propWrite = '{' . self::NS_CALENDARSERVER . '}calendar-proxy-write-for';

          if ($propFind->getStatus($propRead)===404 || $propFind->getStatus($propWrite)===404) {

              $aclPlugin = $this->server->getPlugin('acl');
              $membership = $aclPlugin->getPrincipalMembership($propFind->getPath());
              $readList = [];
              $writeList = [];

              foreach($membership as $group) {

                  $groupNode = $this->server->tree->getNodeForPath($group);

                  // If the node is either ap proxy-read or proxy-write
                  // group, we grab the parent principal and add it to the
                  // list.
                  if ($groupNode instanceof Principal\IProxyRead) {
                      list($readList[]) = URLUtil::splitPath($group);
                  }
                  if ($groupNode instanceof Principal\IProxyWrite) {
                      list($writeList[]) = URLUtil::splitPath($group);
                  }

              }

              $propFind->set($propRead, new HrefList($readList));
              $propFind->set($propWrite, new HrefList($writeList));

          }

      } // instanceof IPrincipal

      if ($node instanceof ICalendarObject) {

          // The calendar-data property is not supposed to be a 'real'
          // property, but in large chunks of the spec it does act as such.
          // Therefore we simply expose it as a property.
          $propFind->handle( '{' . Plugin::NS_CALDAV . '}calendar-data', function() use ($node) {
              $val = $node->get();
              if (is_resource($val))
                  $val = stream_get_contents($val);

              // Taking out \r to not screw up the xml output
              return str_replace("\r","", $val);

          });

      }

  }
}
