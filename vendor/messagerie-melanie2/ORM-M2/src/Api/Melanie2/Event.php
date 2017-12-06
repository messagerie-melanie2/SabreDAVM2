<?php
/**
 * Ce fichier est développé pour la gestion de la librairie Mélanie2
 * Cette Librairie permet d'accèder aux données sans avoir à implémenter de couche SQL
 * Des objets génériques vont permettre d'accèder et de mettre à jour les données
 * ORM M2 Copyright © 2017 PNE Annuaire et Messagerie/MEDDE
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace LibMelanie\Api\Melanie2;

use LibMelanie\Lib\Melanie2Object;
use LibMelanie\Objects\EventMelanie;
use LibMelanie\Objects\HistoryMelanie;
use LibMelanie\Config\ConfigMelanie;
use LibMelanie\Config\MappingMelanie;
use LibMelanie\Exceptions;
use LibMelanie\Log\M2Log;
use LibMelanie\Lib\EventToICS;

/**
 * Classe evenement pour Melanie2,
 * implémente les API de la librairie pour aller chercher les données dans la base de données
 * Certains champs sont mappés directement ou passe par des classes externes
 * 
 * @author PNE Messagerie/Apitech
 * @package Librairie Mélanie2
 * @subpackage API Mélanie2
 *             @api
 * @property string $id Identifiant unique de l'évènement
 * @property string $calendar Identifiant du calendrier de l'évènement
 * @property string $uid UID de l'évènement
 * @property string $owner Créateur de l'évènement
 * @property string $keywords Keywords
 * @property string $title Titre de l'évènement
 * @property string $description Description de l'évènement
 * @property string $category Catégorie de l'évènment
 * @property string $location Lieu de l'évènement
 * @property Event::STATUS_* $status Statut de l'évènement
 * @property Event::CLASS_* $class Class de l'évènement (privé/public)
 * @property int $alarm Alarme en minute (TODO: class Alarm)
 * @property Attendee[] $attendees Tableau d'objets Attendee
 * @property boolean $hasattendees Est-ce que cette instance de l'événement a des participants
 * @property string $start String au format compatible DateTime, date de début
 * @property string $end String au format compatible DateTime, date de fin
 * @property int $modified Timestamp de la modification de l'évènement
 * @property Recurrence $recurrence objet Recurrence
 * @property Organizer $organizer objet Organizer
 * @property Exception[] $exceptions Liste d'exception
 * @property Attachment[] $attachments Liste des pièces jointes associées à l'évènement (URL ou Binaire)
 * @property bool $deleted Défini si l'exception est un évènement ou juste une suppression
 * @property-read string $realuid UID réellement stocké dans la base de données (utilisé pour les exceptions) (Lecture seule)
 * @property string $ics ICS associé à l'évènement courant, calculé à la volée en attendant la mise en base de données
 * @property-read VObject\Component\VCalendar $vcalendar Object VCalendar associé à l'évènement, peut permettre des manipulations sur les récurrences
 * @property $move Il s'ajout d'un MOVE, les participants sont conservés
 * @method bool load() Chargement l'évènement, en fonction du calendar et de l'uid
 * @method bool exists() Test si l'évènement existe, en fonction du calendar et de l'uid
 * @method bool save() Sauvegarde l'évènement et l'historique dans la base de données
 * @method bool delete() Supprime l'évènement et met à jour l'historique dans la base de données
 */
class Event extends Melanie2Object {
  // Accès aux objets associés
  /**
   * Utilisateur associé à l'objet
   * 
   * @var LibMelanie\\Objects\\UserMelanie
   */
  protected $usermelanie;
  /**
   * Calendrier associé à l'objet
   * 
   * @var LibMelanie\\Objects\\CalendarMelanie
   */
  protected $calendarmelanie;
  
  // object privé
  /**
   * Recurrence liée à l'objet
   * 
   * @var Recurrence $recurrence
   */
  private $recurrence;
  /**
   * Organisateur de l'évènement
   * 
   * @var string
   */
  protected $organizer;
  /**
   * L'évènement est supprimé
   * 
   * @var boolean
   */
  protected $deleted;
  /**
   * Tableau d'exceptions pour la récurrence
   * 
   * @var Exception[]
   */
  private $exceptions;
  /**
   * Tableau d'exceptions a supprimer au moment du save
   * 
   * @var Exception[]
   */
  private $deleted_exceptions;
  /**
   * Tableau d'attributs pour l'évènement
   * 
   * @var string[$attribute]
   */
  protected $attributes;
  /**
   * Permet de savoir si les attributs ont déjà été chargés depuis la base
   * 
   * @var bool
   */
  protected $attributes_loaded = false;
  /**
   * Tableau contenant les pièces jointes de l'évènement
   * 
   * @var Attachment[]
   */
  protected $attachments;
  
  /**
   * Object VCalendar disponible via le VObject
   * 
   * @var VCalendar
   */
  private $vcalendar;
  
  /**
   * Défini s'il s'agit d'un move qui nécessite de conserver les participants
   * Dans ce cas les participants doivent être doublés
   * 
   * @var boolean
   */
  protected $move = false;
  
  /**
   * La génération de l'ICS doit elle retourner des freebusy
   * Il n'y aura donc pas de participants, pièces jointes et informations supplémentaires
   * 
   * @var boolean
   */
  public $ics_freebusy = false;
  /**
   * La génération de l'ICS doit elle retourner les pièces jointes ?
   * 
   * @var boolean
   */
  public $ics_attachments = true;
  
  /**
   * **
   * CONSTANTES
   */
  // CLASS Fields
  const CLASS_PRIVATE = ConfigMelanie::PRIV;
  const CLASS_PUBLIC = ConfigMelanie::PUB;
  const CLASS_CONFIDENTIAL = ConfigMelanie::CONFIDENTIAL;
  // STATUS Fields
  const STATUS_TENTATIVE = ConfigMelanie::TENTATIVE;
  const STATUS_CONFIRMED = ConfigMelanie::CONFIRMED;
  const STATUS_CANCELLED = ConfigMelanie::CANCELLED;
  const STATUS_NONE = ConfigMelanie::NONE;
  
