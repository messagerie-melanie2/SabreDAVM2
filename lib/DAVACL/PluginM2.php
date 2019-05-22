<?php
/**
 * CalDAV/PluginM2 pour surcharger le plugin DAVACL de SabreDAV
 *
 * SabreDAVM2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\DAVACL;

/**
 * SabreDAV ACL Plugin
 *
 * This plugin provides functionality to enforce ACL permissions.
 * ACL is defined in RFC3744.
 *
 * In addition it also provides support for the {DAV:}current-user-principal
 * property, defined in RFC5397 and the {DAV:}expand-property report, as
 * defined in RFC3253.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class PluginM2 extends Plugin {
  /**
   * The ACL plugin allows privileges to be assigned to users that are not
   * logged in. To facilitate that, it modifies the auth plugin's behavior
   * to only require login when a privileged operation was denied.
   *
   * Unauthenticated access can be considered a security concern, so it's
   * possible to turn this feature off to harden the server's security.
   *
   * @var bool
   */
  // PAMELA - Ne pas authoriser les requêtes non authentifiés
  public $allowUnauthenticatedAccess = false;
}