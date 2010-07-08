<?php
/**
 * @file classes/security/authorization/PolicySet.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

class PolicySet {
	/** @var array */
	var $_policies;

	/** @var integer */
	var $_combiningAlgorithm;

	/**
	 * Constructor
	 */
	function PolicySet($combiningAlgorithm = COMBINING_DENY_OVERRIDES) {
		$this->_combiningAlgorithm = $combiningAlgorithm;
	}

	//
	// Setters and Getters
	//
	/**
	 * Add a policy or a nested policy set.
	 * @param $policyOrPolicySet AuthorizationPolicy|PolicySet
	 */
	function addPolicy(&$policyOrPolicySet) {
		assert(is_a($policyOrPolicySet, 'AuthorizationPolicy') || is_a($policyOrPolicySet, 'PolicySet'));
		$this->_policies[] =& $policyOrPolicySet;
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
	 * @return integer
	 */
	function getCombiningAlgorithm() {
		return $this->_combiningAlgorithm;
	}
}

?>
