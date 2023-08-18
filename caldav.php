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
require_once 'lib/includes/includes_caldav.php';

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
   * @var Sabre\DAV\Auth\Backend\LibM2AuthInterface
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

    // Initialisation des backends
    self::InitBackends();

    // Directory structure
    $tree = [
      new Sabre\CalDAV\Principal\Collection(self::$principalBackend),
      new Sabre\CalDAV\CalendarRootM2(self::$principalBackend, self::$calendarBackend),
    ];
    // Création du serveur CalDAV
    self::$server = new Sabre\DAV\ServerM2($tree);
    // Configuration de la baseUri
    self::$server->setBaseUri(\Config\Config::caldavBaseUri);
    // Configuration du mode debug pour le serveur
    self::$server->debugExceptions = \Config\Config::debugExceptions;
    // Définition du serveur dans le backend M2
    self::$calendarBackend->setServer(self::$server);
    self::$principalBackend->setServer(self::$server);    
    // Initialisation des logs
    self::InitLogs();
    // Initialisation du shutdown
    register_shutdown_function(array('CalDAV', 'InitShutdown'));
    
    // MANTIS 0005077: Mettre en place un mecanisme de blocage des URL par utilisateur
    \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[GetURL] " . self::$server->httpRequest->getUrl());
    if (isset(Config\Config::$blockedUrl)
        && isset($_SERVER['PHP_AUTH_USER'])) {
      $currentUser = $_SERVER['PHP_AUTH_USER'];
      if (isset(Config\Config::$blockedUrl[$currentUser])) {
        if (is_array(Config\Config::$blockedUrl[$currentUser])
            && in_array(self::$server->httpRequest->getUrl(), Config\Config::$blockedUrl[$currentUser])
            || Config\Config::$blockedUrl[$currentUser] == self::$server->httpRequest->getUrl()) {
          \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "[Blocked URL] " . self::$server->httpRequest->getUrl());
          return;
        }
      }
    }
    
    // PAMELA 10/09/10 Traitement des PROPFIND
    // optimiser les PROPFIND qui ne demandent que le getctag
    if ( self::$server->httpRequest->getMethod() == 'PROPFIND' || self::$server->httpRequest->getMethod() == 'REPORT') {
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
      		&& strpos($v, '</sync-level><prop><getcontenttype/><getetag/></prop></sync-collection>') !== false) {
      	self::$authBackend->setNoAuthReportMethod(true);
      	self::$principalBackend->setNoPrincipalSearch(true);
      	self::$calendarBackend->setIsSync(true);
      }
    }
    // Initialisation des plugins
    self::InitPlugins();    
    // Initialisation des événements serveur
    self::InitEvents();
    
    // Démarrage du serveur
    self::$server->exec();
  }

  /**
   * Initialisation des backends
   */
  private static function InitBackends() {
    // Récupération du nom de backend depuis la configuration
    $authPlugin = defined('\Config\Config::authPlugin') ? \Config\Config::authPlugin : 'Sabre\DAV\Auth\Backend\LibM2';
    self::$authBackend = new $authPlugin();

    // Définition des backends Mélanie2
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
    $infolog = function ($message) {
      \Lib\Log\Log::l(\Lib\Log\Log::INFO, "[LibM2] $message");
    };
    \LibMelanie\Log\M2Log::InitDebugLog($debuglog);
    \LibMelanie\Log\M2Log::InitErrorLog($errorlog);
    \LibMelanie\Log\M2Log::InitInfoLog($infolog);

    // Gestion des exceptions
    /**
     * Ecrit l'exception dans les logs
     * 
     * @param Exception $excpetion
     */
    function exception_handler($exception) {
      \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "[Exception] " . $exception);
    }
    set_exception_handler('exception_handler');
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
        //new Sabre\DAVACL\Plugin()
        new Sabre\DAVACL\PluginM2()
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

  /**
   * Gestion des Events server
   * Pour logger les exceptions notamment
   */
  private static function InitEvents() {
    // MANTIS 0004703: Log des exceptions SabreDAV
    self::$server->on('exception', function($ex) {
      if (strpos($ex->getMessage(), 'Authorization: Basic') === false) {
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "Exception: " . $ex->getMessage());
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "File: " . $ex->getFile());
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "Line: " . $ex->getLine());
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "Code: " . $ex->getCode());
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "Trace: " . $ex->getTraceAsString());
      }      
    });
  }
  
  /**
   * Initialisation du shutdown pour logger les erreurs
   */
  public static function InitShutdown() {
    $last_error = error_get_last();
    if (isset($last_error)) {
      $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '-';
      $req = $_SERVER['REQUEST_METHOD'];
      $error = "";
      if (isset($last_error['type']) && ($last_error['type'] === E_ERROR)) {
        if (isset($last_error['message'])) {
          $error .= ' Message:"' . $last_error['message'] . '"';
        }
        if (isset($last_error['file'])) {
          $error .= ' File:"' . $last_error['file'] . '"';
        }
        if (isset($last_error['line'])) {
          $error .= ' Line:' . $last_error['line'];
        }
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "$req $path [Shutdown] Error: $error");
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "$req $path [Shutdown] Last SQL Request: " . var_export(\LibMelanie\Sql\Sql::getLastRequest(), true));
        \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "$req $path [Shutdown] Last LDAP Request: " . \LibMelanie\Ldap\Ldap::getLastRequest());
        // Cas du process bloqué après un "Maximum execution time" on lance un posix kill
        if (strpos($last_error['message'], "Maximum execution time") === 0) {
          \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "$req $path [Shutdown] XML Request: " . self::$server->httpRequest->getBodyAsString());
          self::kill_on_exit();
        }
      }
    }
  }
  
  /**
   * Terminate Apache 2 child process after request has been
   * done by sending a SIGTERM  POSIX signal (15).
   */
  private static function kill_on_exit() {
    $pid = getmypid();
    $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '-';
    $req = $_SERVER['REQUEST_METHOD'];
    \Lib\Log\Log::l(\Lib\Log\Log::FATAL, "$req $path [Shutdown] kill_on_exit() SIGTERM");
    posix_kill($pid, SIGTERM);
  } 
}

// Lancement du module CalDAV
CalDAV::Start();
