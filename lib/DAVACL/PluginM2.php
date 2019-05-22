<?php

namespace Sabre\DAVACL;

use Sabre\DAV;

/**
 * SabreDAV ACL Plugin
 *
 * This plugin provides functionality to enforce ACL permissions.
 * ACL is defined in RFC3744.
 *
 * In addition it also provides support for the {DAV:}current-user-principal
 * property, defined in RFC5397 and the {DAV:}expand-property report, as
 * defined in RFC3253.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class PluginM2 extends Plugin {

  /**
   * Triggered before properties are looked up in specific nodes.
   *
   * @param DAV\PropFind $propFind
   * @param DAV\INode $node
   * @param array $requestedProperties
   * @param array $returnedProperties
   * @TODO really should be broken into multiple methods, or even a class.
   * @return bool
   */
  function propFind(DAV\PropFind $propFind, DAV\INode $node) {
    
    $path = $propFind->getPath();
    
    // Checking the read permission
    if (!$this->checkPrivileges($path, '{DAV:}read', self::R_PARENT, false)) {
      // User is not allowed to read properties
      
      // Returning false causes the property-fetching system to pretend
      // that the node does not exist, and will cause it to be hidden
      // from listings such as PROPFIND or the browser plugin.
      if ($this->hideNodesFromListings) {
        return false;
      }
      
      // Otherwise we simply mark every property as 403.
      foreach ($propFind->getRequestedProperties() as $requestedProperty) {
        $propFind->set($requestedProperty, null, 403);
      }
      
      return;
      
    }
    
    /* Adding principal properties */
    if ($node instanceof IPrincipal) {
      
      $propFind->handle('{DAV:}alternate-URI-set', function() use ($node) {
        return new DAV\Xml\Property\Href($node->getAlternateUriSet());
      });
        $propFind->handle('{DAV:}principal-URL', function() use ($node) {
          return new DAV\Xml\Property\Href($node->getPrincipalUrl() . '/');
        });
          $propFind->handle('{DAV:}group-member-set', function() use ($node) {
            $members = $node->getGroupMemberSet();
            foreach ($members as $k => $member) {
              $members[$k] = rtrim($member, '/') . '/';
            }
            return new DAV\Xml\Property\Href($members);
          });
            $propFind->handle('{DAV:}group-membership', function() use ($node) {
              $members = $node->getGroupMembership();
              foreach ($members as $k => $member) {
                $members[$k] = rtrim($member, '/') . '/';
              }
              return new DAV\Xml\Property\Href($members);
            });
              $propFind->handle('{DAV:}displayname', [$node, 'getDisplayName']);
              
    }
    
    $propFind->handle('{DAV:}principal-collection-set', function() {
      
      $val = $this->principalCollectionSet;
      // Ensuring all collections end with a slash
      foreach ($val as $k => $v) $val[$k] = $v . '/';
      return new DAV\Xml\Property\Href($val);
      
    });
    // MANTIS 0005006: La gestion des réponses aux invitations pour les pools de secrétaires n'est pas satisfaisante
    $propFind->handle('{DAV:}current-user-principal', function() use ($node) {
      if ($url = $this->getCurrentUserPrincipal()) {
        if (method_exists($node, "getOwner")) {
          return new Xml\Property\Principal(Xml\Property\Principal::HREF, $node->getOwner() . '/');
        }
        else {
          return new Xml\Property\Principal(Xml\Property\Principal::HREF, $url . '/');
        }
      } else {
        return new Xml\Property\Principal(Xml\Property\Principal::UNAUTHENTICATED);
      }
    });
    $propFind->handle('{DAV:}supported-privilege-set', function() use ($node) {
      return new Xml\Property\SupportedPrivilegeSet($this->getSupportedPrivilegeSet($node));
    });
    $propFind->handle('{DAV:}current-user-privilege-set', function() use ($node, $propFind, $path) {
      if (!$this->checkPrivileges($path, '{DAV:}read-current-user-privilege-set', self::R_PARENT, false)) {
        $propFind->set('{DAV:}current-user-privilege-set', null, 403);
      } else {
        $val = $this->getCurrentUserPrivilegeSet($node);
        if (!is_null($val)) {
          return new Xml\Property\CurrentUserPrivilegeSet($val);
        }
      }
    });
    $propFind->handle('{DAV:}acl', function() use ($node, $propFind, $path) {
      /* The ACL property contains all the permissions */
      if (!$this->checkPrivileges($path, '{DAV:}read-acl', self::R_PARENT, false)) {
        $propFind->set('{DAV:}acl', null, 403);
      } else {
        $acl = $this->getACL($node);
        if (!is_null($acl)) {
          return new Xml\Property\Acl($this->getACL($node));
        }
      }
    });
    $propFind->handle('{DAV:}acl-restrictions', function() {
      return new Xml\Property\AclRestrictions();
    });
                
    /* Adding ACL properties */
    if ($node instanceof IACL) {
      $propFind->handle('{DAV:}owner', function() use ($node) {
        return new DAV\Xml\Property\Href($node->getOwner() . '/');
      });
    }
  }
}
