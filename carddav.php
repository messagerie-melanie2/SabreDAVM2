<?php
/**
 * Serveur CardDAV pour l'application SabreDAVM2
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
require_once 'lib/includes/includes_carddav.php';

/**
 * Classe de serveur CardDAV via SabreDAV
 * Implémente et démarre les plugins et backends ORM M2 et Mélanie2
 *
 * @author Thomas Payen
 * @author PNE Annuaire et Message/MEDDE
 *
 */
class CardDAV {
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
	 * @var Sabre\CardDAV\Backend\LibM2
	 */
	private static $contactBackend;
	/**
	 * Backend principal
	 * @var Sabre\DAVACL\PrincipalBackend\LibM2
	 */
	private static $principalBackend;
	/**
	 * Démarrage des différents modules du serveur CardDAV
	 * @throws ErrorException
	 */
	public static function Start() {
		//Mapping PHP errors to exceptions
		function exception_error_handler($errno, $errstr, $errfile, $errline ) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}
		set_error_handler("exception_error_handler");
	
		// Initialisation des backends
		self::InitBackends();
	
		// Directory structure
		$nodes = [
				new Sabre\DAVACL\PrincipalCollection(self::$principalBackend),
				new Sabre\CardDAV\AddressBookRoot(self::$principalBackend, self::$contactBackend),
		];
		// Création du serveur CalDAV
		self::$server = new Sabre\DAV\Server($nodes);
		// Configuration de la baseUri
		self::$server->setBaseUri(\Config\Config::carddavBaseUri);
		// Configuration du mode debug pour le serveur
		self::$server->debugExceptions = \Config\Config::debugExceptions;
		// Définition du serveur dans le backend M2
		self::$contactBackend->setServer(self::$server);
		self::$principalBackend->setServer(self::$server);
		// Initialisation des logs
		self::InitLogs();
	
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
		self::$contactBackend = new Sabre\CardDAV\Backend\LibM2(self::$authBackend);
		self::$principalBackend = new Sabre\DAVACL\PrincipalBackend\LibM2(self::$authBackend);
		// Ajout du calendar backend dans le principal backend
		self::$principalBackend->setContactBackend(self::$contactBackend);
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
		/* CardDAV support */
		self::$server->addPlugin(
				new Sabre\CardDAV\Plugin()
				);
		/* Log support */
		self::$server->addPlugin(
				new Lib\Log\Plugin()
				);
		if (\Config\Config::useBrowser) {
			// Support for html frontend
			self::$server->addPlugin(
					new Sabre\DAV\Browser\Plugin()
			);
		}
	}
}

// Lancement du module CardDAV
CardDAV::Start();