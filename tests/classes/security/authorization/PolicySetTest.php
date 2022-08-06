<?php

/**
 * @file tests/classes/security/authorization/PolicySetTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PolicySetTest
 * @ingroup tests_classes_security_authorization
 *
 * @see PolicySet
 *
 * @brief Test class for the PolicySet class
 */

namespace PKP\tests\classes\security\authorization;

use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\PolicySet;
use PKP\tests\PKPTestCase;

class PolicySetTest extends PKPTestCase
{
    /**
     * @covers PolicySet
     */
    public function testPolicySet()
    {
        // Test combining algorithm and default effect.
        $policySet = new PolicySet();
        self::assertEquals(PolicySet::COMBINING_DENY_OVERRIDES, $policySet->getCombiningAlgorithm());
        self::assertEquals(AuthorizationPolicy::AUTHORIZATION_DENY, $policySet->getEffectIfNoPolicyApplies());
        $policySet = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        $policySet->setEffectIfNoPolicyApplies(AuthorizationPolicy::AUTHORIZATION_PERMIT);
        self::assertEquals(PolicySet::COMBINING_PERMIT_OVERRIDES, $policySet->getCombiningAlgorithm());
        self::assertEquals(AuthorizationPolicy::AUTHORIZATION_PERMIT, $policySet->getEffectIfNoPolicyApplies());

        // Test adding policies.
        $policySet->addPolicy($policy1 = new AuthorizationPolicy('policy1'));
        $policySet->addPolicy($policy2 = new AuthorizationPolicy('policy2'));
        $policySet->addPolicy($policy3 = new AuthorizationPolicy('policy3'), $addToTop = true);
        self::assertEquals([$policy3, $policy1, $policy2], $policySet->getPolicies());
    }
}