  /**
   * Constructeur de l'objet
   * 
   * @param \LibMelanie\Objects\UserMelanie $usermelanie          
   * @param \LibMelanie\Objects\CalendarMelanie $calendarmelanie          
   */
  public function __construct($usermelanie = null, $calendarmelanie = null) {
    // Défini la classe courante
    $this->get_class = get_class($this);
    
    // M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->__construct()");
    // Définition de l'évènement melanie2
    $this->objectmelanie = new EventMelanie();
    
    // Définition des objets associés
    if (isset($usermelanie))
      $this->usermelanie = $usermelanie;
    if (isset($calendarmelanie)) {
      $this->calendarmelanie = $calendarmelanie;
      $this->objectmelanie->calendar = $this->calendarmelanie->id;
    }
  }
  
  /**
   * Défini l'utilisateur Melanie
   * 
   * @param UserMelanie $usermelanie          
   * @ignore
   *
   */
  public function setUserMelanie($usermelanie) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setUserMelanie()");
    $this->usermelanie = $usermelanie;
  }
  /**
   * Retourne l'utilisateur Melanie
   * 
   * @return UserMelanie
   */
  public function getUserMelanie() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getUserMelanie()");
    return $this->usermelanie;
  }
  
  /**
   * Défini le calendrier Melanie
   * 
   * @param CalendarMelanie $calendarmelanie          
   * @ignore
   *
   */
  public function setCalendarMelanie($calendarmelanie) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setCalendarMelanie()");
    $this->calendarmelanie = $calendarmelanie;
    $this->objectmelanie->calendar = $this->calendarmelanie->id;
  }
  /**
   * Retourne le calendrier Melanie
   * 
   * @return CalendarMelanie
   */
  public function getCalendarMelanie() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getCalendarMelanie()");
    return $this->calendarmelanie;
  }
  
  /**
   * Retourne l'ICS lié à l'évènement courant
   */
  public function getICS() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getICS()");
    return EventToICS::Convert($this, $this->calendarmelanie, $this->usermelanie);
  }
  
  /**
   * Retourne un attribut supplémentaire pour l'évènement
   * 
   * @param string $name
   *          Nom de l'attribut
   * @return string|NULL valeur de l'attribut, null s'il n'existe pas
   */
  public function getAttribute($name) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getAttribute($name)");
    // Si les attributs n'ont pas été chargés
    if (!$this->attributes_loaded) {
      $this->loadAttributes();
    }
    if (!isset($this->attributes[$name])) {
      return null;
    }
    return $this->attributes[$name]->value;
  }
  /**
   * Met à jour ou ajoute l'attribut
   * 
   * @param string $name
   *          Nom de l'attribut
   * @param string $value
   *          Valeur de l'attribut
   */
  public function setAttribute($name, $value) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setAttribute($name)");
    if (!isset($value)) {
      // Si name est a null on supprime le champ
      $this->deleteAttribute($name);
    } else {
      // Création de l'objet s'il n'existe pas
      if (!isset($this->attributes))
        $this->attributes = [];
      if (isset($this->attributes[$name])) {
        $this->attributes[$name]->value = $value;
      }
      else {
        $eventproperty = new EventProperty();
        $eventproperty->event = $this->realuid;
        if (isset($this->calendarmelanie)) {
          $eventproperty->calendar = $this->calendarmelanie->id;
        } else {
          $eventproperty->calendar = $this->calendar;
        }
        // Problème de User avec DAViCal
        if (isset($this->calendarmelanie)) {
          $eventproperty->user = $this->calendarmelanie->owner;
        } else if (isset($this->owner)) {
          $eventproperty->user = $this->owner;
        } else {
          $eventproperty->user = '';
        }
        
        $eventproperty->key = $name;
        $eventproperty->value = $value;
        $this->attributes[$name] = $eventproperty;
      }
    }
  }
  /**
   * Method permettant de définir directement la liste des attributs de l'évènement
   * 
   * @param array $attributes          
   */
  public function setAttributes($attributes) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setAttributes()");
    // Positionne la liste des attributs
    $this->attributes = $attributes;
    $this->attributes_loaded = true;
  }
  /**
   * Suppression d'un attribut
   * 
   * @param string $name          
   */
  public function deleteAttribute($name) {
    // Si les attributs n'ont pas été chargés
    if (!$this->attributes_loaded) {
      $this->loadAttributes();
    }
    // Si l'atrribut existe, on le supprime
    if (isset($this->attributes[$name])) {
      return $this->attributes[$name]->delete();
    }
    return false;
  }
  
  /**
   * ***************************************************
   * EVENT METHOD
   */
  /**
   * Sauvegarde des participants
   * Methode pour sauvegarder les participants pour l'architecture de la base de données Horde
   * 
   * @return boolean
   */
  private function saveAttendees() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->saveAttendees()");
    // Détecter les attendees pour tous les évènements et exceptions
    $hasAttendees = $this->getMapHasAttendees();
    // Parcours les exceptions pour chercher les attendees
    if (!$hasAttendees) {
      $exceptions = $this->getMapExceptions();
      foreach ($exceptions as $exception) {
        $attendees = $exception->attendees;
        $hasAttendees |= isset($attendees) && count($attendees) > 0;
        if ($hasAttendees) {
          break;
        }
      }
    }
    // Récupération de l'organisateur
    $organizer = $this->getMapOrganizer();
    if (!$hasAttendees || $organizer->extern || 
        // MANTIS 4016: Gestion des COPY/MOVE
        $this->move)
      return false;
    
    if (!is_null($organizer->calendar)) {
      $organizer_calendar_id = $organizer->calendar;
    } else {
      // Si l'évènement n'existe pas il faut essayer de récupérer l'évènement de l'organisateur
      $listevents = new Event();
      $listevents->uid = $this->uid;
      // XXX: Problème dans la gestion des participants
      // N'utiliser l'organizer uid que s'il existe ?
      if (isset($this->objectmelanie->organizer_uid) && !empty($this->objectmelanie->organizer_uid)) {
        $listevents->owner = $this->objectmelanie->organizer_uid;
      }
      $events = $listevents->getList(null, null, null, 'attendees', false);
      // Si l'évènement n'existe pas et que l'organisateur est différent, c'est un organisateur externe
      if (count($events) == 0) {
        if (strtolower($this->objectmelanie->organizer_uid) == strtolower($this->usermelanie->uid)) {
          // L'évènement n'existe pas, l'organisateur est celui qui créé l'évènement
          // Donc on est dans le cas d'une création interne
          return false;
        } else {
          // L'évènement n'existe pas, mais l'organisateur est différent du créateur
          // On considère alors que c'est un organisateur externe (même s'il est interne au ministère)
          $this->getMapOrganizer()->extern = true;
          $this->getMapOrganizer()->email = $organizer->email;
          $this->getMapOrganizer()->name = $organizer->name;
          return false;
        }
      }
      // XXX on doit arriver ici quand le load ne retourne rien car l'évènement n'existe pas
      // Parcourir les évènements trouvés pour chercher l'évènement de l'organisateur
      foreach ($events as $_event) {
        if ($_event->hasattendees) {
          $organizer_event = $_event;
          $organizer_calendar_id = $_event->calendar;
          break;
        } else {
          $exceptions = $_event->getMapExceptions();
          if (isset($exceptions) && is_array($exceptions)) {
            foreach ($exceptions as $_exception) {
              if ($_exception->hasattendees) {
                $organizer_event = $_event;
                $organizer_calendar_id = $_event->calendar;
                break;
              }
            }
          }
        }
      }
      // Si l'organisateur n'est pas trouvé
      if (!isset($organizer_calendar_id)) {
        // On considère également que c'est un organisateur externe
        $this->getMapOrganizer()->extern = true;
        $this->getMapOrganizer()->email = $organizer->email;
        $this->getMapOrganizer()->name = $organizer->name;
        return false;
      }
    }
    // Test si on est dans le calendrier de l'organisateur (dans ce cas on sauvegarde directement les participants)
    if ($organizer_calendar_id != $this->calendar) {
      // Définition de la sauvegarde de l'évènement de l'organisateur
      $save = false;
      if (!isset($organizer_event)) {
        $organizer_calendar = new Calendar($this->usermelanie);
        $organizer_calendar->id = $organizer_calendar_id;
        // Recuperation de l'évènement de l'organisateur
        $organizer_event = new Event($this->usermelanie, $organizer_calendar);
        $organizer_event->uid = $this->uid;
        if (!$organizer_event->load()) {
          // Si l'évènement de l'organisateur n'existe pas, on le considère en externe
          $this->getMapOrganizer()->extern = true;
          $this->getMapOrganizer()->email = $organizer->email;
          $this->getMapOrganizer()->name = $organizer->name;
          return false;
        }
      }
      if (!$this->deleted && isset($this->objectmelanie->attendees)) {
        // Recupération de la réponse du participant
        $response = Attendee::RESPONSE_NEED_ACTION;
        foreach ($this->attendees as $attendee) {
          if (strtolower($attendee->uid) == strtolower($this->usermelanie->uid)) {
            $response = $attendee->response;
            // MANTIS 0004708: Lors d'un "s'inviter" utiliser les informations de l'ICS
            $att_email = $attendee->email;
            $att_name = $attendee->name;
            break;
          }
        }
        // Mise à jour du participant
        if ($response != Attendee::RESPONSE_NEED_ACTION) {
          // Récupère les participants de l'organisateur
          $organizer_attendees = $organizer_event->getMapAttendees();
          $invite = true;
          foreach ($organizer_attendees as $attendee) {
            if (strtolower($attendee->uid) == strtolower($this->usermelanie->uid)) {
              if ($attendee->response != $response) {
                $attendee->response = $response;
                $organizer_event->setMapAttendees($organizer_attendees);
                // Sauvegarde de l'evenement de l'organisateur
                $save = true;
                $invite = false;
              } else {
                // MANTIS 0004471: Problème lorsque la réponse du participant ne change pas
                $invite = false;
              }
              break;
            }
          }
          // S'inviter dans la réunion
          if ($invite && ConfigMelanie::SELF_INVITE) {
            $attendee = new Attendee($organizer_event);
            // MANTIS 0004708: Lors d'un "s'inviter" utiliser les informations de l'ICS
            $attendee->email = isset($att_email) ? $att_email : $this->usermelanie->email;
            $attendee->name = isset($att_name) ? $att_name : '';
            $attendee->response = $response;
            $attendee->role = Attendee::ROLE_REQ_PARTICIPANT;
            $organizer_attendees[] = $attendee;
            $organizer_event->attendees = $organizer_attendees;
            $save = true;
          }
        }
        unset($this->objectmelanie->attendees);
      }
      // Gestion des exceptions
      if (count($this->getMapExceptions()) > 0) {
        // Exceptions de l'évènement de l'organisateur
        $organizer_event_exceptions = $organizer_event->getMapExceptions();
        // Parcour les exceptions pour le traitement
        foreach ($this->exceptions as $recurrenceId => $exception) {
          if (!$exception->deleted) {
            if (!isset($exception->attendees))
              continue;
            if (!isset($organizer_event_exceptions[$recurrenceId])) {
              // L'exception n'existe pas, alors qu'on en veut une chez le participant
              // XXX: Traiter ce cas en créant une exception dans l'évènement de l'organisateur
              $organizer_event_exceptions[$recurrenceId] = new Exception($organizer_event);
              $organizer_event_exceptions[$recurrenceId]->attendees = $organizer_event->getMapAttendees();
              $organizer_event_exceptions[$recurrenceId]->recurrenceId = $exception->recurrenceId;
              $organizer_event_exceptions[$recurrenceId]->uid = $organizer_event->uid;
              $organizer_event_exceptions[$recurrenceId]->owner = $organizer_event->owner;
              $organizer_event_exceptions[$recurrenceId]->start = $exception->start;
              $organizer_event_exceptions[$recurrenceId]->end = $exception->end;
              $organizer_event_exceptions[$recurrenceId]->modified = time();
              $organizer_event_exceptions[$recurrenceId]->class = $organizer_event->class;
              $organizer_event_exceptions[$recurrenceId]->status = $organizer_event->status;
              $organizer_event_exceptions[$recurrenceId]->title = $organizer_event->title;
              $organizer_event_exceptions[$recurrenceId]->description = $organizer_event->description;
              $organizer_event_exceptions[$recurrenceId]->location = $organizer_event->location;
              $organizer_event_exceptions[$recurrenceId]->category = $organizer_event->category;
              $organizer_event_exceptions[$recurrenceId]->alarm = $organizer_event->alarm;
              $save = true;
            }
            $organizer_event_exception = $organizer_event_exceptions[$recurrenceId];
            if ($organizer_event_exception->deleted) {
              unset($exception->attendees);
              continue;
            }
            // Recupération de la réponse du participant
            $response = Attendee::RESPONSE_NEED_ACTION;
            foreach ($exception->attendees as $attendee) {
              if (strtolower($attendee->uid) == strtolower($this->usermelanie->uid)) {
                $response = $attendee->response;
                break;
              }
            }
            if ($response != Attendee::RESPONSE_NEED_ACTION) {
              // Mise à jour du participant
              $invite = true;
              $organizer_exception_attendees = $organizer_event_exception->attendees;
              foreach ($organizer_exception_attendees as $attendee) {
                if (strtolower($attendee->uid) == strtolower($this->usermelanie->uid)) {
                  $attendee->response = $response;
                  $organizer_event_exception->attendees = $organizer_exception_attendees;
                  $save = true;
                  $invite = false;
                  break;
                }
              }
              // S'inviter dans la réunion
              if ($invite && ConfigMelanie::SELF_INVITE) {
                $attendee = new Attendee($organizer_event_exception);
                $attendee->email = $this->usermelanie->email;
                $attendee->name = '';
                $attendee->response = $response;
                $attendee->role = Attendee::ROLE_REQ_PARTICIPANT;
                $organizer_exception_attendees[] = $attendee;
                $organizer_event_exception->attendees = $organizer_exception_attendees;
                $save = true;
              }
            }
            unset($exception->attendees);
          }
        }
        $organizer_event->setMapExceptions($organizer_event_exceptions);
      }
      // Sauvegarde de l'evenement si besoin
      if ($save) {
        $organizer_event->modified = time();
        $organizer_event->save();
        // Mise à jour de l'etag pour tout le monde
        $this->objectmelanie->updateMeetingEtag();
      }
    }
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    return true;
  }
  
  /**
   * Suppression de la liste des pièces jointes liées à l'évènement
   */
  protected function deleteAttachments() {
    $event_uid = $this->objectmelanie->uid;
    $_events = new Event();
    $_events->uid = $event_uid;
    $nb_events = $_events->getList('count');
    $count = $nb_events['']->events_count;
    unset($nb_events);
    // Si c'est le dernier evenement avec le même uid on supprime toutes les pièces jointes
    if ($count === 0) {
      $attachments_folders = new Attachment();
      $attachments_folders->isfolder = true;
      $attachments_folders->path = $event_uid;
      $folders_list = [];
      // Récupère les dossiers lié à l'évènement
      $folders = $attachments_folders->getList();
      if (count($folders) > 0) {
        foreach ($folders as $folder) {
          $folders_list[] = $folder->path . '/' . $folder->name;
        }
        $attachments = new Attachment();
        $attachments->isfolder = false;
        $attachments->path = $folders_list;
        // Lecture des pièces jointes pour chaque dossier de l'évènement
        $attachments = $attachments->getList([
            'id',
            'name',
            'path'
        ]);
        if (count($attachments) > 0) {
          foreach ($attachments as $attachment) {
            // Supprime la pièce jointe
            $attachment->delete();
          }
        }
        foreach ($folders as $folder) {
          // Supprime le dossier
          $folder->delete();
        }
      }
      $folder = new Attachment();
      $folder->isfolder = true;
      $folder->path = '';
      $folder->name = $event_uid;
      if ($folder->load()) {
        $folder->delete();
      }
    }
  }
  
  /**
   * Sauvegarde les attributs dans la base de données
   */
  protected function saveAttributes() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->saveAttributes()");
    // Parcours les attributs pour les enregistrer
    if (isset($this->attributes)) {
      foreach ($this->attributes as $name => $attribute) {
        $attribute->save();
      }
    }
  }
  /**
   * Charge les attributs en mémoire
   */
  protected function loadAttributes() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->loadAttributes()");
    // Création de l'objet s'il n'existe pas
    if (!isset($this->attributes))
      $this->attributes = [];
    // Génération de l'attribut pour le getList
    $eventproperty = new EventProperty();
    $eventproperty->event = $this->realuid;
    if (isset($this->calendarmelanie)) {
      $eventproperty->calendar = $this->calendarmelanie->id;
    } else {
      $eventproperty->calendar = $this->calendar;
    }
    // Problème de User avec DAViCal
    if (isset($this->calendarmelanie)) {
      $eventproperty->user = $this->calendarmelanie->owner;
    } else if (isset($this->owner)) {
      $eventproperty->user = $this->owner;
    } else {
      $eventproperty->user = '';
    }
    $properties = $eventproperty->getList();
    // Récupération de la liste des attributs
    foreach ($properties as $property) {
      $this->attributes[$property->key] = $property;
    }
    $this->attributes_loaded = true;
  }
  /**
   * Supprime les attributs
   */
  protected function deleteAttributes() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->loadAttributes()");
    if (!$this->attributes_loaded) {
      $this->loadAttributes();
    }
    // Parcours les attributs pour les enregistrer
    if (isset($this->attributes)) {
      foreach ($this->attributes as $name => $attribute) {
        $attribute->delete();
      }
    }
  }
  
  /**
   * Charge les exceptions en mémoire
   * Doit être utilisé quand l'évènement n'existe pas, donc que le load retourne false
   * 
   * @return boolean
   */
  private function loadExceptions() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->loadExceptions()");
    $event = new Event($this->usermelanie, $this->calendarmelanie);
    $event->uid = $this->uid . '%' . Exception::RECURRENCE_ID;
    $events = $event->getList(null, null, [
        'uid' => MappingMelanie::like
    ]);
    if (isset($events[$this->uid . $this->calendar])) {
      $this->modified = isset($events[$this->uid . $this->calendar]->modified) ? $events[$this->uid . $this->calendar]->modified : 0;
      $this->setMapExceptions($events[$this->uid . $this->calendar]->getMapExceptions());
      $this->objectmelanie->setExist(true);
    }
    if (count($this->exceptions) > 0) {
      $this->deleted = true;
      return true;
    }
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    return false;
  }
  
  /**
   * Test pour savoir si on est dans une exception ou un évènement maitre
   * 
   * @return boolean
   */
  private function notException() {
    return $this->get_class == 'LibMelanie\Api\Melanie2\Event';
  }
  
  /**
   * ***************************************************
   * METHOD MAPPING
   */
  /**
   * Mapping de la sauvegarde de l'objet
   * Appel la sauvegarde de l'historique en même temps
   * 
   * @ignore
   *
   */
  function save() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->save()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    // Sauvegarde des participants
    $this->saveAttendees();
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->save() delete " . count($this->deleted_exceptions));
    // Supprimer les exceptions
    if (isset($this->deleted_exceptions) && count($this->deleted_exceptions) > 0) {
      foreach ($this->deleted_exceptions as $exception) {
        M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->save() delete " . $exception->uid);
        $exception->delete();
      }
    }
    $exMod = false;
    // Sauvegarde des exceptions
    if (isset($this->exceptions)) {
      foreach ($this->exceptions as $exception) {
        $res = $exception->save();
        $exMod = $exMod || !is_null($res);
      }
    }
    if ($this->deleted) {
      // Sauvegarde des attributs
      $this->saveAttributes();
      return false;
    }
      
    if ($exMod)
      $this->modified = time();
    if (!isset($this->owner)) {
      $this->owner = $this->usermelanie->uid;
    }
    // Sauvegarde l'objet
    $insert = $this->objectmelanie->save();
    if (!is_null($insert)) {
      // Sauvegarde des attributs
      $this->saveAttributes();
      // Gestion de l'historique
      $history = new HistoryMelanie();
      $history->uid = ConfigMelanie::CALENDAR_PREF_SCOPE . ":" . $this->calendar . ":" . $this->realuid;
      $history->action = $insert ? ConfigMelanie::HISTORY_ADD : ConfigMelanie::HISTORY_MODIFY;
      $history->timestamp = time();
      $history->description = "LibM2/" . ConfigMelanie::APP_NAME;
      $history->who = isset($this->usermelanie) ? $this->usermelanie->uid : $this->calendar;
      // Enregistrement dans la base
      if (!is_null($history->save()))
        return $insert;
    }
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->save() Rien a sauvegarder: return null");
    return null;
  }
  
  /**
   * Mapping de la suppression de l'objet
   * Appel la sauvegarde de l'historique en même temps
   * 
   * @ignore
   *
   */
  function delete() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->delete()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    // Suppression des exceptions
    if (isset($this->exceptions)) {
      foreach ($this->exceptions as $exception) {
        if (!$exception->deleted)
          $exception->delete();
      }
    }
    // Suppression de l'objet
    if ($this->objectmelanie->delete()) {
      // Suppression des attributs liés à l'évènement
      $this->deleteAttributes();
      // Suppression des pièces jointes de l'évènement
      $this->deleteAttachments();
      // Gestion de l'historique
      $history = new HistoryMelanie();
      $history->uid = ConfigMelanie::CALENDAR_PREF_SCOPE . ":" . $this->objectmelanie->calendar . ":" . $this->objectmelanie->uid;
      $history->action = ConfigMelanie::HISTORY_DELETE;
      $history->timestamp = time();
      $history->description = "LibM2/" . ConfigMelanie::APP_NAME;
      $history->who = isset($this->usermelanie) ? $this->usermelanie->getUid() : $this->objectmelanie->calendar;
      // Enregistrement dans la base
      if (!is_null($history->save()))
        return true;
    }
    else {
      // Suppression des attributs liés à l'évènement
      $this->deleteAttributes();
    }
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    M2Log::Log(M2Log::LEVEL_ERROR, $this->get_class . "->delete() Error: return false");
    return false;
  }
  
  /**
   * Utilisé pour les exceptions
   * visiblement l'héritage ne fonctionne pas bien dans notre cas
   * 
   * @ignore
   *
   */
  function load() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->load()");
    $ret = $this->objectmelanie->load();
    if (!$ret && $this->notException())
      $ret = $this->loadExceptions();
    else
      $this->deleted = false;
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    return $ret;
  }
  
  /**
   * Permet de récupérer la liste d'objet en utilisant les données passées
   * (la clause where s'adapte aux données)
   * Il faut donc peut être sauvegarder l'objet avant d'appeler cette méthode
   * pour réinitialiser les données modifiées (propriété haschanged)
   * 
   * @param String[] $fields
   *          Liste les champs à récupérer depuis les données
   * @param String $filter
   *          Filtre pour la lecture des données en fonction des valeurs déjà passé, exemple de filtre : "((#description# OR #title#) AND #start#)"
   * @param String[] $operators
   *          Liste les propriétés par operateur (MappingMelanie::like, MappingMelanie::supp, MappingMelanie::inf, MappingMelanie::diff)
   * @param String $orderby
   *          Tri par le champ
   * @param bool $asc
   *          Tri ascendant ou non
   * @param int $limit
   *          Limite le nombre de résultat (utile pour la pagination)
   * @param int $offset
   *          Offset de début pour les résultats (utile pour la pagination)
   * @param String[] $case_unsensitive_fields
   *          Liste des champs pour lesquels on ne sera pas sensible à la casse
   * @return Event[] Array
   */
  function getList($fields = [], $filter = "", $operators = [], $orderby = "", $asc = true, $limit = null, $offset = null, $case_unsensitive_fields = []) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getList()");
    $_events = $this->objectmelanie->getList($fields, $filter, $operators, $orderby, $asc, $limit, $offset, $case_unsensitive_fields);
    if (!isset($_events))
      return null;
    $events = [];
    $exceptions = [];
    // MANTIS 3680: Charger tous les attributs lors d'un getList
    $events_uid = [];
    // Traitement de la liste des évènements
    foreach ($_events as $_event) {
      try {
        if (isset($this->calendarmelanie) && $this->calendarmelanie->id == $_event->calendar) {
          $calendar = $this->calendarmelanie;
        } else {
          $calendar = new Calendar($this->usermelanie);
          $calendar->id = $_event->calendar;
        }
        if (strpos($_event->uid, Exception::RECURRENCE_ID) === false) {
          $event = new Event($this->usermelanie, $calendar);
          $event->setObjectMelanie($_event);
          $event->setMapDeleted(false);
          $events[$event->uid . $event->calendar] = $event;
          // MANTIS 3680: Charger tous les attributs lors d'un getList
          $events_uid[] = $event->uid;
        } else {
          $exception = new Exception(null, $this->usermelanie, $calendar);
          $exception->setObjectMelanie($_event);
          if (!isset($exceptions[$exception->uid . $exception->calendar]) || !is_array($exceptions[$exception->uid . $exception->calendar]))
            $exceptions[$exception->uid . $exception->calendar] = [];
          // Filtrer les exceptions qui n'ont pas de date
          if (empty($exception->start) || empty($exception->end)) {
            $exception->deleted = true;
          } else {
            $exception->deleted = false;
          }
          $recId = new \DateTime(substr($exception->realuid, strlen($exception->realuid) - strlen(Exception::FORMAT_STR . Exception::RECURRENCE_ID), strlen(Exception::FORMAT_STR)));
          $exception->recurrenceId = $recId->format(Exception::FORMAT_ID);
          $exceptions[$exception->uid . $exception->calendar][$exception->recurrenceId] = $exception;
          // MANTIS 3680: Charger tous les attributs lors d'un getList
          $events_uid[] = $exception->realuid;
        }
      } catch ( \Exception $ex ) {
        M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getList() Exception: " . $ex);
      }
    }
    // Détruit les variables pour libérer le plus rapidement de la mémoire
    unset($_events);
    // Traitement des exceptions qui n'ont pas d'évènement associé
    // On crée un faux évènement qui va contenir ces exceptions
    foreach ($exceptions as $key => $_exceptions) {
      if (!isset($events[$key])) {
        $event = new Event($this->usermelanie);
        $modified = 0;
        foreach ($_exceptions as $_exception) {
          $calendarid = $_exception->calendar;
          $uid = $_exception->uid;
          $_exception->setEventParent($event);
          if (!isset($_exception->modified))
            $_exception->modified = 0;
          if ($_exception->modified > $modified)
            $modified = $_exception->modified;
        }
        if (isset($uid)) {
          if (isset($this->calendarmelanie) && $this->calendarmelanie->id == $_event->calendar) {
            $calendar = $this->calendarmelanie;
          } else {
            $calendar = new Calendar($this->usermelanie);
            $calendar->id = $calendarid;
          }
          $event->setCalendarMelanie($calendar);
          $event->uid = $uid;
          $event->setMapDeleted(true);
          $event->modified = $modified;
          $event->setMapExceptions($_exceptions);
          $events[$event->uid . $event->calendar] = $event;
        }
      } else {
        foreach ($_exceptions as $_exception) {
          $events[$key]->addException($_exception);
        }
      }
    }
    // Détruit les variables pour libérer le plus rapidement de la mémoire
    unset($exceptions);
    // TODO: Test - Nettoyage mémoire
    //gc_collect_cycles();
    return $events;
  }
  
  /**
   * ***************************************************
   * DATA MAPPING
   */
  /**
   * Mapping class field
   * 
   * @param Event::CLASS_* $class          
   */
  protected function setMapClass($class) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapClass($class)");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (isset(MappingMelanie::$MapClassObjectMelanie[$class]))
      $this->objectmelanie->class = MappingMelanie::$MapClassObjectMelanie[$class];
  }
  /**
   * Mapping class field
   */
  protected function getMapClass() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapClass()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (isset(MappingMelanie::$MapClassObjectMelanie[$this->objectmelanie->class]))
      return MappingMelanie::$MapClassObjectMelanie[$this->objectmelanie->class];
    else
      return self::CLASS_PUBLIC;
  }
  
  /**
   * Mapping status field
   * 
   * @param Event::STATUS_* $status          
   */
  protected function setMapStatus($status) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapStatus($status)");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (isset(MappingMelanie::$MapStatusObjectMelanie[$status]))
      $this->objectmelanie->status = MappingMelanie::$MapStatusObjectMelanie[$status];
  }
  /**
   * Mapping status field
   */
  protected function getMapStatus() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapStatus()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (isset(MappingMelanie::$MapStatusObjectMelanie[$this->objectmelanie->status]))
      return MappingMelanie::$MapStatusObjectMelanie[$this->objectmelanie->status];
    else
      return self::STATUS_CONFIRMED;
  }
  
  /**
   * Mapping recurrence field
   * 
   * @param Recurrence $recurrence          
   */
  protected function setMapRecurrence($recurrence) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapRecurrence()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    $this->recurrence = $recurrence;
    $this->recurrence->setObjectMelanie($this->objectmelanie);
  }
  /**
   * Mapping recurrence field
   */
  protected function getMapRecurrence() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapRecurrence()");
    if (!isset($this->recurrence))
      $this->recurrence = new Recurrence($this);
    return $this->recurrence;
  }
  
  /**
   * Mapping organizer field
   * 
   * @param Organizer $organizer          
   */
  protected function setMapOrganizer($organizer) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapOrganizer()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    $this->organizer = $organizer;
    $this->organizer->setObjectMelanie($this->objectmelanie);
  }
  /**
   * Mapping organizer field
   */
  protected function getMapOrganizer() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapOrganizer()");
    if (!isset($this->organizer))
      $this->organizer = new Organizer($this);
    return $this->organizer;
  }
  
  /**
   * Mapping attendees field
   * 
   * @param Attendee[] $attendees          
   */
  protected function setMapAttendees($attendees) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapAttendees()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    $_attendees = [];
    if (!empty($attendees)) {
      foreach ($attendees as $attendee) {
        $_attendees[$attendee->email] = $attendee->render();
      }
    }
    $this->objectmelanie->attendees = serialize($_attendees);
  }
  /**
   * Mapping attendees field
   */
  protected function getMapAttendees() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapAttendees()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    // Récupération des participants
    $object_attendees = null;
    // Participants directement dans l'objet
    // TODO: Corriger le problème lorsque la variable isset mais vide (ou a:{})
    if (isset($this->objectmelanie->attendees) && $this->objectmelanie->attendees != "" && $this->objectmelanie->attendees != "a:0:{}")
      $object_attendees = $this->objectmelanie->attendees;
    // Participants appartenant à l'organisateur
    elseif (isset($this->objectmelanie->organizer_attendees) && $this->objectmelanie->organizer_attendees != "" && $this->objectmelanie->organizer_attendees != "a:0:{}")
      $object_attendees = $this->objectmelanie->organizer_attendees;
    else
      return [];
    if ($object_attendees == "")
      return [];
    $_attendees = unserialize($object_attendees);
    $attendees = [];
    if (is_array($_attendees) && count($_attendees) > 0) {
      foreach ($_attendees as $key => $_attendee) {
        $attendee = new Attendee($this);
        $attendee->setEmail($key);
        $attendee->define($_attendee);
        $attendees[] = $attendee;
      }
    }
    return $attendees;
  }
  /**
   * Mapping hasattendees field
   * @return boolean
   */
  protected function getMapHasAttendees() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapHasAttendees() : " . ((isset($this->objectmelanie->attendees) && $this->objectmelanie->attendees != "" && $this->objectmelanie->attendees != "a:0:{}") ? "true" : "false"));
    return (isset($this->objectmelanie->attendees) && $this->objectmelanie->attendees != "" && $this->objectmelanie->attendees != "a:0:{}");
  }
  /**
   * Mapping real uid field
   */
  protected function getMapRealUid() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapRealUid()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    return $this->objectmelanie->uid;
  }
  
  /**
   * Mapping deleted field
   * 
   * @param bool $deleted          
   */
  protected function setMapDeleted($deleted) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapDeleted($deleted)");
    $this->deleted = $deleted;
  }
  /**
   * Mapping deleted field
   */
  protected function getMapDeleted() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapDeleted()");
    return $this->deleted || $this->start == '1970-01-01 00:00:00';
  }
  
  /**
   * Mapping exceptions field
   * 
   * @param Exception[] $exceptions          
   * @ignore
   *
   */
  protected function setMapExceptions($exceptions) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapExceptions()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    
    $_exceptions = [];
    // Get the TZ
    try {
      if (isset($this->calendarmelanie))
        $tz = $this->calendarmelanie->getTimezone();
    } catch ( \Exception $ex ) {
      /* Impossible de récupérer le timezone */
      $tz = '';
    }
    // Définition Timezone de l'utilisateur
    $user_timezone = new \DateTimeZone(!empty($tz) ? $tz : date_default_timezone_get());
    // MANTIS 3615: Alimenter le champ recurrence master
    // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
    $recurrence_master = [];
    if (!is_array($exceptions)) {
      $exceptions = [
          $exceptions
      ];
    }
    // Rechercher les exceptions à supprimer au moment du save
    if (isset($this->exceptions) && is_array($this->exceptions) && count($this->exceptions) > 0) {
      $this->deleted_exceptions = [];
      foreach ($this->exceptions as $_exception) {
        $date = new \DateTime($_exception->recurrenceId);
        $_recId = $date->format("Ymd");
        $deleteEx = true;
        foreach ($exceptions as $exception) {
          $date = new \DateTime($exception->recurrenceId);
          $recId = $date->format("Ymd");
          if ($_recId == $recId && (!$exception->deleted || $_exception->deleted)) {
            $deleteEx = false;
            break;
          }
        }
        if ($deleteEx) {
          $this->deleted_exceptions[] = $_exception;
        }
      }
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapExceptions() deleted_exceptions : " . count($this->deleted_exceptions));
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapExceptions() old exceptions : " . count($this->exceptions));
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapExceptions() new exceptions : " . count($exceptions));
    }
    $this->exceptions = [];
    foreach ($exceptions as $exception) {
      $date = new \DateTime($exception->recurrenceId, new \DateTimeZone('GMT'));
      $date->setTimezone($user_timezone);
      $recId = $date->format("Ymd");
      if (!in_array($recId, $_exceptions)) {
        $_exceptions[] = $recId;
      }
      $this->exceptions[$recId] = $exception;
      // MANTIS 3615: Alimenter le champ recurrence master
      // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
      if (!$exception->deleted) {
        $recurrence_master[] = $recId;
      }
    }
    // MANTIS 3615: Alimenter le champ recurrence master
    // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
    $this->setAttribute('RECURRENCE-MASTER', implode(',', array_unique($recurrence_master)));
    
    if (count($_exceptions) > 0)
      $this->objectmelanie->exceptions = implode(',', $_exceptions);
    else
      $this->objectmelanie->exceptions = '';
  }
  /**
   * Mapping exceptions field
   * 
   * @return Exception[] $exceptions
   * @ignore
   *
   */
  protected function getMapExceptions() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapExceptions()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (!isset($this->objectmelanie->exceptions) || $this->objectmelanie->exceptions == "")
      return [];
    
    if (!isset($this->exceptions)) {
      $this->exceptions = [];
    }
    $exceptions = explode(',', $this->objectmelanie->exceptions);
    if (count($exceptions) != count($this->exceptions)) {
      foreach ($exceptions as $exception) {
        // MANTIS 3881: Rendre la librairie moins sensible au format de données pour les exceptions
        if (strtotime($exception) === false)
          continue;
        $dateEx = new \DateTime($exception);
        if (!isset($this->exceptions[$dateEx->format("Ymd")])) {
          $ex = new Exception($this);
          $dateStart = new \DateTime($this->objectmelanie->start);
          $ex->recurrenceId = $dateEx->format("Y-m-d") . ' ' . $dateStart->format("H:i:s");
          $ex->uid = $this->objectmelanie->uid;
          $ex->calendar = $this->objectmelanie->calendar;
          $ex->load();
          $this->exceptions[$dateEx->format("Ymd")] = $ex;
        }
      }
    }
    
    return $this->exceptions;
  }
  /**
   * Ajoute une nouvelle exception à la liste sans avoir à recharger toutes les exceptions
   * 
   * @param Exception $exception          
   * @throws Exceptions\ObjectMelanieUndefinedException
   */
  public function addException($exception) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->addException()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    
    if (!isset($this->exceptions) && !is_array($this->exceptions)) {
      $this->exceptions = [];
    }
    // Get the TZ
    if (isset($this->calendarmelanie))
      $tz = $this->calendarmelanie->getTimezone();
    // Définition Timezone de l'utilisateur
    $user_timezone = new \DateTimeZone(!empty($tz) ? $tz : date_default_timezone_get());
    // Récupère les dates des exceptions
    $exceptions_dates = explode(',', $this->objectmelanie->exceptions);
    // MANTIS 3615: Alimenter le champ recurrence master
    // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
    $recurrence_master = explode(',', $this->getAttribute('RECURRENCE-MASTER'));
    // Gestion de l'exception
    $date = new \DateTime($exception->recurrenceId, new \DateTimeZone('GMT'));
    $date->setTimezone($user_timezone);
    $recId = $date->format("Ymd");
    $this->exceptions[$recId] = $exception;
    // MANTIS 3615: Alimenter le champ recurrence master
    // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
    if (!$exception->deleted && !in_array($recId, $recurrence_master)) {
      $recurrence_master[] = $recId;
    }
    // Ajoute l'exception à la liste des dates si elle n'est pas présente
    if (!in_array($recId, $exceptions_dates)) {
      $exceptions_dates[] = $recId;
      $this->objectmelanie->exceptions = implode(',', $exceptions_dates);
    }
    // MANTIS 3615: Alimenter le champ recurrence master
    // TODO: Supprimer cet ajout quand CalDAV utilisera l'ORM
    if (count($recurrence_master) > 0) {
      $this->setAttribute('RECURRENCE-MASTER', implode(',', array_unique($recurrence_master)));
    }
  }
  
  /**
   * Mapping attachments field
   * 
   * @param Attachments[] $exceptions          
   * @ignore
   *
   */
  protected function setMapAttachments($attachments) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapAttachments()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    $this->attachments = $attachments;
  }
  /**
   * Mapping attachments field
   * 
   * @return Attachments[] $exceptions
   * @ignore
   *
   */
  protected function getMapAttachments() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapAttachments()");
    if (!isset($this->objectmelanie))
      throw new Exceptions\ObjectMelanieUndefinedException();
    if (!isset($this->attachments)) {
      $this->attachments = [];
      // Récupération des pièces jointes binaires
      $attachment = new Attachment();
      $path = ConfigMelanie::ATTACHMENTS_PATH;
      $calendar = $this->getMapOrganizer()->calendar;
      if (!isset($calendar))
        $calendar = $this->objectmelanie->calendar;
      $path = str_replace('%c', $calendar, $path);
      $path = str_replace('%e', $this->objectmelanie->uid, $path);
      $attachment->path = $path;
      M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapAttachments() path : " . $attachment->path);
      // MANTIS 0004689: Mauvaise optimisation du chargement des pièces jointes
      $fields = ["id", "type", "path", "name", "modified", "owner"];
      $this->attachments = $attachment->getList($fields);
      
      // Récupération des pièces jointes URL
      $attach_uri = $this->getAttribute('ATTACH-URI');
      if (isset($attach_uri)) {
        foreach (explode('%%URI-SEPARATOR%%', $attach_uri) as $uri) {
          if (isset($uri) && $uri !== "") {
            M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapAttachments(): $uri");
            $attachment = new Attachment();
            $attachment->url = $uri;
            $attachment->type = Attachment::TYPE_URL;
            $this->attachments[] = $attachment;
          }
        }
      }
    }
    return $this->attachments;
  }
  /**
   * Map ics to current event
   * 
   * @ignore
   *
   */
  protected function setMapIcs($ics) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapsIcs()");
    \LibMelanie\Lib\ICSToEvent::Convert($ics, $this, $this->calendarmelanie, $this->usermelanie);
  }
  /**
   * Map current event to ics
   * 
   * @return string $ics
   * @ignore
   *
   */
  protected function getMapIcs() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapIcs()");
    return \LibMelanie\Lib\EventToICS::Convert($this, $this->calendarmelanie, $this->usermelanie, null, $this->ics_attachments, $this->ics_freebusy);
  }
  /**
   * Map current event to vcalendar
   * 
   * @return VObject\Component\VCalendar $vcalendar
   * @ignore
   *
   */
  protected function getMapVcalendar() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapVcalendar()");
    return \LibMelanie\Lib\EventToICS::getVCalendar($this, $this->calendarmelanie, $this->usermelanie, $this->ics_attachments, $this->ics_freebusy, $this->vcalendar);
  }
  /**
   * Set current vcalendar for event
   * 
   * @param VObject\Component\VCalendar $vcalendar          
   * @ignore
   *
   */
  protected function setMapVcalendar($vcalendar) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapVcalendar()");
    $this->vcalendar = $vcalendar;
  }
  /**
   * Map move param
   * 
   * @ignore
   *
   */
  protected function setMapMove($move) {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->setMapMove()");
    $this->move = $move;
  }
  /**
   * Map move param
   * 
   * @return string $move
   * @ignore
   *
   */
  protected function getMapMove() {
    M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class . "->getMapMove()");
    return $this->move;
  }
}