<?php
/**
 * @file classes/security/authorization/AuthorizationContextHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorizationContextHandler
 * @ingroup security_authorization
 *
 * @brief Class to represent an authorization context handler.
 *
 * The authorization context handler is the central point to
 * provide authorization attribute values like the logged in user,
 * the roles of this user, access to data objects, context
 * settings, etc.
 *
 * The authorization context handler makes sure that such
 * information is only retrieved once per request from the
 * database and then served from cache.
 */

class AuthorizationContextHandler {
	/** @var array a cache of previously retrieved values */
	var $_responseCache = array();

	/**
	 * Constructor
	 */
	function AuthorizationContextHandler() {
	}


	//
	// Protected helper methods
	//
	/**
	 * Cache a response for a given value
	 *
	 * @param $cacheId mixed any scalar that uniquely
	 *  identified the cached response.
	 * @param $response boolean
	 */
	function cacheResponse($cacheId, $response) {
		assert(is_scalar($cacheId));
		$this->_responseCache[$cacheId] = $response;
	}

	/**
	 * Retrieve a cached response for a given value
	 *
	 * @param $cacheId mixed any scalar that uniquely
	 *  identified the cached response.
	 * @return $response boolean or null if no cached
	 *  response present.
	 */
	function retrieveCachedResponse($cacheId) {
		assert(is_scalar($cacheId));
		if (!isset($this->_responseCache[$cacheId])) return null;
		return $this->_responseCache[$cacheId];
	}


	//
	// Protected methods to be overridden by subclasses
	//
	/**
	 * Checks whether the given attribute value is present
	 * in the current authorization request context.
	 *
	 * @param $value mixed
	 * @return boolean
	 */
	function checkAttribute(&$value) {
		// Abstract method to be implemented by subclasses
		assert(false);
	}

	/**
	 * Retrieves an attribute from the authorization context.
	 *
	 * NB: It is often more efficient to check for the
	 * presence of an attribute rather than retrieving it.
	 *
	 * If you can use checkAttribute() rather than
	 * getAttribute() then you should do so.
	 *
	 * @return array an array with all values that match
	 *  the attribute.
	 */
	function getAttribute() {
		// Abstract method to be implemented by subclasses
		assert(false);
	}
}

?>
