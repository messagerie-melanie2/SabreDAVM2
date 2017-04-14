Version
=======
0.5.2
Build 201704141136

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

CalDAV - 0.5.2 (Pas encore publiée) [ Afficher les bogues ]
---
- 0004531: [SabreDAV] lors de l'invitation des participants on ne voit pas les plages occupés en 7.1T2 (thomas.payen) - résolu.

[1 bogue]

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
