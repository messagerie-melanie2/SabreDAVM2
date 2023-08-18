Mise en place d'un serveur SabreDAV avec backend ORM M2

Fonctionnalités
===============
 - Fastpropfind : Requête PROPFIND spécifique utilisant un traitement optimisé (pas d'authentification, traitement réduit)
 - Schedule calendar : désactivation du mode calendar-auto-schedule pour éviter les problèmes sur les URL INBOX/OUTBOX. Permet de lire les freebusy
 - Création/Modification/Suppression d'évènement
 - Gestion des invitations et participants
 - Gestion des récurrences
 - Gestion des pièces jointes binaires et URL
 - Gestion des tâches dans le calendrier principal
 - Gestion des COPY/MOVE par le serveur CalDAV.
 - Gestion des authentifications via les boites partagées (modification de l'organisateur)
 - Ajouter dans la description le creator id s'il est différent du propriétaire du calendrier

A faire
=======
 - Synchronisation des contacts via CardDAV
 - Support de la création/suppression des Carnets d'adresses, Calendriers et Listes de tâches
 - Supporter l'abonnement aux calendriers, carnets d'adresses depuis M2Web
 - Mettre en place une synchronisation spécifique pour les listes de tâches
 - Mettre en place une synchronisation spécifique pour les calendriers sans listes de tâches
 
CalDAV - Liste des changements
==============================

CalDAV - 0.7.4
==============
- MR 3 fix server ldap for auth 

CalDAV - 0.7.3
==============
- 0007635: Problème d'alarme sur les tâches

CalDAV - 0.7
============
- 0007588: [SabreDAV] Erreur lors de la suppression d'un contact
- 0007539: [SabreDAV] Mécanisme de rattrapage de cache régulier
- 0007517: [SabreDAV] Ajouter les informations de l'utilisateur lors de la création d'un événement
- 0007474: [SabreDAV] Mise en place de drivers pour la gestion par ministère

CalDAV - 0.6.11
===============
- 0006748: [SabreDAV CardDAV] Problème dans la création d'un contact
- 0006726: [SabreDAV CardDAV] Lister tous les contacts et les groupes dans le backend

CalDAV - 0.6.10 (Publiée le 23/03/2022)
=======================================
- 0006193: [SabreDAV] Rendre configurable le namespace utilisé pour les Api de l'ORM
- 0006344: [SabreDAV] Dans le Backend M2 ajouter les calendars prop dans la méthode getCalendar

[2 bogues]

CalDAV - 0.6.9 (Publiée 29/04/2021)
===================================
- 0006231: [SabreDAV] Gestion des ':' dans les uid
- 0006160: [SabreDAV] Problème avec le backend CardDAV sur les REPORT multiget
- 0006139: [SabreDAV] Utiliser le realuid pour le REPORT
- 0006132: [SabreDAV] Catcher les exceptions dans les logs
- 0006114: [SabreDAV] Problème de performance sur le pliage de ligne dans VObject
- 0006083: [SabreDAV] Passage en ORM 0.6.1.X

[6 bogues]

CalDAV - 0.6.8 (Publiée 18/10/2019)
===================================
- 0005901: [SabreDAV] Ne pas faire un save si aucun champ n'a changé
- 0005414: [SabreDAV] récurrences journée hebdo sans date de fin et limitée à une date créées depuis MélWeb ou zpush commencent un jour plus tôt dans

[2 bogues]

CalDAV - 0.6.7 (Publiée 14/10/2019)
===================================
- 0005411: [SabreDAV] Support de CardDAV
- 0005397: [SabreDAV] Problème dans les recurrence id sur des timezones différents d'Europe/Paris
- 0005311: [SabreDAV] Fix: Problème de suppression d'événement recurrent avec occurrences

[3 bogues]

CalDAV - 0.6.6 (Publiée 13/02/2019)
===================================
- 0005239: [SabreDAV] Erreur lors de l'affichage en mode browser
- 0005227: [SabreDAV] Problème pour désactiver une alarme sur un événement récurrent

[2 bogues]

CalDAV - 0.6.5 (Publiée 23/10/2018)
---
- 0005134: [SabreDAV] Problème de bouclage sur des événements créés
- 0005116: [SabreDAV] Mauvais affichage des disponibilités

[2 bogues]

CalDAV - 0.6.4 (Publiée 25/09/2018)
---
- 0005087: [SabreDAV] Optimisation des requêtes SQL
- 0005067: [SabreDAV] Mettre en place le en attente

[2 bogues]

CalDAV - 0.6.3 (Publiée 20/08/2018)
---
- 0005077: [SabreDAV] Mettre en place un mecanisme de blocage des URL par utilisateur
- 0005076: [SabreDAV] Ajouter le User Agent dans les logs debug
- 0005063: [SabreDAV] Une fois que l'organisateur est défini, ne plus permettre au client de le modifier
- 0005050: [SabreDAV] Problème avec l'ajout d'un participant dans une occurrence depuis l'agenda pool
- 0005030: [SabreDAV] La connexion via un objet de partage pose des problèmes sur les événements privés
- 0005006: [SabreDAV] La gestion des réponses aux invitations pour les pools de secrétaires n'est pas satisfaisante
- 0005008: [SabreDAV] Ajout d'un X-M2-ORG-MAIL dans le champ ORGANIZER
- 0005009: [SabreDAV] Optimiser la gestion des droits

[8 bogues]

CalDAV - 0.6.2 (Publiée 14/06/2018)
---
- 0005003: [SabreDAV] Problème d'exception avec un organizer avec majuscule
- 0004985: [SabreDAV] Pour les non participants, forcer le ACCEPTED et le RSVP à FALSE
- 0004984: [SabreDAV] Les informations d'un owner en .-. ne sont pas correctement récupérées
- 0004977: [SabreDAV] Lightning ne gère pas correctement les différences entre current user et owner du calendrier
- 0004968: [SabreDAV] Lorsque l'on crée une invitation dans l'agenda de quelqu'un d'autre, l'ajouter aux participants
- 0004967: [SabreDAV] Cab SG: une modification d'occurrence ne semble pas être en .-.
- 0004946: [SabreDAV] Le VObject ne traite pas correctement le VFREEBUSY lors d'un Faked Master
- 0004872: [SabreDAV] Mise à jour de version de SabreDAV
- 0004938: [SabreDAV] Problème dans le chargement des disponibilités d'un événement récurrent sans master

[9 bogues]

CalDAV - 0.6.1 (Publiée 12/04/2018)
---
- 0004933: [SabreDAV] Si l'organisateur est vide, ne pas considérer que c'est une réunion
- 0004932: [SabreDAV] Problème d'affichage des disponibilités

[2 bogues]

CalDAV - 0.6.0.1 (Publiée 22/02/2018)
---
- 0004878: [SabreDAV] HOTFIX: Problème dans la récupération des freebusy

[1 bogue]

CalDAV - 0.6 (Publiée 20/02/2018)
---
- 0004875: [SabreDAV] La détermination du isSync ne fonctionne pas sur les synchros courantes
- 0004872: [SabreDAV] Mise à jour de version de SabreDAV
- 0004860: [SabreDAV] Mauvaise gestion des participants lors d'organisateur externe
- 0004843: [SabreDAV] Comportement de l'organisateur
- 0004849: [SabreDAV] Ajouter les UID dans les logs des méthodes REPORT
- 0004858: [SabreDAV] Erreur de timezone dans la récurrence
- 0004836: [SabreDAV] Problème de timezone du recurrence id lors d'un passage en journée entière
- 0004837: [SabreDAV] L'adresse mail associé à un agenda n'est potentiellement pas la bonne

[8 bogues]

CalDAV - 0.5.9 (Publiée 18/01/2018)
---
- 0004824: [SabreDAV] Problème d'optimisation des requêtes sync-collection

[1 bogue]

CalDAV - 0.5.8 (Publiée 20/12/2017)
---
- 0004788: [SabreDAV] Problème dans le calcul de la taille/hash des pièces jointes
- 0004725: [SabreDAV] Trouver un moyen de gérer les événements avec "/" dans l'uid

[2 bogues]

CalDAV - 0.5.7 (Publiée 18/12/2017)
---
- 0004761: [SabreDAV] Le serveur sabredav ne retourne pas les bonnes valeurs de privilèges
- 0004775: [SabreDAV] Augmenter la plage de retour du WebDAVSync

[2 bogues]

CalDAV - 0.5.6 (Publiée 05/12/2017)
---
- 0004763: [SabreDAV] Utilisation de l'ORM v0.4

[1 bogue]

CalDAV - 0.5.5 (Publiée 13/11/2017)
---
- 0004697: [SabreDAV] Timeout PHP lors d'une modification d'événement
- 0004741: [SabreDAV] Problème d'organisateur multiple dans des récurrences avec exceptions
- 0004739: [SabreDAV] Ne pas retourner les événements en 1970-01-01
- 0004732: [SabreDAV] Filtrer les exceptions d'authentification
- 0004727: [SabreDAV] Ne pas retourner les occurrences sans date
- 0004728: [SabreDAV] Problème de génération du recurrence id

[6 bogues]

CalDAV - 0.5.4 (Publiée 02/11/2017)
---
- 0004720: [SabreDAV] Problème de conversion d'une alarme supérieure à 1 semaine
- 0004704: [SabreDAV] Le s'inviter en déclinant ne fonctionne pas
- 0004707: [SabreDAV] Bug dans la lib Sabre/VObject lors d'une conversion de DateTime
- 0004705: [SabreDAV] Le trombonne de la pièce jointe n'apparait pas
- 0004703: [SabreDAV] Log des exceptions SabreDAV
- 0004694: [SabreDAV] Forcer le Timezone quand il est différent de celui enregistré
- 0004685: [SabreDAV] [SyncToken] Problème lorsque l'on supprime puis reaccepte l'événement d'une invitation
- 0004691: [SabreDAV] Ajouter un shutdown pour catcher les erreurs
- 0004681: [SabreDAV] Améliorer le If-Match ETag

[9 bogues]

CalDAV - 0.5.3 (Publiée 02/10/2017)
---
- 0004676: [SabreDAV] Les exceptions d'un FAKED MASTER n'ont pas de owner
- 0004599: [SabreDAV] Problème de décodage de l'UID dans la méthode REPORT
- 0004663: [SabreDAV] Problème de création d'une exception d'une récurrence non présente
- 0004664: [SabreDAV] La recherche If-Match ne marche pas pour un Faked-Master
- 0004666: [SabreDAV] SyncToken modifier les uid en RECURRENCE-ID

[5 bogues]

CalDAV - 0.5.2 (Publiée 17/04/2017)
---
- 0004631: [SabreDAV] Nettoyer les données des pièces jointes après leur lecture
- 0004632: [SabreDAV] Augmenter la limite mémoire de PHP
- 0004617: [SabreDAV] Problème de récupération des récurrences en SyncToken
- 0004584: [SabreDAV] Erreur lorsqu'un ICS accepté n'a pas de fin mais une durée
- 0004590: [SabreDAV] Problème de gestion des ICS en GMT
- 0004541: [SabreDAV] Plages récurrences mensuelles et toutes les x occurrences limitées à une date de fin n'affiche pas l'évt à la date de fin
- 0004538: [SabreDAV] Utiliser l'ORM en mode vendor
- 0004564: [SabreDAV] Remplacement du nom et du prénom par l'adresse mail à l'enregistrement d'un événement
- 0004531: [SabreDAV] lors de l'invitation des participants on ne voit pas les plages occupés en 7.1T2

[9 bogues]

CalDAV - 0.5.1 (Publiée 10/04/2017)
---
- 0004529: [SabreDAV] Statut "libre" non conservé
- 0004520: [SabreDAV] Mettre en cache la liste de tâche dans le backend
- 0004525: [SabreDAV] Ne pas recherche le principal dans le LDAP pour un sync
- 0004518: [SabreDAV] Pas d'authentification pour le WebDAV-Sync

[4 bogues]

CalDAV - 0.5 (Publiée 06/04/2017)
---
- 0004524: [SabreDAV] Problème lorsque le SyncToken est à 0
- 0004484: [SabreDAV] Implémentation de WebDAV-Sync

[2 bogues]

CalDAV - 0.4.3 (Publiée 27/03/2017)
---
- 0004508: [SabreDAV] Mettre en place de logrotate pour SabreDAV
- 0004509: [SabreDAV] Une tâche avec alarme génère une erreur 500
- 0004506: [SabreDAV] Une occurrence journée entière supprimée n'apparait pas supprimé dans SabreDAV
- 0004493: [SabreDAV] Ajouter "" dans le ctag

[4 bogues]

CalDAV - 0.4.2 (Publiée 21/02/2017)
---
- 0004477: [SabreDAV] Gérer le droit afficher
- 0004469: [SabreDAV] Générer des messages d'erreur quand l'utilisateur n'a pas les droits

[2 bogues]

CalDAV - 0.4.1 (Publiée 21/02/2017)
---
- 0004472: [SabreDAV] Mise en place du déploiement
- 0004470: [SabreDAV] Problème de création d'un participant vide

[2 bogues]

CalDAV - 0.4 (Publiée 19/11/2015)
---
- 0003773: [SabreDAV] Serveur SabreDAV et ORM M2
- 0004467: [SabreDAV] Problème d'invitation
- 0004315: [SabreDAV] Mise à jour du serveur SabreDAV

[3 bogues]

CalDAV - 0.3 (Publiée 19/08/2015)
---
- 0003790: [SabreDAV] Gestion des COPY/MOVE
- 0003792: [SabreDAV] Pour les agendas partagés ajouter le creator id dans la description
- 0003825: [SabreDAV] Les pièces jointes URL remontées au serveur sont supprimées
- 0004014: [SabreDAV] Passer en Sabre/DAV 3.0.X
- 0004017: [SabreDAV] Supporter le format DAViCal pour les principals

[5 bogues]

CalDAV - 0.2 (Publiée 11/03/2015)
---
- 0003791: [SabreDAV] Gestion de l'authentification via des boites partagées
- 0003789: [SabreDAV] Gestion des logs
- 0003788: [SabreDAV] Problème avec les évènments journée entière

[3 bogues]
