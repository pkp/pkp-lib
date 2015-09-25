<?php
/**
 * @file classes/security/authorization/AuthorizationDecisionManager.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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

import('lib.pkp.classes.security.authorization.PolicySet');

define('AUTHORIZATION_NOT_APPLICABLE', 0x03);

class AuthorizationDecisionManager extends PolicySet {
	/**
	 * Constructor
	 */
	function AuthorizationDecisionManager() {
		parent::PolicySet(COMBINING_DENY_OVERRIDES);
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the default decision if none of the
	 * policies in the root policy set applies.
	 * @param $decisionIfNoPolicyApplies integer
	 */
	function setDecisionIfNoPolicyApplies($decisionIfNoPolicyApplies) {
		$this->setEffectIfNoPolicyApplies($decisionIfNoPolicyApplies);
	}


	//
	// Public methods
	//
	/**
	 * Take an authorization decision.
	 * @return integer one of AUTHORIZATION_PERMIT or
	 *  AUTHORIZATION_DENY.
	 */
	function decide() {
		// Decide the root policy set which will recursively decide
		// all nested policy sets and return a single decision.
		$decision = $this->effect();
		assert($decision !== AUTHORIZATION_NOT_APPLICABLE);
		$callOnDeny = ($this->hasAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY)?
			$this->getAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY):
			null);

		// Call the "call on deny" advice
		if ($decision === AUTHORIZATION_DENY && $callOnDeny) {
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
