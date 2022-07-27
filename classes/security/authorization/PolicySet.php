<?php
/**
 * @file classes/security/authorization/PolicySet.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

namespace PKP\security\authorization;

class PolicySet
{
    public const COMBINING_DENY_OVERRIDES = 1;
    public const COMBINING_PERMIT_OVERRIDES = 2;

    /** @var array */
    public $_policies = [];

    /** @var int */
    public $_combiningAlgorithm;

    /** @var int the default effect if none of the policies in the set applies */
    public $_effectIfNoPolicyApplies = AuthorizationPolicy::AUTHORIZATION_DENY;


    /**
     * Constructor
     *
     * @param int $combiningAlgorithm COMBINING_...
     */
    public function __construct($combiningAlgorithm = self::COMBINING_DENY_OVERRIDES)
    {
        $this->_combiningAlgorithm = $combiningAlgorithm;
    }

    //
    // Setters and Getters
    //
    /**
     * Add a policy or a nested policy set.
     *
     * @param AuthorizationPolicy|PolicySet $policyOrPolicySet
     * @param bool $addToTop whether to insert the new policy
     *  to the top of the list.
     */
    public function addPolicy($policyOrPolicySet, $addToTop = false)
    {
        assert($policyOrPolicySet instanceof AuthorizationPolicy || $policyOrPolicySet instanceof self);
        if ($addToTop) {
            array_unshift($this->_policies, $policyOrPolicySet);
        } else {
            $this->_policies[] = & $policyOrPolicySet;
        }
    }

    /**
     * Get all policies within this policy set.
     *
     * @return array a list of AuthorizationPolicy or PolicySet objects.
     */
    public function &getPolicies()
    {
        return $this->_policies;
    }

    /**
     * Return the combining algorithm
     *
     * @return int
     */
    public function getCombiningAlgorithm()
    {
        return $this->_combiningAlgorithm;
    }

    /**
     * Set the default effect if none of the policies in the set applies
     *
     * @param int $effectIfNoPolicyApplies
     */
    public function setEffectIfNoPolicyApplies($effectIfNoPolicyApplies)
    {
        assert($effectIfNoPolicyApplies == AuthorizationPolicy::AUTHORIZATION_PERMIT ||
                $effectIfNoPolicyApplies == AuthorizationPolicy::AUTHORIZATION_DENY ||
                $effectIfNoPolicyApplies == AuthorizationDecisionManager::AUTHORIZATION_NOT_APPLICABLE);
        $this->_effectIfNoPolicyApplies = $effectIfNoPolicyApplies;
    }

    /**
     * Get the default effect if none of the policies in the set applies
     *
     * @return int
     */
    public function getEffectIfNoPolicyApplies()
    {
        return $this->_effectIfNoPolicyApplies;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PolicySet', '\PolicySet');
    define('COMBINING_DENY_OVERRIDES', \PolicySet::COMBINING_DENY_OVERRIDES);
    define('COMBINING_PERMIT_OVERRIDES', \PolicySet::COMBINING_PERMIT_OVERRIDES);
}
