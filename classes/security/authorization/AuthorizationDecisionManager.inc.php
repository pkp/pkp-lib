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
	 * @var array a list of AuthorizationContextHandler objects
	 */
	var $_authorizationContextHandlers = array();

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
		foreach($this->_policies as $policy) {
			// Check whether the policy applies.
			$policyApplies = true;
			$targetAttributes =& $policy->getTargetAttributes();
			foreach($targetAttributes as $targetAttribute) {
				assert(is_array($targetAttribute) && count($targetAttribute) == 1);
				$attributeName = key($targetAttribute);
				$attributeValues = current($targetAttribute);
				assert(is_array($attributeValues));

				$attributePresent = false;
				foreach($attributeValues as $attributeValue) {
					if ($this->checkAttribute($attributeName, $attributeValue)) {
						// Only one of the attribute values has to be
						// present in the authorization context for the
						// attribute to be considered available ("any of").
						$attributePresent = true;
						break;
					}
				}

				// All attributes have to be present ("all of") for
				// the policy to apply.
				if (!$attributePresent) {
					$policyApplies = false;
					break;
				}
			}

			// If the policy applies then retrieve its effect.
			if ($policyApplies) {
				if ($policy->getEffect() == AUTHORIZATION_ALLOW) {
					$allowedByPolicy = true;
				} else {
					// Only one deny effect overrides all allow effects.
					$allowedByPolicy = false;
					break;
				}
			}
		}

		// Only return an "allowed" decision if at least one
		// policy allowed access and none denied access.
		if ($allowedByPolicy) $decision = AUTHORIZATION_ALLOW;

		return $decision;
	}

	/**
	 * Delegate to an appropriate authorization context handler
	 * and check whether the given attribute value is in the
	 * authorization context.
	 *
	 * @param $attributeName string
	 * @param $attributeValue mixed
	 *
	 * @return boolean
	 */
	function checkAttribute($attributeName, &$attributeValue) {
		$authorizationContextHandler =& $this->_resolveAuthorizationContextHandler($attributeName);
		return $authorizationContextHandler->checkAttribute($attributeValue);
	}

	/**
	 * Delegate to an appropriate authorization context handler
	 * and get the values for the given attribute currently
	 * present in the authorization context.
	 *
	 * @param $attributeName string
	 *
	 * @return mixed either a single scalar attribute value or an
	 *  array if the context contains several values for the attribute.
	 */
	function &getAttributeValues($attributeName) {
		$authorizationContextHandler =& $this->_resolveAuthorizationContextHandler($attributeName);
		$returner =& $authorizationContextHandler->getAttributeValues();
		return $returner;
	}


	//
	// Private helper methods
	//
	/**
	 * Return an authorization context handler instance that
	 * can handle the given attribute name.
	 *
	 * @param $attributeName string
	 *
	 * @return AuthorizationContextHandler
	 */
	function &_resolveAuthorizationContextHandler($attributeName) {
		static $authorizationContextHandlerNames = array(
			'role' => 'lib.pkp.classes.security.authorization.RoleAuthorizationContextHandler'
		);

		if (!isset($this->_authorizationContextHandlers[$attributeName])) {
			// Instantiate a new authorization context handler.
			assert(isset($authorizationContextHandlerNames[$attributeName]));
			$authorizationContextHandlerName = $authorizationContextHandlerNames[$attributeName];
			$authorizationContextHandler = new $authorizationContextHandlerName();
			assert(is_a($authorizationContextHandler, 'AuthorizationContextHandler'));

			// Cache the authorization context handler.
			$this->_authorizationContextHandlers[$attributeName] =& $authorizationContextHandler;
			unset($authorizationContextHandler);
		}

		return $this->_authorizationContextHandlers[$attributeName];
	}
}

?>
