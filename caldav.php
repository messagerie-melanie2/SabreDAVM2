<?php
/**
 * Serveur CalDAV pour l'application SabreDAVM2
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

// Configuration du nom de l'application pour l'ORM
if (!defined('CONFIGURATION_APP_LIBM2')) {
  define('CONFIGURATION_APP_LIBM2', 'sabredav');
}

// Inclusion de l'ORM
require_once 'includes/libm2.php';
// Inclusion de la configuration de l'application
require_once 'config/config.php';

// settings
date_default_timezone_set(\LibMelanie\Config\ConfigMelanie::CALENDAR_DEFAULT_TIMEZONE);

//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");

// Initialisation des logs pour l'ORM
$log = function ($message) {
    error_log("[LibM2] $message");
};
\LibMelanie\Log\M2Log::InitDebugLog($log);
\LibMelanie\Log\M2Log::InitErrorLog($log);

// Files we need
require_once 'vendor/autoload.php';

// Require LibM2 files
require_once 'lib/DAV/Auth/Backend/LibM2.php';
require_once 'lib/CalDAV/Melanie2Support.php';
require_once 'lib/CalDAV/Backend/LibM2.php';
require_once 'lib/CalDAV/PluginM2.php';
require_once 'lib/CalDAV/CalendarRootM2.php';
require_once 'lib/CalDAV/CalendarHomeM2.php';
require_once 'lib/CalDAV/Schedule/PluginM2.php';
require_once 'lib/DAVACL/PrincipalBackend/LibM2.php';

// Backends
$authBackend = new Sabre\DAV\Auth\Backend\LibM2();
$calendarBackend = new Sabre\CalDAV\Backend\LibM2($authBackend);
$principalBackend = new Sabre\DAVACL\PrincipalBackend\LibM2($authBackend);

// Directory structure
$tree = [
  new Sabre\CalDAV\Principal\Collection($principalBackend),
  new Sabre\CalDAV\CalendarRootM2($principalBackend, $calendarBackend),
];

$server = new Sabre\DAV\Server($tree);

// PAMELA 10/09/10 Traitement des PROPFIND
// optimiser les PROPFIND qui ne demandent que le getctag
if ( $server->httpRequest->getMethod() == 'PROPFIND' )
{
  $v = $server->httpRequest->getBodyAsString();
  // XXX: Erreur si on récupère le body et que ce n'est pas du FastPropfind
  // Surement lié à la ressource
  $server->httpRequest->setBody($v);
  if (strpos($v, "<D:prop><CS:getctag/></D:prop>") !== false) {
    require 'lib/CalDAV/Backend/FastPropfind.php';
    exit;
  }
}

if (isset($config['baseUri']))
    $server->setBaseUri($config['baseUri']);

/* Server Plugins */
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, 'SabreDAV');
$server->addPlugin($authPlugin);

$aclPlugin = new Sabre\DAVACL\Plugin();
$server->addPlugin($aclPlugin);

/* CalDAV support */
$caldavPlugin = new Sabre\CalDAV\PluginM2();
$server->addPlugin($caldavPlugin);

/* Calendar scheduling support */
$server->addPlugin(
        new Sabre\CalDAV\Schedule\PluginM2()
);

// Support for html frontend
$browser = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

$server->debugExceptions = $config['debugExceptions'];

// And off we go!
$server->exec();
