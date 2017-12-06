<?php
/**
 * Serveur CalDAV pour l'application SabreDAVM2
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
// Configuration du nom de l'application pour l'ORM
if (!defined('CONFIGURATION_APP_LIBM2')) {
  define('CONFIGURATION_APP_LIBM2', 'sabredav');
}
// Inclusion de l'ORM
@include_once 'includes/libm2.php';
// Inclusion de la configuration de l'application
require_once 'config/includes.php';
// Includes
require_once 'lib/includes/includes.php';

/**
 * Classe de serveur CalDAV via SabreDAV
 * Implémente et démarre les plugins et backends ORM M2 et Mélanie2
 *
 * @author Thomas Payen
 * @author PNE Annuaire et Message/MEDDE
 *
 */
class CalDAV {
  /**
   * server SabreDAV
   * @var Sabre\DAV\Server
   */
  private static $server;
  /**
   * Backend d'authentification
   * @var Sabre\DAV\Auth\Backend\LibM2
   */
  private static $authBackend;
  /**
   * Backend calendar
   * @var Sabre\CalDAV\Backend\LibM2
   */
  private static $calendarBackend;
  /**
   * Backend principal
   * @var Sabre\DAVACL\PrincipalBackend\LibM2
   */
  private static $principalBackend;
  /**
   * Démarrage des différents modules du serveur CalDAV
   * @throws ErrorException
   */
  public static function Start() {
    // Set default timezone, based on ORM configuration
    date_default_timezone_set(\LibMelanie\Config\ConfigMelanie::CALENDAR_DEFAULT_TIMEZONE);

    //Mapping PHP errors to exceptions
//     function exception_error_handler($errno, $errstr, $errfile, $errline ) {
//       throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
//     }
//     set_error_handler("exception_error_handler");

    // Initialisation des backends
    self::InitBackends();

    // Directory structure
    $tree = [
      new Sabre\CalDAV\Principal\Collection(self::$principalBackend),
      new Sabre\CalDAV\CalendarRootM2(self::$principalBackend, self::$calendarBackend),
    ];
    // Création du serveur CalDAV
    self::$server = new Sabre\DAV\Server($tree);
    // Configuration de la baseUri
    self::$server->setBaseUri(\Config\Config::caldavBaseUri);
    // Configuration du mode debug pour le serveur
    self::$server->debugExceptions = \Config\Config::debugExceptions;
    // Définition du serveur dans le backend M2
    self::$calendarBackend->setServer(self::$server);
    self::$principalBackend->setServer(self::$server);    
    // Initialisation des logs
    self::InitLogs();

    // PAMELA 10/09/10 Traitement des PROPFIND
    // optimiser les PROPFIND qui ne demandent que le getctag
    if ( self::$server->httpRequest->getMethod() == 'PROPFIND' || self::$server->httpRequest->getMethod() == 'REPORT')
    {
      $server = self::$server;
      $v = $server->httpRequest->getBodyAsString();
      // XXX: Erreur si on récupère le body et que ce n'est pas du FastPropfind
      // Surement lié à la ressource
      $server->httpRequest->setBody($v);
      if (self::$server->httpRequest->getMethod() == 'PROPFIND' && strpos($v, "<D:prop><CS:getctag/></D:prop>") !== false) {
        require 'lib/CalDAV/Backend/FastPropfind.php';
        exit;
      }
      elseif (self::$server->httpRequest->getMethod() == 'REPORT' 
      		&& strpos($v, "<sync-collection xmlns=\"DAV:\"><sync-token>") !== false
      		&& strpos($v, "</sync-token><sync-level>1</sync-level><prop><getcontenttype/><getetag/></prop></sync-collection>") !== false) {
      	self::$authBackend->setNoAuthReportMethod(true);
      	self::$principalBackend->setNoPrincipalSearch(true);
      }
    }
    // Initialisation des plugins
    self::InitPlugins();
    // Démarrage du serveur
    self::$server->exec();
  }
  /**
   * Initialisation des backends
   */
  private static function InitBackends() {
    // Définition des backends Mélanie2
    self::$authBackend = new Sabre\DAV\Auth\Backend\LibM2();
    self::$calendarBackend = new Sabre\CalDAV\Backend\LibM2(self::$authBackend);
    self::$principalBackend = new Sabre\DAVACL\PrincipalBackend\LibM2(self::$authBackend);
    // Ajout du calendar backend dans le principal backend
    self::$principalBackend->setCalendarBackend(self::$calendarBackend);
  }
  /**
   * Initialisation des logs
   */
  private static function InitLogs() {
    // Initialisation des logs pour l'ORM
    $debuglog = function ($message) {
      \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[LibM2] $message");
    };
    $errorlog = function ($message) {
      \Lib\Log\Log::l(\Lib\Log\Log::ERROR, "[LibM2] $message");
    };
    \LibMelanie\Log\M2Log::InitDebugLog($debuglog);
    \LibMelanie\Log\M2Log::InitErrorLog($errorlog);
  }
  /**
   * Initialisation des plugins
   */
  private static function InitPlugins() {
    /* Server Plugins */
    self::$server->addPlugin(
        new Sabre\DAV\Auth\Plugin(self::$authBackend, 'SabreDAV')
    );
    self::$server->addPlugin(
        new Sabre\DAVACL\Plugin()
    );
    /* CalDAV support */
    self::$server->addPlugin(
        new Sabre\CalDAV\Plugin()
    );
    /* Log support */
    self::$server->addPlugin(
        new Lib\Log\Plugin()
    );
    /* Calendar scheduling support */
    self::$server->addPlugin(
        new Sabre\CalDAV\Schedule\PluginM2()
    );
    if (\Config\Config::enableWebDavSync) {
    	/* Calendar WebDAV-Sync */
    	self::$server->addPlugin(
    		new Sabre\DAV\Sync\Plugin()
    	);
    }            
    if (\Config\Config::useBrowser) {
      // Support for html frontend
      self::$server->addPlugin(
        new Sabre\DAV\Browser\Plugin()
      );
    }
  }
}

// Lancement du module CalDAV
CalDAV::Start();
