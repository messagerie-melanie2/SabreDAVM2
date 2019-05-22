<?php
/**
 * Fichier de gestion du backend CardDAV pour l'application SabreDAVM2
 * Utilise l'ORM M2 pour l'accès aux données Mélanie2
 * SabreDAVM2 Copyright © 2017 PNE Annuaire et Messagerie/MEDDE
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
namespace Sabre\CardDAV\Backend;

use 
		Sabre\VObject, 
		Sabre\CardDAV, 
		Sabre\DAV, 
		Sabre\DAV\Exception\Forbidden, 
		Sabre\HTTP\URLUtil, 
		Config\Config, 
		LibMelanie\Config\ConfigMelanie;

/**
 * LibM2 CardDAV backend
 * Utilisation de l'ORM Mélanie2 pour l'implémentation de ce backend
 * 
 * @copyright Copyright (C) 2007-2014 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class LibM2 extends AbstractBackend implements Melanie2Support {
	/**
	 * Authenfication backend
	 * 
	 * @var \Sabre\DAV\Auth\Backend\LibM2
	 */
	protected $authBackend;
	/**
	 * Liste des calendriers M2 de l'utilisateur
	 * 
	 * @var \LibMelanie\Api\Melanie2\Addressbook[]
	 */
	protected $addressbooks;
	/**
	 * Cache evenements courants, qui peuvent être utilises plusieurs fois
	 * 
	 * @var \LibMelanie\Api\Melanie2\Contact
	 */
	protected $cache_contacts;
	/**
	 * UID de l'utilisateur connecté (pas forcément le propriétaire de l'agenda)
	 * 
	 * @var string
	 */
	protected $current_user;
	/**
	 * UID de la boite partagée
	 * Utilisée dans le cas d'une connexion via un objet de partage
	 * Sinon doit être à null
	 * 
	 * @var string
	 */
	protected $current_balp;
	/**
	 * UID de l'objet de partage
	 * Utilisée dans le cas d'une connexion via un objet de partage
	 * Sinon doit être à null
	 * 
	 * @var string
	 */
	protected $current_share_object;
	/**
	 * Utilisateur courant dans un objet User de l'ORM M2
	 * 
	 * @var \LibMelanie\Api\Melanie2\User
	 */
	protected $user_melanie;
	/**
	 * Instance du serveur SabreDAV
	 * Permet d'accéder à la requête et à la réponse
	 * 
	 * @var \Sabre\DAV\Server
	 */
	protected $server;
	
	/**
	 * Creates the backend
	 * 
	 * @param \Sabre\DAV\Auth\Backend\LibM2 $authBackend        	
	 */
	public function __construct(\Sabre\DAV\Auth\Backend\LibM2 $authBackend) {
		$this->authBackend = $authBackend;
		$this->addressbooks = [];
		$this->cache_contacts = [];
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.__construct() current_user : " . $this->current_user);
	}
	/**
	 * Récupération de l'instance du serveur SabreDAV
	 * 
	 * @param \Sabre\DAV\Server $server        	
	 */
	public function setServer(\Sabre\DAV\Server $server) {
		$this->server = $server;
	}
	/**
	 * Définition du user courant
	 */
	protected function setCurrentUser() {
		if (! isset($this->current_user)) {
			list($basename, $this->current_user) = \Sabre\Uri\split($this->server->getPlugin('auth')->getCurrentPrincipal());
			// $this->current_user = $this->authBackend->getCurrentUser();
			if (strpos($this->current_user, '.-.') !== false) {
				// Gestion des boites partagées
				$this->current_share_object = $this->current_user;
				// MANTIS 3791: Gestion de l'authentification via des boites partagées
				$tmp = explode('.-.', $this->current_user, 2);
				$this->current_user = $tmp[0];
				if (isset($tmp[1])) {
					$this->current_balp = $tmp[1];
				}
			}
			$this->user_melanie = new \LibMelanie\Api\Melanie2\User();
			$this->user_melanie->uid = $this->current_user;
		}
	}
	/**
	 * Récupère l'utilisateur lié au principalURI
	 */
	protected function getUserFromPrincipalUri($principalUri) {
		$var = explode('/', $principalUri);
		$username = $var[1];
		// Si c'est une boite partagée, on s'authentifie sur l'utilisateur pas sur la bal
		if (strpos($username, '.-.') !== false) {
			// MANTIS 3791: Gestion de l'authentification via des boites partagées
			$tmp = explode('.-.', $username, 2);
			$username = $tmp[0];
		}
		return $username;
	}
	/**
	 * Charge la liste des carnets d'adresses de l'utilisateur connecté
	 * 
	 * @return \LibMelanie\Api\Melanie2\Addressbook[]
	 */
	public function loadUserAddressBooks() {
		$this->setCurrentUser();
		
		if (! isset($this->addressbooks) || count($this->addressbooks) === 0) {
			$this->addressbooks = $this->user_melanie->getSharedAddressbooks();
		}
		return $this->addressbooks;
	}
	/**
	 * Returns the list of addressbooks for a specific user.
	 * 
	 * @param string $principalUri        	
	 * @return array
	 */
	function getAddressBooksForUser($principalUri) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.getAddressBooksForUser($principalUri)");
		$this->setCurrentUser();
		$owner = $this->getUserFromPrincipalUri($principalUri);
		
		// Charge la liste des carnets d'adresses
		$this->loadUserAddressBooks();
		
		$addressBooks = [];
		
		foreach ( $this->addressbooks as $_addressbook ) {
			
			$addressBooks[] = [
					'id' => $_addressbook->id,
					'uri' => $_addressbook->id,
					'principaluri' => $principalUri,
					'{DAV:}displayname' => $_addressbook->name,
					'{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $_addressbook->name,
					'{http://calendarserver.org/ns/}getctag' => $this->getAddressBookCTag($principalUri, $_addressbook->id)
			];
		}
		
		return $addressBooks;
	}
	/**
	 * Returns an addressbook for a principal.
	 * Every project is an array with the following keys:
	 * * id, a unique id that will be used by other functions to modify the
	 * calendar. This can be the same as the uri or a database key.
	 * * uri. This is just the 'base uri' or 'filename' of the calendar.
	 * * principaluri. The owner of the calendar. Almost always the same as
	 * principalUri passed to this method.
	 * Furthermore it can contain webdav properties in clark notation. A very
	 * common one is '{DAV:}displayname'.
	 * 
	 * @param string $principalUri        	
	 * @param string $addressBookId        	
	 * @return array
	 */
	function getAddressBookForPrincipal($principalUri, $addressBookId) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.getAddressBookForPrincipal($principalUri, $addressBookId)");
		$this->setCurrentUser();
		
		if (! isset($this->addressbooks[$addressBookId])) {
			$this->addressbooks[$addressBookId] = new \LibMelanie\Api\Melanie2\Addressbook($this->user_melanie);
			$this->addressbooks[$addressBookId]->id = $addressBookId;
			if (! $this->addressbooks[$addressBookId]->load()) {
				unset($this->addressbooks[$addressBookId]);
			}
		}
		
		if (isset($this->addressbooks[$addressBookId])) {
			if (! $this->addressbooks[$addressBookId]->asRight(\LibMelanie\Config\ConfigMelanie::READ) && ! $this->addressbooks[$addressBookId]->asRight(\LibMelanie\Config\ConfigMelanie::FREEBUSY)) {
				// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
				throw new \Sabre\DAV\Exception\Forbidden();
			}
			return [
					'id' => $this->addressbooks[$addressBookId]->id,
					'uri' => $this->addressbooks[$addressBookId]->id,
					'principaluri' => $principalUri,
					'{DAV:}displayname' => $this->addressbooks[$addressBookId]->name,
					'{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $this->addressbooks[$addressBookId]->name,
					'{http://calendarserver.org/ns/}getctag' => $this->getAddressBookCTag($principalUri, $addressBookId)
			];
		}
		return null;
	}
	/**
	 * Return an addressbook ctag for a principal and a calendar id
	 * Return the ctag string associate to the addressbook id
	 * Getting a ctag do not need an authenticate
	 * 
	 * @param string $principalUri        	
	 * @param string $addressBookId        	
	 * @return string
	 */
	public function getAddressBookCTag($principalUri, $addressBookId) {
		// Pas de ctag si on n'est pas dans un propfind
		if ($this->server->httpRequest->getMethod() != "PROPFIND") {
			return null;
		}
		// Current User
		$this->setCurrentUser();
		if (! isset($this->addressbooks[$addressBookId])) {
			$this->addressbooks[$addressBookId] = new \LibMelanie\Api\Melanie2\Addressbook($this->user_melanie);
			$this->addressbooks[$addressBookId]->id = $addressBookId;
			if (! $this->addressbooks[$addressBookId]->load()) {
				unset($this->addressbooks[$addressBookId]);
			}
		}
		$ctag = null;
		if (isset($this->addressbooks[$addressBookId])) {
			$ctag = $this->addressbooks[$addressBookId]->ctag;
			if (is_null($ctag)) {
				$ctag = md5($addressBookId);
			}
		}
		return $ctag;
	}
	
	/**
	 * Updates properties for an address book.
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 * Read the PropPatch documenation for more info and examples.
	 * 
	 * @param string $addressBookId        	
	 * @param \Sabre\DAV\PropPatch $propPatch        	
	 * @return void
	 */
	function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.updateAddressBook($addressBookId)");
		return;
	}
	
	/**
	 * Creates a new address book
	 * 
	 * @param string $principalUri        	
	 * @param string $url
	 *        	Just the 'basename' of the url.
	 * @param array $properties        	
	 * @return void
	 */
	function createAddressBook($principalUri, $url, array $properties) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.createAddressBook($principalUri, $url)");
		return null;
	}
	
	/**
	 * Deletes an entire addressbook and all its contents
	 * 
	 * @param int $addressBookId        	
	 * @return void
	 */
	function deleteAddressBook($addressBookId) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.deleteAddressBook($addressBookId)");
		return;
	}
	
	/**
	 * Returns all cards for a specific addressbook id.
	 * This method should return the following properties for each card:
	 * * carddata - raw vcard data
	 * * uri - Some unique url
	 * * lastmodified - A unix timestamp
	 * It's recommended to also return the following properties:
	 * * etag - A unique etag. This must change every time the card changes.
	 * * size - The size of the card in bytes.
	 * If these last two properties are provided, less time will be spent
	 * calculating them. If they are specified, you can also ommit carddata.
	 * This may speed up certain requests, especially with large cards.
	 * 
	 * @param mixed $addressBookId        	
	 * @return array
	 */
	function getCards($addressBookId) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CardDAVBackend] LibM2.getCards($addressBookId)");
			// User courant
		$this->setCurrentUser();
		// Cherche si le calendrier est présent en mémoire
		if (isset($this->addressbooks) && isset($this->addressbooks[$addressBookId])) {
			$loaded = true;
		} else {
			$this->addressbooks[$addressBookId] = new \LibMelanie\Api\Melanie2\Addressbook($this->user_melanie);
			$this->addressbooks[$addressBookId]->id = $addressBookId;
			if ($this->addressbooks[$addressBookId]->load()) {
				$loaded = true;
			} else {
				unset($this->addressbooks[$addressBookId]);
				$loaded = false;
			}
		}
		$result = [];
		if ($loaded) {
			if (! $this->addressbooks[$addressBookId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
				// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
				throw new \Sabre\DAV\Exception\Forbidden();
			}
			$this->cache_contacts = $this->addressbooks[$addressBookId]->getAllContacts();
			foreach ( $this->cache_contacts as $_contact ) {
				$contact = [
						'id' => $_contact->uid,
						'uri' => urlencode($_contact->uid) . '.vcf',
						'lastmodified' => $_contact->modified,
						'etag' => '"' . md5($_contact->modified) . '"'
				];
				if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
					$contact['carddata'] = $_contact->vcard;
					$contact['size'] = strlen($contact['carddata']);
				}
				$result[] = $contact;
			}
		}
		return $result;
	}
	
	/**
	 * Returns a specfic card.
	 * The same set of properties must be returned as with getCards. The only
	 * exception is that 'carddata' is absolutely required.
	 * If the card does not exist, you must return false.
	 * 
	 * @param mixed $addressBookId        	
	 * @param string $cardUri        	
	 * @return array
	 */
	function getCard($addressBookId, $cardUri) {
		if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG))
			\Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObject($calendarId,$objectUri)");
			// User courant
		$this->setCurrentUser();
		// Cherche si le calendrier est présent en mémoire
		if (isset($this->addressbooks) && isset($this->addressbooks[$addressBookId])) {
			$loaded = true;
		} else {
			$this->addressbooks[$addressBookId] = new \LibMelanie\Api\Melanie2\Addressbook($this->user_melanie);
			$this->addressbooks[$addressBookId]->id = $addressBookId;
			if ($this->addressbooks[$addressBookId]->load()) {
				$loaded = true;
			} else {
				unset($this->addressbooks[$addressBookId]);
				$loaded = false;
			}
		}
		$result = null;
		if ($loaded) {
			if (! $this->addressbooks[$addressBookId]->asRight(\LibMelanie\Config\ConfigMelanie::READ)) {
				// MANTIS 0004469: Générer des messages d'erreur quand l'utilisateur n'a pas les droits
				throw new \Sabre\DAV\Exception\Forbidden();
			}
			$contact_uid = str_replace('.vcf', '', urldecode($cardUri));
			// Cherche si l'évènement n'est pas déjà dans le cache
			if (! isset($this->cache_contacts[$contact_uid . $addressBookId])) {
				$contact = new \LibMelanie\Api\Melanie2\Contact($this->user_melanie, $this->addressbooks[$addressBookId]);
				$contact->uid = $contact_uid;
				if ($contact->load()) {
					$this->cache_contacts[$event_uid . $addressBookId] = $contact;
				}
			}
			// Si l'évènement existe on retourne le resultat
			if (isset($this->cache_contacts[$contact_uid . $addressBookId]) && $this->cache_contacts[$contact_uid . $addressBookId]->exists()) {
				$result = [
						'id' => $this->cache_contacts[$contact_uid . $addressBookId]->uid,
						'uri' => urlencode($this->cache_contacts[$contact_uid . $addressBookId]->uid) . '.vcf',
						'lastmodified' => $this->cache_contacts[$contact_uid . $addressBookId]->modified,
						'etag' => '"' . md5($this->cache_contacts[$contact_uid . $addressBookId]->modified) . '"',
				];
				if ($this->server->httpRequest->getMethod() != 'PROPFIND') {
					$result['carddata'] = $this->cache_contacts[$contact_uid . $addressBookId]->vcard;
					$result['size'] = strlen($result['carddata']);
				}
			}
		}
		// if (\Lib\Log\Log::isLvl(\Lib\Log\Log::DEBUG)) \Lib\Log\Log::l(\Lib\Log\Log::DEBUG, "[CalDAVBackend] LibM2.getCalendarObject($calendarId,$objectUri) : " . var_export($result, true));
		return $result;
		
		$stmt = $this->pdo->prepare('SELECT id, carddata, uri, lastmodified, etag, size FROM ' . $this->cardsTableName . ' WHERE addressbookid = ? AND uri = ? LIMIT 1');
		$stmt->execute([
				$addressBookId,
				$cardUri
		]);
		
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if (! $result)
			return false;
		
		$result['etag'] = '"' . $result['etag'] . '"';
		return $result;
	}
	
	/**
	 * Returns a list of cards.
	 * This method should work identical to getCard, but instead return all the
	 * cards in the list as an array.
	 * If the backend supports this, it may allow for some speed-ups.
	 * 
	 * @param mixed $addressBookId        	
	 * @param array $uris        	
	 * @return array
	 */
	function getMultipleCards($addressBookId, array $uris) {
		$query = 'SELECT id, uri, lastmodified, etag, size, carddata FROM ' . $this->cardsTableName . ' WHERE addressbookid = ? AND uri IN (';
		// Inserting a whole bunch of question marks
		$query .= implode(',', array_fill(0, count($uris), '?'));
		$query .= ')';
		
		$stmt = $this->pdo->prepare($query);
		$stmt->execute(array_merge([
				$addressBookId
		], $uris));
		$result = [];
		while ( $row = $stmt->fetch(\PDO::FETCH_ASSOC) ) {
			$row['etag'] = '"' . $row['etag'] . '"';
			$result[] = $row;
		}
		return $result;
	}
	
	/**
	 * Creates a new card.
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 * It is possible to return an ETag from this method. This ETag is for the
	 * newly created resource, and must be enclosed with double quotes (that
	 * is, the string itself must contain the double quotes).
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 * If you don't return an ETag, you can just return null.
	 * 
	 * @param mixed $addressBookId        	
	 * @param string $cardUri        	
	 * @param string $cardData        	
	 * @return string|null
	 */
	function createCard($addressBookId, $cardUri, $cardData) {
		$stmt = $this->pdo->prepare('INSERT INTO ' . $this->cardsTableName . ' (carddata, uri, lastmodified, addressbookid, size, etag) VALUES (?, ?, ?, ?, ?, ?)');
		
		$etag = md5($cardData);
		
		$stmt->execute([
				$cardData,
				$cardUri,
				time(),
				$addressBookId,
				strlen($cardData),
				$etag
		]);
		
		$this->addChange($addressBookId, $cardUri, 1);
		
		return '"' . $etag . '"';
	}
	
	/**
	 * Updates a card.
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 * It is possible to return an ETag from this method. This ETag should
	 * match that of the updated resource, and must be enclosed with double
	 * quotes (that is: the string itself must contain the actual quotes).
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 * If you don't return an ETag, you can just return null.
	 * 
	 * @param mixed $addressBookId        	
	 * @param string $cardUri        	
	 * @param string $cardData        	
	 * @return string|null
	 */
	function updateCard($addressBookId, $cardUri, $cardData) {
		$stmt = $this->pdo->prepare('UPDATE ' . $this->cardsTableName . ' SET carddata = ?, lastmodified = ?, size = ?, etag = ? WHERE uri = ? AND addressbookid =?');
		
		$etag = md5($cardData);
		$stmt->execute([
				$cardData,
				time(),
				strlen($cardData),
				$etag,
				$cardUri,
				$addressBookId
		]);
		
		$this->addChange($addressBookId, $cardUri, 2);
		
		return '"' . $etag . '"';
	}
	
	/**
	 * Deletes a card
	 * 
	 * @param mixed $addressBookId        	
	 * @param string $cardUri        	
	 * @return bool
	 */
	function deleteCard($addressBookId, $cardUri) {
		$stmt = $this->pdo->prepare('DELETE FROM ' . $this->cardsTableName . ' WHERE addressbookid = ? AND uri = ?');
		$stmt->execute([
				$addressBookId,
				$cardUri
		]);
		
		$this->addChange($addressBookId, $cardUri, 3);
		
		return $stmt->rowCount() === 1;
	}
}