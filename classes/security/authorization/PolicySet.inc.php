<?php
/**
 * @file classes/security/authorization/PolicySet.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PolicySet
 * @ingroup security_authorization
 *
 * @brief An ordered list of policies. Policy sets can be added to
 *  decision managers like policies. The decision manager will evaluate
 *  the contained policies in the order they were added.
 *
 *  NB: PolicySets can be nested.
 */

define('COMBINING_DENY_OVERRIDES', 0x01);
define('COMBINING_PERMIT_OVERRIDES', 0x02);

// Include the authorization policy class which contains
// definitions for the deny and permit effects.
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class PolicySet extends AuthorizationPolicy {
	/** @var array */
	var $_policies = array();

	/** @var integer */
	var $_combiningAlgorithm;

	/** @var integer the default effect if none of the policies in the set applies */
	var $_effectIfNoPolicyApplies = AUTHORIZATION_DENY;


	/**
	 * Constructor
	 * @param $combiningAlgorithm int COMBINING_...
	 * @param $message string optional
	 */
	function PolicySet($combiningAlgorithm = COMBINING_DENY_OVERRIDES, $message = null) {
		$this->_combiningAlgorithm = $combiningAlgorithm;
		parent::AuthorizationPolicy($message);
	}

	//
	// Setters and Getters
	//
	/**
	 * Add a policy.
	 * @param $policy AuthorizationPolicy
	 * @param $addToTop boolean whether to insert the new policy
	 *  to the top of the list.
	 */
	function addPolicy($policy, $addToTop = false) {
		assert(is_a($policy, 'AuthorizationPolicy'));
		if ($addToTop) {
			array_unshift($this->_policies, $policy);
		} else {
			$this->_policies[] = $policy;
		}
	}

	/**
	 * Get all policies within this policy set.
	 * @return array a list of AuthorizationPolicy or PolicySet objects.
	 */
	function &getPolicies() {
		return $this->_policies;
	}

	/**
	 * Return the combining algorithm
	 * @return integer COMBINING_...
	 */
	function getCombiningAlgorithm() {
		return $this->_combiningAlgorithm;
	}

	/**
	 * Set the default effect if none of the policies in the set applies
	 * @param $effectIfNoPolicyApplies integer AUTHORIZATION_...
	 */
	function setEffectIfNoPolicyApplies($effectIfNoPolicyApplies) {
		assert($effectIfNoPolicyApplies == AUTHORIZATION_PERMIT ||
				$effectIfNoPolicyApplies == AUTHORIZATION_DENY ||
				$effectIfNoPolicyApplies == AUTHORIZATION_NOT_APPLICABLE);
		$this->_effectIfNoPolicyApplies = $effectIfNoPolicyApplies;
	}

	/**
	 * Get the default effect if none of the policies in the set applies
	 * @return integer AUTHORIZATION_...
	 */
	function getEffectIfNoPolicyApplies() {
		return $this->_effectIfNoPolicyApplies;
	}

	/**
	 * Add an authorization message
	 * @param $message mixed String (single message) or array (multiple)
	 */
	function addAuthorizationMessage($message) {
		$messages = array_merge(
			$this->getAuthorizationMessages(),
			(array) $message
		);
		$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, $messages);
	}

	/**
	 * Return all authorization messages
	 * @return array
	 */
	function getAuthorizationMessages() {
		return (array) $this->getAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE);
	}

	/**
	 * This method must return a value of either
	 * AUTHORIZATION_DENY or AUTHORIZATION_PERMIT.
	 */
	function effect() {
		// Configure the decision algorithm.
		$dominantEffect = $overriddenEffect = null; // For PHP linter
		switch($this->getCombiningAlgorithm()) {
			case COMBINING_DENY_OVERRIDES:
				$dominantEffect = AUTHORIZATION_DENY;
				$overriddenEffect = AUTHORIZATION_PERMIT;
				break;

			case COMBINING_PERMIT_OVERRIDES:
				$dominantEffect = AUTHORIZATION_PERMIT;
				$overriddenEffect = AUTHORIZATION_DENY;
				break;

			default:
				assert(false);
		}

		// Set the default decision.
		$decision = $this->getEffectIfNoPolicyApplies();

		// The following flag will record when the
		// overridden decision state is returned by
		// at least one policy.
		$decidedByOverriddenEffect = false;

		// Separated from below for bug #6821.
		$context =& $this->getAuthorizedContext();

		// Go through all policies within the policy set
		// and combine them with the configured algorithm.
		foreach($this->getPolicies() as $policy) {
			// Make sure that the policy can access the latest authorized context.
			// NB: The authorized context is set by reference. This means that it
			// will change globally if changed by the policy which is intended
			// behavior so that policies can access authorized objects provided
			// by policies called earlier in the authorization process.
			$policy->setAuthorizedContext($context);

			// Check whether the policy applies.
			if ($policy->applies()) {
				// If the policy applies then retrieve its effect.
				$effect = $policy->effect();
			} else {
				$effect = AUTHORIZATION_NOT_APPLICABLE;
			}

			// Try the next policy if this policy didn't apply.
			if ($effect === AUTHORIZATION_NOT_APPLICABLE) continue;
			assert($effect === AUTHORIZATION_PERMIT || $effect === AUTHORIZATION_DENY);

			if ($effect === AUTHORIZATION_DENY || is_a($policy, 'PolicySet')) {
				// Bring in advice from denied policies, or anything that comes from
				// denied policies in nested policy sets.

				// Messages to the end user.
				if ($policy->hasAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE)) {
					$this->addAuthorizationMessage($policy->getAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE));
				}

				// Callback advice.
				if ($policy->hasAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY)) {
					$this->setAdvice(
						AUTHORIZATION_ADVICE_CALL_ON_DENY,
						$policy->getAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY)
					);
				}
			}

			// Process the effect.
			if ($effect === $overriddenEffect) {
				$decidedByOverriddenEffect = true;
			} else {
				// Only one dominant effect overrides all other effects
				// so we don't even have to evaluate other policies.
				return $dominantEffect;
			}
		}

		// Only return an overridden effect if at least one
		// policy returned that effect and none returned the
		// dominant effect.
		if ($decidedByOverriddenEffect) $decision = $overriddenEffect;
		return $decision;
	}
}

?>
