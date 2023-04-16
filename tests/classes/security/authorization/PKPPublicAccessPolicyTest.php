<?php

/**
 * @file tests/classes/security/authorization/PKPPublicAccessPolicyTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicAccessPolicyTest
 *
 * @ingroup tests_classes_security_authorization
 *
 * @see PKPPublicAccessPolicy
 *
 * @brief Test class for the PKPPublicAccessPolicy class
 */

namespace PKP\tests\classes\security\authorization;

use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\PKPPublicAccessPolicy;

class PKPPublicAccessPolicyTest extends PolicyTestCase
{
    /**
     * @covers PKPPublicAccessPolicy
     * @covers HandlerOperationPolicy
     */
    public function testPKPPublicAccessPolicy()
    {
        // Mock a request to the permitted operation.
        $request = $this->getMockRequest('permittedOperation');

        // Instantiate the policy.
        $policy = new PKPPublicAccessPolicy($request, 'permittedOperation');

        // Test default message.
        self::assertEquals('user.authorization.privateOperation', $policy->getAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE));

        // Test getters.
        self::assertEquals($request, $policy->getRequest());
        self::assertEquals(['permittedOperation'], $policy->getOperations());

        // Test the effect with a public operation.
        self::assertEquals(AuthorizationPolicy::AUTHORIZATION_PERMIT, $policy->effect());

        // Test the effect with a private operation
        $request = $this->getMockRequest('privateOperation');
        $policy = new PKPPublicAccessPolicy($request, 'permittedOperation');
        self::assertEquals(AuthorizationPolicy::AUTHORIZATION_DENY, $policy->effect());
    }
}
