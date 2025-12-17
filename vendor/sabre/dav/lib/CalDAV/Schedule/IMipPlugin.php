<?php

namespace Sabre\CalDAV\Schedule;

use Sabre\DAV;
use Sabre\VObject\ITip;
use Sabre\VObject\Property;


define("SAUT_LIGNE", "\r\n");

// inclure le fichier de langue
include_once(__DIR__ .'/'. \Config\Config::plugin_imip_langues);


/**
 * iMIP handler.
 *
 * This class is responsible for sending out iMIP messages. iMIP is the
 * email-based transport for iTIP. iTIP deals with scheduling operations for
 * iCalendar objects.
 *
 * If you want to customize the email that gets sent out, you can do so by
 * extending this class and overriding the sendMessage method.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class IMipPlugin extends DAV\ServerPlugin {

    /**
     * ITipMessage
     *
     * @var ITip\Message
     */
    protected $itipMessage;

    protected $serveur;

    // accès aux constantes de la classe IMipPluginLangue
    protected $libellesImip='IMipPluginLangue';


    /*
     * This initializes the plugin.
     *
     * This function is called by Sabre\DAV\Server, after
     * addPlugin is called.
     *
     * This method should set up the required event subscriptions.
     *
     * @param DAV\Server $server
     * @return void
     */
    function initialize(DAV\Server $server) {

        $server->on('schedule', [$this, 'schedule'], 120);

        $this->serveur=$server;
    }

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    function getPluginName() {

        return 'imip';

    }

    /**
     * Event handler for the 'schedule' event.
     *
     * @param ITip\Message $iTipMessage
     * @return void
     */
    function schedule(ITip\Message $iTipMessage) {

        // pas d'envoi si événement produit par le courrielleur
        if (strpos($this->serveur->httpRequest->getHeader('User-Agent'), 'Thunderbird') !== false) return;

        // Not sending any emails if the system considers the update
        // insignificant.
        if (!$iTipMessage->significantChange) {
            if (!$iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
            }
            return;
        }

        $summary = $iTipMessage->message->VEVENT->SUMMARY;

        if (parse_url($iTipMessage->sender, PHP_URL_SCHEME) !== 'mailto')
            return;

        if (parse_url($iTipMessage->recipient, PHP_URL_SCHEME) !== 'mailto')
            return;

        $sender = substr($iTipMessage->sender, 7);
        $returnpath = $sender;
        $recipient = substr($iTipMessage->recipient, 7);

        if ($iTipMessage->senderName) {
            $sender = $iTipMessage->senderName . ' <' . $sender . '>';
        }
        if ($iTipMessage->recipientName) {
            $recipient = $iTipMessage->recipientName . ' <' . $recipient . '>';
        }

        $subject = 'SabreDAV iTIP message';
        switch (strtoupper($iTipMessage->method)) {
            case 'REPLY' :
                $subject = $this->libellesImip::reponse_invitation . $summary;
                break;
            case 'REQUEST' :
                $seq=$iTipMessage->sequence;
                if ($seq && $seq > 0)
                  $subject = $this->libellesImip::mise_a_jour_invitation . $summary;
                else
                  $subject = $this->libellesImip::invitation . $summary;
                break;
            case 'CANCEL' :
                $subject = $this->libellesImip::evenement_annule . $summary;
                break;
        }

        $subject = '=?UTF-8?B?'.base64_encode($subject).'?=';

        if (\Config\Config::plugin_imip_encode) {

            $headers = array(
                'From' => '=?UTF-8?B?'.base64_encode($sender).'?=',
                'Reply-To' => '=?UTF-8?B?'.base64_encode($sender).'?=',
            );

        } else {

            $headers = array(
                'From' => $sender,
                'Reply-To' => $sender,
            );
        }

        $headers['Content-Type']='text/calendar; charset=UTF-8; method='.$iTipMessage->method;

        if (DAV\Server::$exposeVersion) {
            $headers['X-Sabre-Version']=DAV\Version::VERSION;
        }

        $message="";

        if (\Config\Config::plugin_imip_ics_texte) {

            $boundary="-----=".md5(uniqid(rand()));

            $message=$this->messageInvitation($iTipMessage, $boundary);

            $headers['MIME-Version']='1.0';
            $headers['Content-Type']='multipart/alternative; boundary="'.$boundary.'"';
        }
        else{

            $message=$iTipMessage->message->serialize();
        }

        // envoi du message
        $this->mail($recipient,
                    $subject,
                    $message,
                    $headers,
                    "-f $returnpath"
                    );

        $iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip';
    }


    /*
      Construit le message d'invitation (multipart)

      Paramètre : $iTipMessage

      retourne le message formaté pour envoi avec mail (body)
    */
    function messageInvitation(ITip\Message $iTipMessage, $boundary) {

      // partie description de l'événement
      $msg=$this->formateMessage($iTipMessage);

      // partie attachement (ics)
      $ics=$this->formateAttachement($iTipMessage);

      // construction du message d'invitation
      $message='Ce message est au format MIME 1.0 multipart/mixed.'.SAUT_LIGNE;

      // 1ere partie
      $message.='--'.$boundary.SAUT_LIGNE;
      $message.='Content-type: text/plain; charset=UTF-8'.SAUT_LIGNE;
      $message.='Content-transfer-encoding: 8BIT'.SAUT_LIGNE.SAUT_LIGNE;
      $message.=$msg.SAUT_LIGNE;

      // 2ème partie
      $message.='--'.$boundary.SAUT_LIGNE;
      $message.='Content-Transfer-Encoding: 8bit'.SAUT_LIGNE;
      $message.='Content-Type: text/calendar; charset=UTF-8; method=REQUEST;'.SAUT_LIGNE;
      $message.=' name=event.ics'.SAUT_LIGNE.SAUT_LIGNE;
      $message.=$ics.SAUT_LIGNE;

      return $message;
    }


    /* contruit la partie du message d'invitation (description de l'événement
      retourne une chaine pour ajout dans le message (partie 1)
    */
    protected function formateMessage(ITip\Message $iTipMessage){

      if (!isset($iTipMessage->message->VEVENT)) return "";

      $event;
      $vtimezone=null;

      $event=$iTipMessage->message->VEVENT;
      if (!isset($iTipMessage->message->VTIMEZONE)) $vtimezone=$iTipMessage->message->VTIMEZONE;

      $message="";

      if (isset($event->SUMMARY)){
        $titre=$event->SUMMARY;
        $message='*'.$titre."'".SAUT_LIGNE.SAUT_LIGNE;
      }

      $repetition=$this->getRepetitionString($event);
      if (!empty($repetition)){

        $message.=$this->libellesImip::quand.$repetition;
      }
      // cas même jour : date début fin
      // cas jour entier : date
      // cas jour != : date1 date2
      //               date1 début - date2 fin
      else if (isset($event->DTSTART)){

        if ($vtimezone) $dtstart=new \DateTime($event->DTSTART, $vtimezone);
        else $dtstart=new \DateTime($event->DTSTART);

        $tzid="";
        if (isset($vtimezone['TZID']))
          $tzid='('.$vtimezone['TZID'].')';

        if (isset($event->DTEND)){

          if ($vtimezone) $dtend=new \DateTime($event->DTEND, $vtimezone);
          else $dtend=new \DateTime($event->DTEND);

          $hms=$dtstart->format('H:i:s');
          $diff=$dtstart->diff($dtend);

          if ($hms==='00:00:00') {// pas d'heure

            if ($diff->d==1) // 1 jour entier
              $message.=$this->libellesImip::quand.$this->formateDateJour($dtstart).' '.$tzid.SAUT_LIGNE.SAUT_LIGNE;
            else
              $message.=$this->libellesImip::quand_jour_entier.$this->formateDateJour($dtstart).' - '
                        .$this->formateDateJour($dtend).' '.$tzid.SAUT_LIGNE.SAUT_LIGNE;
          }
          else{

            if ($diff->d==0){
              $message.=$this->libellesImip::quand.$this->formateDateJour($dtstart).' '.$dtstart->format('H:i').' - '.
                        $dtend->format('H:i').' '.$tzid.SAUT_LIGNE.SAUT_LIGNE;
            }
            else{
              $message.=$this->libellesImip::quand.$this->formateDateJour($dtstart).' '.$dtstart->format('H:i').
                        ' - '.$this->formateDateJour($dtend).' '.$dtend->format('H:i').' '.$tzid.SAUT_LIGNE.SAUT_LIGNE;
            }
          }
        }
        else { // pas de DTEND
          // rfc 5545 : For cases where a "VEVENT" calendar component specifies a "DTSTART" property with a DATE value type but no "DTEND"
          //nor "DURATION" property, the event's duration is taken to be one day.
          $message.=$this->libellesImip::quand.$this->formateDateJour($dtstart).' '.$tzid.SAUT_LIGNE.SAUT_LIGNE;
        }
      }

      if (isset($event->LOCATION)){
        $lieu=$event->LOCATION;
        $message.=$this->libellesImip::lieu.$lieu.SAUT_LIGNE.SAUT_LIGNE;
      }

      if (isset($event->DESCRIPTION)){
        $desc=$event->DESCRIPTION;
        $message.=$this->libellesImip::description.$desc.SAUT_LIGNE.SAUT_LIGNE;
      }

      if (isset($event->ORGANIZER)){
        $org=$event->ORGANIZER;
        $courriel=str_replace("mailto:", "", $org);
        if (isset($event->ORGANIZER['CN'])){
          $message.=$this->libellesImip::organisateur.$event->ORGANIZER['CN'].' '.$courriel.SAUT_LIGNE;
        }
        else{
          $message.=$this->libellesImip::organisateur.$courriel.SAUT_LIGNE;
        }
      }

      if (isset($event->ATTENDEE)){
        $parts=$event->ATTENDEE;
        $message.=SAUT_LIGNE.$this->libellesImip::participants;
        $sep_part="";
        foreach ($event->ATTENDEE as $part){

          $courriel=str_replace("mailto:", "", $part);
          if (isset($part['CN'])){
            $message.=$sep_part.$part['CN'].' '.$courriel;
          }
          else{
            $message.=$sep_part.$courriel;
          }

          $sep_part=', ';
        }
        $message.=SAUT_LIGNE;
      }
      return $message;
    }


    // retourne une chaine pour l'affichage des répétitions
    // vide si aucune
    protected function getRepetitionString($event, $vtimezone=null){

      if (!isset($event->RRULE)){
        return "";
      }

      $rrule=$event->RRULE;

      $rrule=Property\ICalendar\Recur::stringToArray($rrule);

      $freq=$rrule['FREQ'];
      $byday;
      if (isset($rrule['BYDAY'])) $byday=$rrule['BYDAY'];
      $until='';
      if (isset($rrule['UNTIL'])) $until=$rrule['UNTIL'];
      $count='';
      if (isset($rrule['COUNT'])) $count=$rrule['COUNT'];
      $interval='';
      if (isset($rrule['INTERVAL'])) $interval=$rrule['INTERVAL'];

      $texte.='Test : '.$byday.SAUT_LIGNE.SAUT_LIGNE;

      // répétition
      $texte=$this->libellesImip::a_lieu;
      if ($freq=='DAILY'){
        if (!empty($byday) && count($byday)==5){
          $texte.=$this->libellesImip::chaque_jour_ouvre;
        }
        else if (!empty($interval)){
          $texte.=$this->libellesImip::tous_les.$interval.$this->libellesImip::_jours;
        } else {
          $texte.=$this->libellesImip::chaque_jour;
        }
      }
      else if ($freq=='WEEKLY'){
        if (!empty($interval)){
          $texte.=$this->libellesImip::toutes_les.$interval.$this->libellesImip::semaines;
        }
        if (!empty($byday)){
          if (!is_array($byday)){
            $texte.=$this->libellesImip::le.$this->getJourFromBYDAY($byday);
          }
          else {
            $texte.=$this->libellesImip::chaque;
            for($i=0;$i<count($byday);$i++){
              if ($i>0) $texte.=$this->libellesImip::et;
              $texte.=$this->getJourFromBYDAY($byday[$i]);
            }
          }
        } else {
          $texte.=$this->libellesImip::chaque_semaine;
        }
      }
      else if ($freq=='MONTHLY'){

        if (isset($rrule['BYMONTHDAY'])){

          $bymonthday=$rrule['BYMONTHDAY'];
          if (!is_array($bymonthday)){
            if (-1==$bymonthday) $texte.=$this->libellesImip::le_dernier_jour;
            else $texte.=$this->libellesImip::le.$bymonthday;
          }
          else {
            $texte.=$this->libellesImip::les;
            for($i=0;$i<count($bymonthday);$i++){
              if ($i>0) $texte.=$this->libellesImip::et;
              if (-1==$bymonthday) $texte.=$this->libellesImip::le_dernier_jour;
              else $texte.=$bymonthday[$i];
            }
          }
          $texte.=$this->libellesImip::de_chaque_mois;
        }
        else if (!empty($byday)){

          $nb=count($byday);
          if ($nb==7){
            $texte.=$this->libellesImip::tous_les_jours_chaque_mois;
          }
          else{

            if (!empty($interval))
              $texte.=$this->libellesImip::tous_les.$interval.$this->libellesImip::mois_le.$this->getJourFromBYDAY($byday);
            else
              $texte.=$this->libellesImip::tous_les_mois_le.$this->getJourFromBYDAY($byday);
          }
        } else {

          $dtstart=new \DateTime($event->DTSTART, $vtimezone);
          $texte.=$this->libellesImip::le.$dtstart->format('d').$this->libellesImip::de_chaque_mois;
        }
      }
      else if ($freq=='YEARLY'){

        if (isset($rrule['BYMONTHDAY'])){

          $bymonthday=$rrule['BYMONTHDAY'];
          $bymonth=$rrule['BYMONTH'];

          $mois=$this->getMoisFromBYMONTH($bymonth);
          $texte.=$this->libellesImip::le.$bymonthday.' '.$mois;
        }
        else if (!empty($byday)){

          $bymonth=$rrule['BYMONTH'];
          $mois=$this->getMoisFromBYMONTH($bymonth);

          $jour=$this->getJourFromBYDAY($byday);

          if (!empty($interval))
            $texte.=$this->libellesImip::tous_les.$interval.$this->libellesImip::ans_le.$jour.
                    $this->libellesImip::du_mois_de.$mois;
          else
            $texte.=$this->libellesImip::le.$jour.$this->libellesImip::du_mois_de.$mois;

        } else {
          $texte.=$this->libellesImip::chaque_annee;
        }
      }
      $texte.=SAUT_LIGNE.SAUT_LIGNE;

      // à partir du
      $dtstart=new \DateTime($event->DTSTART, $vtimezone);
      $texte.=$this->libellesImip::a_partir_du.$dtstart->format('d/m/Y');

      if (!empty($until)){
        $dtend=new \DateTime($until, $vtimezone);
        $texte.=$this->libellesImip::jusquau.$dtend->format('d/m/Y');
      }
      else if (!empty($count)){
         $texte.=$this->libellesImip::et.$count.$this->libellesImip::fois_de_suite;
      }
      $texte.=SAUT_LIGNE.SAUT_LIGNE;

      // heures
      if (isset($event->DTEND)){

        $dtend=new \DateTime($event->DTEND, $vtimezone);
        $texte.=$this->libellesImip::__de.$dtstart->format('H:i').
                $this->libellesImip::_a_.$dtend->format('H:i').'.';
      }
      else if (isset($event->DURATION)){
        $dtend=clone $dtend;
        $duration=new \DateInterval(strval($event->DURATION));
        $endDate->add($duration);
        $texte.=$this->libellesImip::__de.$dtstart->format('H:i').
                $this->libellesImip::_a_.$dtend->format('H:i').'.';
      }
      else {//!!!
        $texte.=$this->libellesImip::___a.$dtstart->format('H:i');
      }
      $texte.=SAUT_LIGNE.SAUT_LIGNE;

      return $texte;
    }

    protected function getJourFromBYDAY($byday){

      if (strlen($byday)>2){

        if (false!==strpos($byday, '-1')){
          $d=substr($byday, 2);
          return $this->libellesImip::dernier.$this->getJourFromBYDAY($d);
        }
        $n=substr($byday, 0, 1);
        $d=substr($byday, 1);
        $num=$this->libellesImip::premier;
        if ($n==2) $num=$this->libellesImip::deuxieme;
        else if ($n==3) $num=$this->libellesImip::troisieme;
        else if ($n==4) $num=$this->libellesImip::quatrieme;
        else if ($n==5) $num=$this->libellesImip::cinquieme;
        return $num.$this->getJourFromBYDAY($d);
      }

      switch($byday){
        case 'MO' : return $this->libellesImip::lundi;
        case 'TU' : return $this->libellesImip::mardi;
        case 'WE' : return $this->libellesImip::mercredi;
        case 'TH' : return $this->libellesImip::jeudi;
        case 'FR' : return $this->libellesImip::vendredi;
        case 'SA' : return $this->libellesImip::samedi;
        case 'SU' : return $this->libellesImip::dimanche;
      }
      return "";
    }

    protected function getMoisFromBYMONTH($bymonth){

      $mois=[$this->libellesImip::janvier, $this->libellesImip::fevrier,
             $this->libellesImip::mars, $this->libellesImip::avril,
             $this->libellesImip::mai, $this->libellesImip::juin,
             $this->libellesImip::juillet, $this->libellesImip::aout,
             $this->libellesImip::septembre, $this->libellesImip::octobre,
             $this->libellesImip::novembre, $this->libellesImip::decembre];

      $n=strval($bymonth);
      if (0<$n && $n<13) return $mois[$n-1];
      return '';
    }

    protected function formateDateJour($date_time){

      $m=$date_time->format('m');

      return $this->getJourFromDateTime($date_time).$date_time->format(' d ').$this->getMoisFromBYMONTH($m).$date_time->format(' Y');
    }

    protected function getJourFromDateTime($date_time){
      $n=$date_time->format('N');
      switch($n){
        case '1' : return $this->libellesImip::lundi;
        case '2' : return $this->libellesImip::mardi;
        case '3' : return $this->libellesImip::mercredi;
        case '4' : return $this->libellesImip::jeudi;
        case '5' : return $this->libellesImip::vendredi;
        case '6' : return $this->libellesImip::samedi;
        case '7' : return $this->libellesImip::dimanche;
      }
      return "";
    }


    /* convertit l'ics en chaine base 64 avec sauts de lignes */
    protected function formateAttachement(ITip\Message $iTipMessage){

      $attachment=$iTipMessage->message->serialize();
      $lignes=explode("\r\n", $attachment);
      $attachment="";
      foreach ($lignes as $ligne){
        $attachment.=chunk_split($ligne);
      }

      return $attachment;
    }



    // @codeCoverageIgnoreStart
    // This is deemed untestable in a reasonable manner

    /**
     * This function is responsible for sending the actual email.
     *
     * @param string $to Recipient email address
     * @param string $subject Subject of the email
     * @param string $body iCalendar body
     * @param array $headers List of headers
     * @return void
     */
    protected function mail($to, $subject, $body, $headers, $params="") {

      if (class_exists('\LibMelanie\Mail::mail'))
        \LibMelanie\Mail::mail($to, $subject, $body, $headers, $params);
      else
        mail($to, $subject, $body, $headers, $params);
    }


    // @codeCoverageIgnoreEnd

    /**
     * Returns a bunch of meta-data about the plugin.
     *
     * Providing this information is optional, and is mainly displayed by the
     * Browser plugin.
     *
     * The description key in the returned array may contain html and will not
     * be sanitized.
     *
     * @return array
     */
    function getPluginInfo() {

        return [
            'name'        => $this->getPluginName(),
            'description' => 'Email delivery (rfc6037) for CalDAV scheduling',
            'link'        => 'http://sabre.io/dav/scheduling/',
        ];

    }

}

