<?php
/**
 * Classe personnalisée pour Melanie2
 *
 * SabreDAVM2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 */
namespace Sabre\CardDAV\Backend;

/**
 * Implementing this interface adds CardDAV Melanie2 support to your carddav
 * server, as defined in rfc6638.
 *
 * @author Thomas Payen/PNE Annuaire et Messagerie (MEDDE)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
interface Melanie2Support extends BackendInterface {

  /**
   * Returns an addressbook for a principal.
   *
   * Every project is an array with the following keys:
   *  * id, a unique id that will be used by other functions to modify the
   *    addressbook. This can be the same as the uri or a database key.
   *  * uri. This is just the 'base uri' or 'filename' of the calendar.
   *  * principaluri. The owner of the calendar. Almost always the same as
   *    principalUri passed to this method.
   *
   * Furthermore it can contain webdav properties in clark notation. A very
   * common one is '{DAV:}displayname'.
   *
   * @param string $principalUri
   * @param string $addressBookId
   * @return array
   */
  function getAddressBookForPrincipal($principalUri, $addressBookId);


  /**
	 * Return an addressbook ctag for a principal and a calendar id
	 *
	 * Return the ctag string associate to the addressbook id
	 * Getting a ctag do not need an authenticate
	 *
	 * @param string $principalUri
	 * @param string $addressBookId
	 * @return string
	 */
	public function getAddressBookCTag($principalUri, $addressBookId);

}
