<?php
/**
 * @file classes/security/authorization/AuthorizationPolicy.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

define ('AUTHORIZATION_ALLOW', 0x01);
define ('AUTHORIZATION_DENY', 0x02);

class AuthorizationPolicy {
	/** @var string a message to be displayed when access is denied */
	var $_message;

	/**
	 * @var array the target of the policy.
	 *
	 * The target is an array of attribute identifiers and
	 * attribute values. All specified attribute values have
	 * to be present in the authorization request context
	 * for the policy to be applicable. If an attribute
	 * contains an array of allowed values then only one
	 * of these values must be present in the context.
	 *
	 * Attributes can be repeated, e.g. to express that
	 * several roles must be in the context for the rule
	 * to apply.
	 *
	 * Example:
	 *  array(
	 *    array( 'role' => ROLE_ID_EDITOR ),
	 *    array( 'role' => ROLE_ID_SECTION_EDITOR ),
	 *    array( 'operation' => array('addItem', 'deleteItem')
	 *  )
	 *
	 * Such a target specification means that the user must
	 * have both, the editor and the section editor roles, to
	 * access /any of/ the operations 'addItem' or 'deleteItem'.
	 */
	var $_targetAttributes = array();

	/** @var integer the effect of this policy */
	var $_effect = AUTHORIZATION_ALLOW;


	/**
	 * Constructor
	 * @param $message string
	 */
	function AuthorizationPolicy($message = null) {
		$this->_message = $message;
	}

	//
	// Setters and Getters
	//
	/**
	 * Add a target attribute, see explanation of the
	 * $_targetAttributes variable above.
	 *
	 * @param $attributeName string
	 * @param $attributeValues array
	 */
	function addTargetAttribute($attributeName, $attributeValues) {
		assert(is_scalar($attributeName));
		if (!is_array($attributeValues)) {
			$attributeValues = array($attributeValues);
		}
		$this->_targetAttributes[] = array($attributeName => $attributeValues);
	}

	/**
	 * Return the list of configured
	 * target attributes, see explanation of the
	 * $_targetAttributes variable above.
	 *
	 * @return array
	 */
	function &getTargetAttributes() {
		return $this->_targetAttributes;
	}

	/**
	 * Set the effect of the policy.
	 *
	 * @param $effect one of AUTHORIZATION_ALLOW or
	 *  AUTHORIZATION_DENY.
	 */
	function setEffect($effect) {
		assert($effect == AUTHORIZATION_ALLOW || $effect == AUTHORIZATION_DENY);
		$this->_effect = $effect;
	}

	/**
	 * Return the effect of the policy.
	 *
	 * @return integer one of AUTHORIZATION_ALLOW or
	 *  AUTHORIZATION_DENY.
	 */
	function getEffect() {
		// The default policy is: allow when matched.
		return $this->_effect;
	}
}

?>
