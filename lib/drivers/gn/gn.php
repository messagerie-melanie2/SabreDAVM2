<?php
/**
 * Plugin Mél
 *
 * Driver specifique a la MCE pour le plugin mel
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

class gn_driver extends driver {
  /**
   * Separator used for object shared
   */
  protected static $_objectSharedSeparator = ".-.";

  /**
   * Namespace for the objets
   */
  protected static $_objectsNS = "\\LibMelanie\\Api\\Gn\\";

  /**
   * Remplace la dernière occurence d'un motif
   * 
   * @param $search
   * @param $replace
   * @param $subject
   * 
   * @return string|string[]
   */
  private function str_lreplace($search, $replace, $subject) {
    $pos = strrpos($subject, $search);

    if($pos !== false) {
      $subject = substr_replace($subject, $replace, $pos, strlen($search));
    }

    return $subject;
  }

  /**
   * alexandre.-.balp%police%gendarmerie => alexandre.-.balp%police@gendarmerie
   * sylvain%gendarmerie => sylvain@gendarmerie
   * 
   * @param $mail
   * 
   * @return string
   */
  private function fixMail($mail) {
    return strpos($mail, '@') !== false ? $mail : $this->str_lreplace('%', '@', $mail);
  }
  
  /**
   * Retourne le username et le balpname à partir d'un username complet
   * balpname sera null si username n'est pas un objet de partage
   * username sera nettoyé de la boite partagée si username est un objet de partage
   *
   * @param string $username Username à traiter peut être un objet de partage ou non
   * @return array($username, $balpname) $username traité, $balpname si objet de partage ou null sinon
   */
  public function getBalpnameFromUsername($username) {

    // On peut recevoir un radical principals/ depuis sabredav
    $principals = "";
    if (substr( $username, 0, 11 ) === "principals/") {
      $principals = "principals/";
      $username = substr($username, 11);
    }

    $original_username = $username;
    // on peut recevoir un mailroutingaddress
    // sylvain.-.stc.bmpn.stsisi%gendarmerie.interieur.gouv.fr@organique.gendarmerie.fr
    // alexandre.-.sdac.stsisi%police.interieur.gouv.fr%gendarmerie.interieur.gouv.fr@organique.gendarmerie.fr
    // alexandre.-.sdac.stsisi%police.interieur.gouv.fr@gendarmerie.interieur.gouv.fr
    if (strpos($username, '%') !== false) {
      $inf = explode('@', $username);
      if (isset($inf[1]) && !in_array($inf[1], array("gendarmerie.interieur.gouv.fr", "police.interieur.gouv.fr", "interieur.gouv.fr")) ) {
        # si on finit par un @fqdn qui n'est pas un domaine mail et on a un % => on est sur un mailroutingaddress => on vire le serveur
        $username = $inf[0];
      }
      // s'il n'y a pas d'@ ou bien 2 % il faut corriger
      if (substr_count($username, '%') === 2 || strpos($username, '@') === false) {
        $username = $this->fixMail($username);
      }
    }

    // on a maintenant
    // sylvain.-.stc.bmpn.stsisi@gendarmerie.interieur.gouv.fr
    // alexandre.-.sdac.stsisi%police.interieur.gouv.fr@gendarmerie.interieur.gouv.fr
    // alexandre.-.sdac.stsisi%police.interieur.gouv.fr@gendarmerie.interieur.gouv.fr
    $balregexp = defined('\Config\Config::emailRegexp') ? \Config\Config::emailRegexp : false;

    if (!$balregexp || !preg_match($balregexp, $username, $m)) {
      return array($username, null);
    }

    # alexandre.-.sdac%interieur.gouv.fr@gendarmerie.interieur.gouv.fr
    # radical = alexandre.-.sdac%interieur.gouv.fr
    # uid     = alexandre
    # balp    = sdac
    # domaineuser = interieur.gouv.fr
    # domaine = gendarmerie.interieur.gouv.fr

    if (!$m["domaineuser"]) {
      $m["domaineuser"] = $m["domaine"];
    }

    $user = $m["uid"] .'@'. $m["domaineuser"];
    $balpname = $m["balp"] ? $m["balp"] .'@'. $m["domaine"] : null;

    if (isset($balpname)) {
      $balpname = "$principals$balpname";
    }

    return array("$principals$user", $balpname);
  }
}
