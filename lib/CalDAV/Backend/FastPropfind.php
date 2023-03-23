<?php
/**
 * PROPFIND optimisé pour les requêtes de type getctag (polling)
 * N'utilise pas d'authentification LDAP et un minimum de mémoire
 *
 * TODO: Utiliser l'ORM M2 pour le ctag pour la gestion du cache mémoire
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
//$temps_debut = microtime_float();
//declare(ticks = 1);
define ("MAX_ACQUIRE", 25);
define ("NB_ESSAI", 5);
define ("TEMPS_ATTENTE", 8000);

function microtime_float() {
    return array_sum(explode(' ', microtime()));
}
function Error404 ($info) {
  $ctag = 'ERROR_CTAG';
  $status = '404';
  $statusMessage = ' Not Found';
  error_log("$ctag $status $statusMessage $info");

  // Fermeture de la connexion
  if (isset($dbconn) && $dbconn)
    pg_close($dbconn);

  $xmldoc = '<?xml version="1.0" encoding="utf-8" ?><multistatus xmlns="DAV:" xmlns:C="http://calendarserver.org/ns/"><response><href>' . $_SERVER['REQUEST_URI'] . '</href><propstat><prop><C:getctag>"' . $ctag . '"</C:getctag></prop><status>HTTP/1.1 ' . $status . $statusMessage . '</status></propstat></response></multistatus>';

  @header( sprintf("HTTP/1.1 %d%s", $status, $statusMessage));
  @header( 'Content-type: text/xml; charset="utf-8"' );
  @header( "Content-Length: " . strlen($xmldoc) );

  echo $xmldoc;
  exit;
}
function selaforme_acquire($max = 50, $base = "/tmp/_SeLaFoRmE_") {
 for ($j=NB_ESSAI; $j>0; $j--) {
   for ($i=1; $i<=$max; $i++) {
    $fp = fopen($base.$i, "w+");
    if(flock($fp, LOCK_EX | LOCK_NB)) return $fp;
    fclose($fp);
   }
   usleep(TEMPS_ATTENTE);
 }
 return false;
}
function selaforme_release($fp) {
 flock($fp, LOCK_UN);
 fclose($fp);
}
// Recupere le semaphore associé a l'identifiant
// Bloque le semaphore si on depasse le MAX_ACQUIRE
$ret_sem = selaforme_acquire ( MAX_ACQUIRE );

// Si le semaphore n'est pas accessible
if ($ret_sem === false) {
  Error404 ('Les ressources sont deja utilisees');
}
$path = str_replace(trim(\Config\Config::baseUri, '/'), '', $server->httpRequest->getPath());
$args = explode('/', trim($path, '/'), 3);

if (count($args) < 3) {
  Error404 ('Erreur de paramètre : ' . $path);
}

$user_uid = $args[1];
$calendar_id = $args[2];

if (!preg_match('/^[0-9a-z\-\.]+$/', $calendar_id, $matches)) {
  Error404 ('Erreur de paramètre : ' . $path . ' / baseuri : ' . \Config\Config::baseUri);
}
$calendar = \driver::new('Calendar');
$calendar->id = $calendar_id;
$ctag = $calendar->getCTag();

if (empty($ctag)) {
	$ctag = md5($calendar_id);
}

$status = '200';
$statusMessage = ' OK';

if ($user_uid == $calendar_id && $user_uid == $_SERVER['PHP_AUTH_USER'])
{
  $taskslist = \driver::new('Taskslist');
  $taskslist->id = $calendar_id;
  $taskslist_ctag = $taskslist->getCTag();

  if (empty($taskslist_ctag)) {
  	$taskslist_ctag = md5($calendar_id);
  }

  $ctag = md5($ctag . $taskslist_ctag);
  $status = '200';
  $statusMessage = ' OK';
}

// Libere le semaphore
selaforme_release( $ret_sem );

$xmldoc = '<?xml version="1.0" encoding="utf-8" ?><multistatus xmlns="DAV:" xmlns:C="http://calendarserver.org/ns/"><response><href>' . $_SERVER['REQUEST_URI'] . '</href><propstat><prop><C:getctag>"' . $ctag . '"</C:getctag></prop><status>HTTP/1.1 ' . $status . $statusMessage . '</status></propstat></response></multistatus>';
/*$xmldoc = '<?xml version="1.0" encoding="utf-8" ?><multistatus xmlns="DAV:" xmlns:C="http://calendarserver.org/ns/"><response><href>' . $_SERVER['REQUEST_URI'] . '</href><propstat><prop><C:getctag>' . $ctag . '</C:getctag></prop><status>HTTP/1.1 ' . $status . $statusMessage . '</status></propstat></response></multistatus>';*/

@header( sprintf("HTTP/1.1 %d%s", $status, $statusMessage));
@header( 'Content-type: text/xml; charset="utf-8"' );
@header( "Content-Length: " . strlen($xmldoc) );

echo $xmldoc;
exit;

