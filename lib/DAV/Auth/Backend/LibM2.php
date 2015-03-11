<?php
/**
 * Fichier de gestion du backend Auth pour l'application SabreDAVM2
 * Utilise l'ORM M2 pour l'accès aux données Mélanie2
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
namespace Sabre\DAV\Auth\Backend;

/**
 * This is an authentication backend that uses a database to manage passwords.
 *
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBasic {

    /**
     * Creates the backend object.
     *
     * If the filename argument is passed in, it will parse out the specified file fist.
     */
    function __construct() {
    }

    /**
     * Returns the digest hash for a user.
     *
     * @param string $realm
     * @param string $username
     * @return string|null
     */
    function getDigestHash($realm,$username) {

        $stmt = $this->pdo->prepare('SELECT digesta1 FROM '.$this->tableName.' WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetchColumn() ?: null;

    }

    /**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    protected function validateUserPass($username, $password) {
      return \LibMelanie\Ldap\Ldap::Authentification($username, $password);
    }

}
