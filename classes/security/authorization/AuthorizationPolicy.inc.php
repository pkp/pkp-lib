<?php
/**
 * @file classes/security/authorization/AuthorizationPolicy.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorizationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to represent an authorization policy.
 *
 * We use some of the terminology specified in the draft XACML V3.0 standard,
 * please see <http://www.oasis-open.org/committees/tc_home.php?wg_abbrev=xacml>
 * for details.
 *
 * We try to stick closely enough to XACML concepts to make sure that
 * future improvements to the authorization framework can be done in a
 * consistent manner.
 *
 * This of course doesn't mean that we try to be XACML compliant in any way.
 */

define ('AUTHORIZATION_PERMIT', 0x01);
define ('AUTHORIZATION_DENY', 0x02);

define ('AUTHORIZATION_ADVICE_DENY_MESSAGE', 0x01);
define ('AUTHORIZATION_ADVICE_CALL_ON_DENY', 0x02);

class AuthorizationPolicy {
	/** @var array advice to be returned to the decision point */
	var $_advice = array();

	/** @var array a cache of previously retrieved values */
	var $_effectCache = array();

	/**
	 * @var array a list of authorized context objects that should be
	 *  returned to the caller
	 */
	var $_authorizedContext = array();


	/**
	 * Constructor
	 * @param $message string
	 */
	function AuthorizationPolicy($message = null) {
		if (!is_null($message)) $this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, $message);
	}

	//
	// Setters and Getters
	//
	/**
	 * Set an advice
	 * @param $adviceType integer
	 * @param $adviceContent mixed
	 */
	function setAdvice($adviceType, &$adviceContent) {
		$this->_advice[$adviceType] =& $adviceContent;
	}

	/**
	 * Whether this policy implements
	 * the given advice type.
	 * @param $adviceType integer
	 * @return boolean
	 */
	function hasAdvice($adviceType) {
		return isset($this->_advice[$adviceType]);
	}

	/**
	 * Get all advice
	 * @return array
	 */
	function &getAdvice() {
		return $this->_advice;
	}

	/**
	 * Add an object to the authorized context
	 * @param $assocType integer
	 * @param $authorizedObject mixed
	 */
	function addAuthorizedContextObject($assocType, &$authorizedObject) {
		assert(!$this->hasAuthorizedContextObject($assocType));
		$this->_authorizedContext[$assocType] =& $authorizedObject;
	}

	/**
	 * Check whether an object already exists in the
	 * authorized context.
	 * @param $assocType integer
	 * @return boolean
	 */
	function hasAuthorizedContextObject($assocType) {
		return isset($this->_authorizedContext[$assocType]);
	}


	/**
	 * Retrieve an object from the authorized context
	 * @param $assocType integer
	 * @return mixed will return null if the context
	 *  for the given assoc type does not exist.
	 */
	function &getAuthorizedContextObject($assocType) {
		if ($this->hasAuthorizedContextObject($assocType)) {
			return $this->_authorizedContext[$assocType];
		} else {
			$nullVar = null;
			return $nullVar;
		}
	}

	/**
	 * Set the authorized context
	 * @return array
	 */
	function setAuthorizedContext(&$authorizedContext) {
		$this->_authorizedContext =& $authorizedContext;
	}

	/**
	 * Get the authorized context
	 * @return array
	 */
	function &getAuthorizedContext() {
		return $this->_authorizedContext;
	}

	//
	// Protected template methods to be implemented by sub-classes
	//
	/**
	 * Whether this policy applies.
	 * @return boolean
	 */
	function applies() {
		// Policies apply by default
		return true;
	}

	/**
	 * This method must return a value of either
	 * AUTHORIZATION_DENY or AUTHORIZATION_PERMIT.
	 */
	function effect() {
		// Deny by default.
		return AUTHORIZATION_DENY;
	}


	//
	// Protected helper methods
	//
	/**
	 * Cache an effect for a given cache id
	 *
	 * @param $cacheId mixed any scalar that uniquely
	 *  identified the cached effect.
	 * @param $response boolean
	 */
	function cacheEffect($cacheId, $effect) {
		assert(is_scalar($cacheId));
		$this->_effectCache[$cacheId] = $effect;
	}

	/**
	 * Retrieve a cached effect for a given cache id
	 *
	 * @param $cacheId mixed any scalar that uniquely
	 *  identified the cached effect.
	 * @return $response mixed or null if no cached
	 *  effect present.
	 */
	function retrieveCachedEffect($cacheId) {
		assert(is_scalar($cacheId));
		if (!isset($this->_effectCache[$cacheId])) return null;
		return $this->_effectCache[$cacheId];
	}
}

?>
