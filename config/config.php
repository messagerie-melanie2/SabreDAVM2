<?php
/**
 * Fichier de configuration de l'application SabreDAVM2
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
$config = [];

// If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
// You can override the baseUri here.
// @var string
$config['baseUri'] = '/caldav.php';

// This is a flag that allow or not showing file, line and code
// of the exception in the returned XML
// @var bool
$config['debugExceptions'] = false;