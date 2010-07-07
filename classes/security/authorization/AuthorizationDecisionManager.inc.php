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
	/** @var integer the default decision if no policy applies */
	var $_decisionIfNoPolicyApplies = AUTHORIZATION_DENY;

	/** @var array a list of AuthorizationPolicy objects. */
	var $_policies = array();

	/** @var array */
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
	 * Set the default decision if no policy applies
	 * @param $decisionIfNoPolicyApplies integer
	 */
	function setDecisionIfNoPolicyApplies($decisionIfNoPolicyApplies) {
		assert($decisionIfNoPolicyApplies == AUTHORIZATION_ALLOW ||
				$decisionIfNoPolicyApplies == AUTHORIZATION_DENY);
		$this->_decisionIfNoPolicyApplies = $decisionIfNoPolicyApplies;
	}

	/**
	 * Get the default decision if no policy applies
	 * @return integer
	 */
	function getDecisionIfNoPolicyApplies() {
		return $this->_decisionIfNoPolicyApplies;
	}

	/**
	 * Add an authorization policy or a policy set.
	 *
	 * @param $policyOrPolicySet AuthorizationPolicy|PolicySet
	 */
	function addPolicy(&$policyOrPolicySet) {
		switch (true) {
			case is_a($policyOrPolicySet, 'AuthorizationPolicy'):
				$this->_policies[] =& $policyOrPolicySet;
				break;

			case is_a($policyOrPolicySet, 'PolicySet'):
				foreach($policyOrPolicySet->getPolicies() as $subPolicy) {
					// Recursively add the sub-policy as it can be
					// another policy set.
					$this->addPolicy($subPolicy);
					unset($subPolicy);
				}
				break;

			default:
				// Unknown policy container.
				assert(false);
		}
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
		// Set the default decision.
		$decision = $this->getDecisionIfNoPolicyApplies();

		$allowedByPolicy = false;
		$callOnDeny = null;
		foreach($this->_policies as $policy) {
			// Check whether the policy applies.
			if ($policy->applies()) {
				// If the policy applies then retrieve its effect.
				$effect = $policy->effect();
				assert($effect === AUTHORIZATION_ALLOW || $effect === AUTHORIZATION_DENY);
				if ($effect === AUTHORIZATION_ALLOW) {
					$allowedByPolicy = true;
				} else {
					// Only one deny effect overrides all allow effects.
					$allowedByPolicy = false;
					$decision = AUTHORIZATION_DENY;

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
		if ($decision === AUTHORIZATION_DENY && !is_null($callOnDeny)) {
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
