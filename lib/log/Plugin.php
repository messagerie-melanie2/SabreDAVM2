<?php
/**
 * Plugin pour la gestion des logs CalDAV
 *
 * SabreDAVM2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 */
namespace Lib\Log;

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
 * CalDAV plugin
 *
 * This plugin provides functionality added by CalDAV (RFC 4791)
 * It implements new reports, and the MKCALENDAR method.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Plugin extends DAV\ServerPlugin {

    /**
     * Reference to server object
     *
     * @var DAV\Server
     */
    protected $server;

    /**
     * Initializes the plugin
     *
     * @param DAV\Server $server
     * @return void
     */
    function initialize(DAV\Server $server) {
      $this->server = $server;

      $server->on('beforeMethod',            [$this,'beforeMethod']);
      $server->on('afterMethod',               [$this,'afterMethod']);
    }

    /**
     * beforeMethod LOG Method and Request
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return boolean
     */
    function beforeMethod(RequestInterface $request, ResponseInterface $response) {
      if (\Lib\Log\Log::isLvl(\Lib\Log\Log::INFO))
        \Lib\Log\Log::l(\Lib\Log\Log::INFO, "REQ===> method =".$request->getMethod()."= =".$request->getAbsoluteUrl()."= =".$request->getPath()."=");
      if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) {
        \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, 'User-Agent: ' . $request->getHeader('User-Agent'));
        $body = $request->getBodyAsString();
        // XXX: Erreur si on récupère le body et que ce n'est pas du FastPropfind
        // Surement lié à la ressource
        $request->setBody($body);
        \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, $body);
      }
      return true;
    }

    /**
     * afterMethod LOG Method and Response
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return boolean
     */
    function afterMethod(RequestInterface $request, ResponseInterface $response) {
      if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) {
        \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "RESP===> method =".$request->getMethod()."= =".$response->getStatus()."= =".$response->getStatusText()."= =".$request->getPath()."=");
        \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, $response->getBodyAsString());
      }
      return true;
    }

}
