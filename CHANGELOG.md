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
 - Synchronisation des contacts via CalDAV
 - Support de la création/suppression des Carnets d'adresses, Calendriers et Listes de tâches
 - Supporter l'abonnement aux calendriers, carnets d'adresses depuis M2Web
 - Mettre en place une synchronisation spécifique pour les listes de tâches
 - Mettre en place une synchronisation spécifique pour les calendriers sans listes de tâches
 
CalDAV - Liste des changements
==============================

CalDAV - 0.6.1 (Pas encore publiée) [ Afficher les bogues ]
---
- 0004933: [SabreDAV] Si l'organisateur est vide, ne pas considérer que c'est une réunion (thomas.payen) - résolu.
- 0004932: [SabreDAV] Problème d'affichage des disponibilités (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.6.0.1 (Publiée 22/02/2018) [ Afficher les bogues ]
---
- 0004878: [SabreDAV] HOTFIX: Problème dans la récupération des freebusy (thomas.payen) - résolu.

[1 bogue]

CalDAV - 0.6 (Publiée 20/02/2018) [ Afficher les bogues ]
---
- 0004875: [SabreDAV] La détermination du isSync ne fonctionne pas sur les synchros courantes (thomas.payen) - résolu.
- 0004872: [SabreDAV] Mise à jour de version de SabreDAV (thomas.payen) - résolu.
- 0004860: [SabreDAV] Mauvaise gestion des participants lors d'organisateur externe (thomas.payen) - résolu.
- 0004843: [SabreDAV] Comportement de l'organisateur (thomas.payen) - résolu.
- 0004849: [SabreDAV] Ajouter les UID dans les logs des méthodes REPORT (thomas.payen) - résolu.
- 0004858: [SabreDAV] Erreur de timezone dans la récurrence (thomas.payen) - résolu.
- 0004836: [SabreDAV] Problème de timezone du recurrence id lors d'un passage en journée entière (thomas.payen) - résolu.
- 0004837: [SabreDAV] L'adresse mail associé à un agenda n'est potentiellement pas la bonne (thomas.payen) - résolu.

[8 bogues]

CalDAV - 0.5.9 (Publiée 18/01/2018) [ Afficher les bogues ]
---
- 0004824: [SabreDAV] Problème d'optimisation des requêtes sync-collection (thomas.payen) - résolu.

[1 bogue]

