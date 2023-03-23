<?php
/**
 * Fichier de gestion du backend Auth pour l'application SabreDAVM2
 * Utilise l'ORM M2 pour l'accès aux données Mélanie2
 *
 * SabreDAVM2 Copyright © 2022 MCE
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

namespace Sabre\DAV\Auth\Backend;

/**
 * This is an authentication backend based on ORM MCE
 */
interface LibM2AuthInterface extends BackendInterface {

  /**
   * Défini si on est dans le cas d'une method REPORT sans authentification (webdav sync)
   * @param boolean $noAuth
   * @return void
   */
  public function setNoAuthReportMethod($noAuth);
}