Version
=======
0.2
Build 20150311143648

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

A faire
=======
 - Mise en place des logs
 - Optimiser les requêtes de création (lecture dans la base de données inutile), le mieux étant de ne pas différencier création de modification, l'ORM s'en occupe
 - Gestion des COPY/MOVE par le serveur CalDAV. Une fonctionnalité WebDAV permet de l'implémenter a voir si cela peut convenir (peu probable)
 - Gestion des authentifications via les boites partagées (modification de l'organisateur)
 - Ajouter dans la description le creator id s'il est différent du propriétaire du calendrier
 - Mise en place des URL sans le rewrite d'apache