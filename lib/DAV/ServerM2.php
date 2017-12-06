<?php

namespace Sabre\DAV;

use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Main DAV server class
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ServerM2 extends Server { 
  /**
   * This method checks the main HTTP preconditions.
   *
   * Currently these are:
   *   * If-Match
   *   * If-None-Match
   *   * If-Modified-Since
   *   * If-Unmodified-Since
   *
   * The method will return true if all preconditions are met
   * The method will return false, or throw an exception if preconditions
   * failed. If false is returned the operation should be aborted, and
   * the appropriate HTTP response headers are already set.
   *
   * Normally this method will throw 412 Precondition Failed for failures
   * related to If-None-Match, If-Match and If-Unmodified Since. It will
   * set the status to 304 Not Modified for If-Modified_since.
   *
   * @param RequestInterface $request
   * @param ResponseInterface $response
   * @return bool
   */
  function checkPreconditions(RequestInterface $request, ResponseInterface $response) {
    
    $path = $request->getPath();
    $node = null;
    $lastMod = null;
    $etag = null;
    
    
    if ($ifMatch = $request->getHeader('If-Match')) {
      
      // If-Match contains an entity tag. Only if the entity-tag
      // matches we are allowed to make the request succeed.
      // If the entity-tag is '*' we are only allowed to make the
      // request succeed if a resource exists at that url.
      try {
        $node = $this->tree->getNodeForPath($path);
      } catch (Exception\NotFound $e) {
        //throw new Exception\PreconditionFailed('An If-Match header was specified and the resource did not exist', 'If-Match');
        \Lib\Log\Log::l(\Lib\Log\Log::INFO, "[DAV] ServerM2.checkPreconditions() Error: An If-Match header was specified and the resource did not exist.");
      }
      
      // Only need to check entity tags if they are not *
      if ($ifMatch !== '*') {
        
        // There can be multiple ETags
        $ifMatch = explode(',', $ifMatch);
        $haveMatch = false;
        foreach ($ifMatch as $ifMatchItem) {
          
          // Stripping any extra spaces
          $ifMatchItem = trim($ifMatchItem, ' ');
          
          $etag = $node instanceof IFile ? $node->getETag() : null;
          if ($etag === $ifMatchItem) {
            $haveMatch = true;
          } else {
            // Evolution has a bug where it sometimes prepends the "
            // with a \. This is our workaround.
            if (str_replace('\\"', '"', $ifMatchItem) === $etag) {
              $haveMatch = true;
            }
          }
          
        }
        if (!$haveMatch) {
          if ($etag) $response->setHeader('ETag', $etag);
          //throw new Exception\PreconditionFailed('An If-Match header was specified, but none of the specified the ETags matched.', 'If-Match');
          \Lib\Log\Log::l(\Lib\Log\Log::INFO, "[DAV] ServerM2.checkPreconditions() Error: An If-Match header was specified, but none of the specified the ETags matched.");
        }
      }
    }
    
    if ($ifNoneMatch = $request->getHeader('If-None-Match')) {
      
      // The If-None-Match header contains an ETag.
      // Only if the ETag does not match the current ETag, the request will succeed
      // The header can also contain *, in which case the request
      // will only succeed if the entity does not exist at all.
      $nodeExists = true;
      if (!$node) {
        try {
          $node = $this->tree->getNodeForPath($path);
        } catch (Exception\NotFound $e) {
          $nodeExists = false;
        }
      }
      if ($nodeExists) {
        $haveMatch = false;
        if ($ifNoneMatch === '*') $haveMatch = true;
        else {
          
          // There might be multiple ETags
          $ifNoneMatch = explode(',', $ifNoneMatch);
          $etag = $node instanceof IFile ? $node->getETag() : null;
          
          foreach ($ifNoneMatch as $ifNoneMatchItem) {
            
            // Stripping any extra spaces
            $ifNoneMatchItem = trim($ifNoneMatchItem, ' ');
            
            if ($etag === $ifNoneMatchItem) $haveMatch = true;
            
          }
          
        }
        
        if ($haveMatch) {
          if ($etag) $response->setHeader('ETag', $etag);
          if ($request->getMethod() === 'GET') {
            $response->setStatus(304);
            return false;
          } else {
            //throw new Exception\PreconditionFailed('An If-None-Match header was specified, but the ETag matched (or * was specified).', 'If-None-Match');
            \Lib\Log\Log::l(\Lib\Log\Log::INFO, "[DAV] ServerM2.checkPreconditions() Error: An If-None-Match header was specified, but the ETag matched (or * was specified).");
          }
        }
      }
      
    }
    
    if (false && $ifMatch = $request->getHeader('If-Match')) {
      
      // If-Match contains an entity tag. Only if the entity-tag
      // matches we are allowed to make the request succeed.
      // If the entity-tag is '*' we are only allowed to make the
      // request succeed if a resource exists at that url.
      try {
        $node = $this->tree->getNodeForPath($path);
      } catch (Exception\NotFound $e) {
        throw new Exception\PreconditionFailed('An If-Match header was specified and the resource did not exist', 'If-Match');
      }
      
      // Only need to check entity tags if they are not *
      if ($ifMatch !== '*') {
        
        // There can be multiple ETags
        $ifMatch = explode(',', $ifMatch);
        $haveMatch = false;
        foreach ($ifMatch as $ifMatchItem) {
          
          // Stripping any extra spaces
          $ifMatchItem = trim($ifMatchItem, ' ');
          
          $etag = $node instanceof IFile ? $node->getETag() : null;
          if ($etag === $ifMatchItem) {
            $haveMatch = true;
          } else {
            // Evolution has a bug where it sometimes prepends the "
            // with a \. This is our workaround.
            if (str_replace('\\"', '"', $ifMatchItem) === $etag) {
              $haveMatch = true;
            }
          }
          
        }
        if (!$haveMatch) {
          if ($etag) $response->setHeader('ETag', $etag);
          throw new Exception\PreconditionFailed('An If-Match header was specified, but none of the specified the ETags matched.', 'If-Match');
        }
      }
    }
    
    if (false && $ifNoneMatch = $request->getHeader('If-None-Match')) {
      
      // The If-None-Match header contains an ETag.
      // Only if the ETag does not match the current ETag, the request will succeed
      // The header can also contain *, in which case the request
      // will only succeed if the entity does not exist at all.
      $nodeExists = true;
      if (!$node) {
        try {
          $node = $this->tree->getNodeForPath($path);
        } catch (Exception\NotFound $e) {
          $nodeExists = false;
        }
      }
      if ($nodeExists) {
        $haveMatch = false;
        if ($ifNoneMatch === '*') $haveMatch = true;
        else {
          
          // There might be multiple ETags
          $ifNoneMatch = explode(',', $ifNoneMatch);
          $etag = $node instanceof IFile ? $node->getETag() : null;
          
          foreach ($ifNoneMatch as $ifNoneMatchItem) {
            
            // Stripping any extra spaces
            $ifNoneMatchItem = trim($ifNoneMatchItem, ' ');
            
            if ($etag === $ifNoneMatchItem) $haveMatch = true;
            
          }
          
        }
        
        if ($haveMatch) {
          if ($etag) $response->setHeader('ETag', $etag);
          if ($request->getMethod() === 'GET') {
            $response->setStatus(304);
            return false;
          } else {
            throw new Exception\PreconditionFailed('An If-None-Match header was specified, but the ETag matched (or * was specified).', 'If-None-Match');
          }
        }
      }
      
    }
    
    if (!$ifNoneMatch && ($ifModifiedSince = $request->getHeader('If-Modified-Since'))) {
      
      // The If-Modified-Since header contains a date. We
      // will only return the entity if it has been changed since
      // that date. If it hasn't been changed, we return a 304
      // header
      // Note that this header only has to be checked if there was no If-None-Match header
      // as per the HTTP spec.
      $date = HTTP\Util::parseHTTPDate($ifModifiedSince);
      
      if ($date) {
        if (is_null($node)) {
          $node = $this->tree->getNodeForPath($path);
        }
        $lastMod = $node->getLastModified();
        if ($lastMod) {
          $lastMod = new \DateTime('@' . $lastMod);
          if ($lastMod <= $date) {
            $response->setStatus(304);
            $response->setHeader('Last-Modified', HTTP\Util::toHTTPDate($lastMod));
            return false;
          }
        }
      }
    }
    
    if ($ifUnmodifiedSince = $request->getHeader('If-Unmodified-Since')) {
      
      // The If-Unmodified-Since will allow allow the request if the
      // entity has not changed since the specified date.
      $date = HTTP\Util::parseHTTPDate($ifUnmodifiedSince);
      
      // We must only check the date if it's valid
      if ($date) {
        if (is_null($node)) {
          $node = $this->tree->getNodeForPath($path);
        }
        $lastMod = $node->getLastModified();
        if ($lastMod) {
          $lastMod = new \DateTime('@' . $lastMod);
          if ($lastMod > $date) {
            throw new Exception\PreconditionFailed('An If-Unmodified-Since header was specified, but the entity has been changed since the specified date.', 'If-Unmodified-Since');
          }
        }
      }
      
    }
    
    // Now the hardest, the If: header. The If: header can contain multiple
    // urls, ETags and so-called 'state tokens'.
    //
    // Examples of state tokens include lock-tokens (as defined in rfc4918)
    // and sync-tokens (as defined in rfc6578).
    //
    // The only proper way to deal with these, is to emit events, that a
    // Sync and Lock plugin can pick up.
    $ifConditions = $this->getIfConditions($request);
    
    foreach ($ifConditions as $kk => $ifCondition) {
      foreach ($ifCondition['tokens'] as $ii => $token) {
        $ifConditions[$kk]['tokens'][$ii]['validToken'] = false;
      }
    }
    
    // Plugins are responsible for validating all the tokens.
    // If a plugin deemed a token 'valid', it will set 'validToken' to
    // true.
    $this->emit('validateTokens', [ $request, &$ifConditions ]);
    
    // Now we're going to analyze the result.
    
    // Every ifCondition needs to validate to true, so we exit as soon as
    // we have an invalid condition.
    foreach ($ifConditions as $ifCondition) {
      
      $uri = $ifCondition['uri'];
      $tokens = $ifCondition['tokens'];
      
      // We only need 1 valid token for the condition to succeed.
      foreach ($tokens as $token) {
        
        $tokenValid = $token['validToken'] || !$token['token'];
        
        $etagValid = false;
        if (!$token['etag']) {
          $etagValid = true;
        }
        // Checking the ETag, only if the token was already deamed
        // valid and there is one.
        if ($token['etag'] && $tokenValid) {
          
          // The token was valid, and there was an ETag. We must
          // grab the current ETag and check it.
          $node = $this->tree->getNodeForPath($uri);
          $etagValid = $node instanceof IFile && $node->getETag() == $token['etag'];
          
        }
        
        
        if (($tokenValid && $etagValid) ^ $token['negate']) {
          // Both were valid, so we can go to the next condition.
          continue 2;
        }
        
        
      }
      
      // If we ended here, it means there was no valid ETag + token
      // combination found for the current condition. This means we fail!
      throw new Exception\PreconditionFailed('Failed to find a valid token/etag combination for ' . $uri, 'If');
      
    }
    
    return true;
    
  }
}
  