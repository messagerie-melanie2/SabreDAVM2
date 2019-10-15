<?php
/**
 * Fichier d'inclusion pour le serveur SabreDAV CalDAV
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

// Files we need
require_once 'vendor/autoload.php';

// Require LibM2 files
require_once 'lib/log/log.php';
require_once 'lib/log/logging.php';
require_once 'lib/log/Plugin.php';
require_once 'lib/DAV/ServerM2.php';
require_once 'lib/DAV/Auth/Backend/LibM2.php';
require_once 'lib/CardDAV/Melanie2Support.php';
require_once 'lib/CardDAV/Backend/LibM2.php';
require_once 'lib/DAVACL/PrincipalBackend/LibM2.php';
require_once 'lib/DAVACL/PluginM2.php';