<?php
/**
 * @file classes/security/authorization/AuthorizationDecisionManager.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorizationDecisionManager
 * @ingroup security_authorization
 *
 * @brief A class that can take a list of authorization policies, apply
 *  them to the current authorization request context and return an
 *  authorization decision.
 *
 *  This decision manager implements the following logic to combine
 *  authorization policies:
 *  - If any of the given policies applies with a result of
 *    AUTHORIZATION_DENY then the decision manager will deny access
 *    (=deny overrides policy).
 *  - If none of the given policies applies then the decision
 *    manager will deny access (=whitelist approach, deny if none
 *    applicable).
 */

class AuthorizationDecisionManager {
	/**
	 * @var array a list of AuthorizationPolicy objects.
	 */
	var $_policies = array();

	/**
	 * @var array
	 */
	var $_authorizationMessages = array();

	/**
	 * Constructor
	 */
	function AuthorizationDecisionManager() {
	}


	//
	// Setters and Getters
	//
	/**
	 * Add an authorization policy.
	 *
	 * @param $policy AuthorizationPolicy
	 */
	function addPolicy(&$policy) {
		$this->_policies[] =& $policy;
	}

	/**
	 * Add an authorization message
	 * @param $message string
	 */
	function addAuthorizationMessage($message) {
		$this->_authorizationMessages[] = $message;
	}

	/**
	 * Return all authorization messages
	 * @return array
	 */
	function getAuthorizationMessages() {
		return $this->_authorizationMessages;
	}


	//
	// Public methods
	//
	/**
	 * Take an authorization decision.
	 *
	 * @return integer one of AUTHORIZATION_ALLOW or
	 *  AUTHORIZATION_DENY.
	 */
	function decide() {
		// Our default policy:
		$decision = AUTHORIZATION_DENY;

		$allowedByPolicy = false;
		$callOnDeny = null;
		foreach($this->_policies as $policy) {
			// Check whether the policy applies.
			if ($policy->applies()) {
				// If the policy applies then retrieve its effect.
				if ($policy->effect() == AUTHORIZATION_ALLOW) {
					$allowedByPolicy = true;
				} else {
					// Only one deny effect overrides all allow effects.
					$allowedByPolicy = false;

					// Look for applicable advice.
					if ($policy->hasAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE)) {
						$this->addAuthorizationMessage($policy->getAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE));
					}
					if ($policy->hasAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY)) {
						$callOnDeny =& $policy->getAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY);
					}
					break;
				}
			}
		}

		// Only return an "allowed" decision if at least one
		// policy allowed access and none denied access.
		if ($allowedByPolicy) $decision = AUTHORIZATION_ALLOW;

		// Call the "call on deny" advice
		if ($decision == AUTHORIZATION_DENY && !is_null($callOnDeny)) {
			assert(is_array($callOnDeny) && count($callOnDeny) == 3);
			list($classOrObject, $method, $parameters) = $callOnDeny;
			$methodCall = array($classOrObject, $method);
			assert(is_callable($methodCall));
			call_user_func_array($methodCall, $parameters);
		}

		return $decision;
	}
}

?>
