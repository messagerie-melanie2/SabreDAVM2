Installation de SabreDAV
========================

Pré-requis
----------

SabreDAV est écrit en PHP et nécessite une version suppérieure ou égale à PHP 5.4 pour fonctionner.  L'ORM nécessite d'être installée et configurée pour que SabreDAV fonctionne. En cas de configuration externe et multiple de l'ORM, le nom à utiliser est "sabredav"


Recupération de SabreDAVM2
--------------------------

La version de SabreDAV Mélanie2 peut être récupérée auprès du PNE Annuaire et Messagerie du MEDDE ou bien depuis les sources git.  La version peut ensuite être décompressée dans un répertoire.


Configuration de SabreDAV
-------------------------

La configuration de l'application se fait dans le répertoire config/. 

La configuration générale se fait dans le fichier config/config.php.  Les baseURI sont à modifier si le service n'est pas situé à la racine (ex: example.com/sabredav baseURI = "sabredav/caldav.php").  Pour un passage en production debugExceptions et useBrowser doivent être passés à false.  Pour le support de enableWebDavSync l'ORM 0.2.5.2 minimum est nécessaire.

La configuration des logs se fait dans le fichier config/logs.php 
