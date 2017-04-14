<?php
/**
 * Fichier de gestion des logs pour l'application SabreDAV
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
 * Classe de configuration des logs
 *
 * @package Config
 */
class Log {
	/**
	 * Défini le niveau de log
	 * Liste des possibilités :
	 * ERROR, WARN, FATAL, INFO, DEBUG ou ALL
	 * OFF pour désactiver les logs
	 * La valeur est une chaine de caractère des diférents niveaux séparés par dés | et sans espace
	 * Par exemple:
	 *  public static $Level = "ERROR|WARN|FATAL|INFO";
	 * Possibilité de tout activer d'un coup:
	 *  public static $Level = "ALL";
	 * Ou bien de désactiver complétement les logs:
	 *  public static $Level = "OFF";
	 * Dés que OFF est présent, les logs sont désactivé, même si d'autres niveaux sont associés
	 */
	//public static $Level = "ERROR|WARN|FATAL|INFO|DEBUG";
	public static $Level = "ALL";
  //public static $Level = "ERROR|WARN|FATAL|INFO";
  /**
   * Configuration du chemin vers les fichiers de logs
   */
  public static $path_log = "/var/log/sabredav";
	/**
	 * Définition du fichier de logs
	 * Possibilité d'ajouter {date} pour ajouter la date au nom du fichier
	 * le format de la date est configuré en dessous
	 * Le fichier doit être dans un répertoire avec des droits d'écriture
	 */
	public static $file_log = "sabredav_{date}.log";
	/**
	 * Définition du fichier d'erreurs
	 * Possibilité d'ajouter {date} pour ajouter la date au nom du fichier
	 * le format de la date est configuré en dessous
	 * Le fichier doit être dans un répertoire avec des droits d'écriture
	 */
	public static $file_errors_log = "sabredav_errors_{date}.log";
	/**
	 * Définition du format de date a ajouter éventuellement au nom du fichier
	 */
	public static $date_format = "Ymd";
  /**
   * Liste des utilisateurs pour lesquels les logs seront en debug dans un fichier particulier
   */
	public static $users_debug = [];
}