CalDAV - 0.5.8 (Publiée 20/12/2017) [ Afficher les bogues ]
---
- 0004788: [SabreDAV] Problème dans le calcul de la taille/hash des pièces jointes (thomas.payen) - résolu.
- 0004725: [SabreDAV] Trouver un moyen de gérer les événements avec "/" dans l'uid (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.5.7 (Publiée 18/12/2017) [ Afficher les bogues ]
---
- 0004761: [SabreDAV] Le serveur sabredav ne retourne pas les bonnes valeurs de privilèges (thomas.payen) - résolu.
- 0004775: [SabreDAV] Augmenter la plage de retour du WebDAVSync (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.5.6 (Publiée 05/12/2017) [ Afficher les bogues ]
---
- 0004763: [SabreDAV] Utilisation de l'ORM v0.4 (thomas.payen) - résolu.

[1 bogue]

CalDAV - 0.5.5 (Publiée 13/11/2017) [ Afficher les bogues ]
---
- 0004697: [SabreDAV] Timeout PHP lors d'une modification d'événement (thomas.payen) - résolu.
- 0004741: [SabreDAV] Problème d'organisateur multiple dans des récurrences avec exceptions (thomas.payen) - résolu.
- 0004739: [SabreDAV] Ne pas retourner les événements en 1970-01-01 (thomas.payen) - résolu.
- 0004732: [SabreDAV] Filtrer les exceptions d'authentification (thomas.payen) - résolu.
- 0004727: [SabreDAV] Ne pas retourner les occurrences sans date (thomas.payen) - résolu.
- 0004728: [SabreDAV] Problème de génération du recurrence id (thomas.payen) - résolu.

[6 bogues]

CalDAV - 0.5.4 (Publiée 02/11/2017) [ Afficher les bogues ]
---
- 0004720: [SabreDAV] Problème de conversion d'une alarme supérieure à 1 semaine (thomas.payen) - résolu.
- 0004704: [SabreDAV] Le s'inviter en déclinant ne fonctionne pas (thomas.payen) - résolu.
- 0004707: [SabreDAV] Bug dans la lib Sabre/VObject lors d'une conversion de DateTime (thomas.payen) - résolu.
- 0004705: [SabreDAV] Le trombonne de la pièce jointe n'apparait pas (thomas.payen) - résolu.
- 0004703: [SabreDAV] Log des exceptions SabreDAV (thomas.payen) - résolu.
- 0004694: [SabreDAV] Forcer le Timezone quand il est différent de celui enregistré (thomas.payen) - résolu.
- 0004685: [SabreDAV] [SyncToken] Problème lorsque l'on supprime puis reaccepte l'événement d'une invitation (thomas.payen) - résolu.
- 0004691: [SabreDAV] Ajouter un shutdown pour catcher les erreurs (thomas.payen) - résolu.
- 0004681: [SabreDAV] Améliorer le If-Match ETag (thomas.payen) - résolu.

[9 bogues]

CalDAV - 0.5.3 (Publiée 02/10/2017) [ Afficher les bogues ]
---
- 0004676: [SabreDAV] Les exceptions d'un FAKED MASTER n'ont pas de owner (thomas.payen) - résolu.
- 0004599: [SabreDAV] Problème de décodage de l'UID dans la méthode REPORT (thomas.payen) - résolu.
- 0004663: [SabreDAV] Problème de création d'une exception d'une récurrence non présente (thomas.payen) - résolu.
- 0004664: [SabreDAV] La recherche If-Match ne marche pas pour un Faked-Master (thomas.payen) - résolu.
- 0004666: [SabreDAV] SyncToken modifier les uid en RECURRENCE-ID (thomas.payen) - résolu.

[5 bogues]

CalDAV - 0.5.2 (Publiée 17/04/2017) [ Afficher les bogues ]
---
- 0004631: [SabreDAV] Nettoyer les données des pièces jointes après leur lecture (thomas.payen) - résolu.
- 0004632: [SabreDAV] Augmenter la limite mémoire de PHP (thomas.payen) - résolu.
- 0004617: [SabreDAV] Problème de récupération des récurrences en SyncToken (thomas.payen) - résolu.
- 0004584: [SabreDAV] Erreur lorsqu'un ICS accepté n'a pas de fin mais une durée (thomas.payen) - résolu.
- 0004590: [SabreDAV] Problème de gestion des ICS en GMT (thomas.payen) - résolu.
- 0004541: [SabreDAV] Plages récurrences mensuelles et toutes les x occurrences limitées à une date de fin n'affiche pas l'évt à la date de fin (thomas.payen) - résolu.
- 0004538: [SabreDAV] Utiliser l'ORM en mode vendor (thomas.payen) - résolu.
- 0004564: [SabreDAV] Remplacement du nom et du prénom par l'adresse mail à l'enregistrement d'un événement (thomas.payen) - résolu.
- 0004531: [SabreDAV] lors de l'invitation des participants on ne voit pas les plages occupés en 7.1T2 (thomas.payen) - résolu.

[9 bogues]

CalDAV - 0.5.1 (Publiée 10/04/2017) [ Afficher les bogues ]
---
- 0004529: [SabreDAV] Statut "libre" non conservé (thomas.payen) - résolu.
- 0004520: [SabreDAV] Mettre en cache la liste de tâche dans le backend (thomas.payen) - résolu.
- 0004525: [SabreDAV] Ne pas recherche le principal dans le LDAP pour un sync (thomas.payen) - résolu.
- 0004518: [SabreDAV] Pas d'authentification pour le WebDAV-Sync (thomas.payen) - résolu.

[4 bogues]

CalDAV - 0.5 (Publiée 06/04/2017) [ Afficher les bogues ]
---
- 0004524: [SabreDAV] Problème lorsque le SyncToken est à 0 (thomas.payen) - résolu.
- 0004484: [SabreDAV] Implémentation de WebDAV-Sync (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.4.3 (Publiée 27/03/2017) [ Afficher les bogues ]
---
- 0004508: [SabreDAV] Mettre en place de logrotate pour SabreDAV (thomas.payen) - résolu.
- 0004509: [SabreDAV] Une tâche avec alarme génère une erreur 500 (thomas.payen) - résolu.
- 0004506: [SabreDAV] Une occurrence journée entière supprimée n'apparait pas supprimé dans SabreDAV (thomas.payen) - résolu.
- 0004493: [SabreDAV] Ajouter "" dans le ctag (thomas.payen) - résolu.

[4 bogues]

CalDAV - 0.4.2 (Publiée 21/02/2017) [ Afficher les bogues ]
---
- 0004477: [SabreDAV] Gérer le droit afficher (thomas.payen) - résolu.
- 0004469: [SabreDAV] Générer des messages d'erreur quand l'utilisateur n'a pas les droits (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.4.1 (Publiée 21/02/2017) [ Afficher les bogues ]
---
- 0004472: [SabreDAV] Mise en place du déploiement (thomas.payen) - résolu.
- 0004470: [SabreDAV] Problème de création d'un participant vide (thomas.payen) - résolu.

[2 bogues]

CalDAV - 0.4 (Publiée 19/11/2015) [ Afficher les bogues ]
---
- 0003773: [SabreDAV] Serveur SabreDAV et ORM M2 (thomas.payen) - résolu.
- 0004467: [SabreDAV] Problème d'invitation (thomas.payen) - résolu.
- 0004315: [SabreDAV] Mise à jour du serveur SabreDAV (thomas.payen) - résolu.

[3 bogues]

CalDAV - 0.3 (Publiée 19/08/2015) [ Afficher les bogues ]
---
- 0003790: [SabreDAV] Gestion des COPY/MOVE (thomas.payen) - résolu.
- 0003792: [SabreDAV] Pour les agendas partagés ajouter le creator id dans la description (thomas.payen) - résolu.
- 0003825: [SabreDAV] Les pièces jointes URL remontées au serveur sont supprimées (thomas.payen) - résolu.
- 0004014: [SabreDAV] Passer en Sabre/DAV 3.0.X (thomas.payen) - résolu.
- 0004017: [SabreDAV] Supporter le format DAViCal pour les principals (thomas.payen) - résolu.

[5 bogues]

CalDAV - 0.2 (Publiée 11/03/2015) [ Afficher les bogues ]
---
- 0003791: [SabreDAV] Gestion de l'authentification via des boites partagées (thomas.payen) - résolu.
- 0003789: [SabreDAV] Gestion des logs (thomas.payen) - résolu.
- 0003788: [SabreDAV] Problème avec les évènments journée entière (thomas.payen) - résolu.

[3 bogues]
