<?php
/**
 * PROPFIND optimisé pour les requêtes de type getctag (polling)
 * N'utilise pas d'authentification LDAP et un minimum de mémoire
 *
 * TODO: Utiliser l'ORM M2 pour le ctag pour la gestion du cache mémoire
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
require_once 'includes/includes_conf.php';

use LibMelanie\Config\ConfigSQL;

$_conf = ConfigSQL::$SERVERS[ConfigSQL::$CURRENT_BACKEND];

//$temps_debut = microtime_float();
//declare(ticks = 1);
define ("MAX_ACQUIRE", 25);
define ("NB_ESSAI", 5);
define ("TEMPS_ATTENTE", 8000);

function microtime_float() {
    return array_sum(explode(' ', microtime()));
}

function Error404 ($info) {
  //@dbg_error_log('LOG', 'PROPFIND: fastPropFind: ERROR 404 - '.$_SERVER['PATH_INFO'].' - '.$_SERVER['PHP_AUTH_USER'].' - '.$info);
  $ctag = 'ERROR_CTAG';
  $status = '404';
  $statusMessage = ' Not Found';
  error_log("$ctag $status $statusMessage $info");

  // Fermeture de la connexion
  if (isset($dbconn) && $dbconn)
    pg_close($dbconn);

  $xmldoc = '<?xml version="1.0" encoding="utf-8" ?><multistatus xmlns="DAV:" xmlns:C="http://calendarserver.org/ns/"><response><href>' . $_SERVER['REQUEST_URI'] . '</href><propstat><prop><C:getctag>"' . $ctag . '"</C:getctag></prop><status>HTTP/1.1 ' . $status . $statusMessage . '</status></propstat></response></multistatus>';

  //@header ('X-Powered-By: PHP/5.2.6-1+lenny9');
  //@header ('Server: 0.9');
  //@header ('DAV: 1, 2, access-control, calendar-access, calendar-schedule, extended-mkcol, calendar-proxy');
  //$etag = md5($xmldoc);
  //@header("ETag: \"$etag\"");
  @header( sprintf("HTTP/1.1 %d%s", $status, $statusMessage));
  //@header( 'X-DAViCal-Version: DAViCal/0.9.8' );
  @header( 'Content-type: text/xml; charset="utf-8"' );
  @header( "Content-Length: " . strlen($xmldoc) );

  echo $xmldoc;
  exit;
}

function selaforme_acquire($max = 50, $base = "/tmp/_SeLaFoRmE_")
{
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

function selaforme_release($fp)
{
 flock($fp, LOCK_UN);
 fclose($fp);
}

// Recupere l'identifiant d'un semaphore
//$sem_identifier = sem_get (1, MAX_ACQUIRE);

// Recupere le semaphore associé a l'identifiant
// Bloque le semaphore si on depasse le MAX_ACQUIRE
//$ret_sem = sem_acquire ( $sem_identifier );
$ret_sem = selaforme_acquire ( MAX_ACQUIRE );
//@dbg_error_log('LOG', 'PROPFIND: fastPropFind: ret_sem: '.$ret_sem);

// Si le semaphore n'est pas accessible
if ($ret_sem === false) {
  Error404 ('Les ressources sont deja utilisees');
}

$connectString = "host=".$_conf['hostspec']." dbname=".$_conf['database']." user=".$_conf['username']." password=".$_conf['password']." connect_timeout=1";
//$dbconn = pg_pconnect($connectString);
$dbconn = pg_connect($connectString);

// Test si la connexion reussie
if (!$dbconn) {
  // Si la connexion echoue, erreur 404
  Error404 ('Connection failed');
}

// Test si la connexion est accessible
$stat = pg_connection_status($dbconn);
if ($stat !== PGSQL_CONNECTION_OK) {
  //@dbg_error_log('LOG', 'PROPFIND: fastPropFind: PG_CONNECT STATUS NOK, Connection reset');
  // Si la connexion est inaccessible on fait un reset
  $dbconn = pg_connection_reset($dbconn);
  if ($dbconn) {
    // Le reset fonctionne on continue
    //@dbg_error_log('LOG', 'PROPFIND: fastPropFind: Reset OK');
  } else {
    // Le reset a echoué Erreur 404
    Error404 ('Reset failed');
  }
}

// Test si la connexion est disponible
$bs = pg_connection_busy($dbconn);
if ($bs) {
  //@dbg_error_log('LOG', 'PROPFIND: fastPropFind: ERROR 404 PG_CONNECT RESOURCE BUSY');
  Error404 ('RESOURCE BUSY');
}

// @dbg_error_log('LOG', 'PROPFIND: fastPropFind');
$args = explode('/', trim($_SERVER['SCRIPT_URL'], '/'), 3);

if (count($args) < 3) {
  Error404 ('Erreur de paramètre');
}

$user_uid = $args[1];
$calendar_id = $args[2];

if (!preg_match('/^[0-9a-z\-\.]+$/', $calendar_id, $matches)) {
  Error404 ('Erreur de paramètre');
}

//$query = "SELECT md5(max(event_modified) + count(*)) as dav_ctag FROM kronolith_events WHERE calendar_id = '".$calendar_id."'";
$query = "SELECT cast(sum(event_modified) as varchar) as dav_ctag FROM kronolith_events WHERE calendar_id = '".$calendar_id."'";
//@dbg_error_log('LOG', 'Requete: ' . $query);

$result = pg_query($dbconn, $query);
if (!$result) {
  //sem_release ( $sem_identifier );
  selaforme_release( $ret_sem );
  Error404 ('Erreur dans la requete');
}

if ($row = pg_fetch_row($result)) {
  $ctag = md5($row[0]);
  $status = '200';
  $statusMessage = ' OK';
  //@dbg_error_log('LOG', 'dav_ctag: ' . $ctag);
}
else {
  //sem_release ( $sem_identifier );
  selaforme_release( $ret_sem );
  Error404 ('Not Found');
}

//@dbg_error_log('LOG', '$_SERVER[PHP_AUTH_USER]: ' . $_SERVER['PHP_AUTH_USER']);
if ($user_uid == $calendar_id && $user_uid == $_SERVER['PHP_AUTH_USER'])
{
  $query = "SELECT md5(cast(sum(task_ts) as varchar)) as dav_ctag FROM nag_tasks WHERE task_owner = '".$calendar_id."'";
  //@dbg_error_log('LOG', 'Requete: ' . $query);

  $result = pg_query($dbconn, $query);
  if (!$result) {
    //sem_release ( $sem_identifier );
    selaforme_release( $ret_sem );
    Error404 ('Erreur dans la requete');
  }

  if ($row = pg_fetch_row($result)) {
    $ctag = md5($ctag . $row[0]);
    $status = '200';
    $statusMessage = ' OK';
    //@dbg_error_log('LOG', 'dav_ctag: ' . $ctag);
  }
  else {
    //sem_release ( $sem_identifier );
    selaforme_release( $ret_sem );
    Error404 ('Not Found');
  }
}

// Fermeture de la connexion
//pg_close($dbconn);

// Libere le semaphore
//sem_release ( $sem_identifier );
selaforme_release( $ret_sem );

$xmldoc = '<?xml version="1.0" encoding="utf-8" ?><multistatus xmlns="DAV:" xmlns:C="http://calendarserver.org/ns/"><response><href>' . $_SERVER['REQUEST_URI'] . '</href><propstat><prop><C:getctag>"' . $ctag . '"</C:getctag></prop><status>HTTP/1.1 ' . $status . $statusMessage . '</status></propstat></response></multistatus>';

//@header ('X-Powered-By: PHP/5.2.6-1+lenny9');
//@header ('Server: 0.9');
//@header ('DAV: 1, 2, access-control, calendar-access, calendar-schedule, extended-mkcol, calendar-proxy');
//$etag = md5($xmldoc);
//@header("ETag: \"$etag\"");
@header( sprintf("HTTP/1.1 %d%s", $status, $statusMessage));
//@header( 'X-DAViCal-Version: DAViCal/0.9.8' );
@header( 'Content-type: text/xml; charset="utf-8"' );
@header( "Content-Length: " . strlen($xmldoc) );

echo $xmldoc;

//$temps_fin = microtime_float();
//@dbg_error_log('LOG', 'PROPFIND: fastPropFind: Temps d\'execution du script : '.round($temps_fin - $temps_debut, 4));
exit;

