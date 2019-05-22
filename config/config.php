<?php
/**
 * Fichier de configuration de l'application SabreDAVM2
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
namespace Config;

/**
 * Classe de configuration de l'applications
 *
 * @package Config
 */
class Config {
  /**
   * If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
   * You can override the baseUri here.
   * @var string
   */
  const baseUri = '/caldav.php';
  /**
   * If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
   * You can override the baseUri here.
   * For CalDAV server
   * @var string
   */
  const caldavBaseUri = '/caldav.php';  
  /**
   * If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
   * You can override the baseUri here.
   * For CardDAV server
   * @var string
   */
  const carddavBaseUri = '/carddav.php';
  /**
   * This is a flag that allow or not showing file, line and code
   * of the exception in the returned XML
   * @var boolean
   */
  const debugExceptions = true;

  /**
   * Implement the web browser in SabreDAV
   * Can be usefull in assistance
   * @var boolean
   */
  const useBrowser = true;
  
  /**
   * Use WebDav Sync to CalDAV synchronisation
   * @var boolean
   */
  const enableWebDavSync = true;
  /**
   * Date limite maximum pour l'ancienneté des évènements retournés
   * @var string
   */
  const DATE_MAX = "-18 months"; 
  /**
   * Ajouter au Sync Token pour retourner plus d'enregistrement
   * @var integer
   */
  const addToSyncToken = 0;
  /**
   * Liste des URL bloquées en fonction des utilisateurs
   * Ex :  [
   *         'utilisateur1' => ['url1', 'url2'],
   *         'utilisateur2' => ['url3'],
   *       ]
   * @var array
   */
  static $blockedUrl = null;
}