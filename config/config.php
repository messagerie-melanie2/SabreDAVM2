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
  const baseUri = '/davm2/caldav.php';
  /**
   * If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
   * You can override the baseUri here.
   * For CalDAV server
   * @var string
   */
  const caldavBaseUri = '/davm2/caldav.php';
  /**
   * If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
   * You can override the baseUri here.
   * For CardDAV server
   * @var string
   */
  const carddavBaseUri = '/davm2/carddav.php';
  /**
   * Configuration du nom de driver
   * 
   * Valeurs possibles : 'mte', 'mce', 'gn', 'dgfip', 'mi', 'ens'
   */
  const driver = 'mce';
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
   * Permettre un nettoyage du cache régulier en chargeant plus de SyncToken
   * que nécessaite
   * 
   * Syntaxe de configuration :
   *  [ frequence => tokens ]
   * 
   * Exemple, pour recharger 100 tokens toutes les 20 synchros 
   *  et 500 tokens toutes les 100 synchros :
   *  [ 
   *    20  => 100,
   *    100 => 500,
   *  ]
   * @var array ou null
   */
  const addSyncTokenToCleanCache = null;
  /**
   * Plugin d'authentification
   *   'Sabre\DAV\Auth\Backend\LibM2' : Login/password
   *   'Sabre\DAV\Auth\Backend\LibM2Krb' : Kerberos
   */
  const authPlugin = 'Sabre\DAV\Auth\Backend\LibM2';

  /**
   * Filtre LDAP pour la recherche d'un utilisateur kerberos
   * (utilisé avec le backend LibM2Krb)
   */
  const krbldapfilter = '';

  /**
   * Liste des URL bloquées en fonction des utilisateurs
   * Ex :  [
   *         'utilisateur1' => ['url1', 'url2'],
   *         'utilisateur2' => ['url3'],
   *       ]
   * @var array
   */
  static $blockedUrl = null;

  /**
   * Configuration du plugin IMipPlugin
   */
  // si true plugin actif, sinon false
  const plugin_imip_enable = false;

  // si true génère un message au format texte + calendar (ics)
  const plugin_imip_ics_texte = false;

  // si true, encode les entetes
  const plugin_imip_encode = false;

  // le nom du fichier de langue à inclure
  const plugin_imip_langues = 'IMipPlugin_fr.php';
}