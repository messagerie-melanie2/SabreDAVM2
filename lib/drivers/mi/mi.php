<?php
/**
 * Driver specifique a la MCE pour SabreDAV MCE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class mi_driver extends driver {
  /**
   * Separator used for object shared
   */
  protected static $_objectSharedSeparator = ".-.";

  /**
   * Namespace for the objets
   */
  protected static $_objectsNS = "\\LibMelanie\\Api\\Mi\\";

  /**
   * Permet de convertir un username 
   * au moment du check de validation de l'authentification
   * A utiliser pour une authentification email qui doit donner un uid
   * 
   * @param string $username Username a convertir en entrée
   * 
   * @return string $convertUsername Username converti
   */
  public function convertUser($username) {
    // Convertir l'adresse e-mail en uid
    if (strpos($username, '@') !== false) {
      $user = \driver::new('User');
      $user->uid = $username;
      if ($user->load()) {
        $username = $user->uid;
      }
    }
    return $username;
  }
}
