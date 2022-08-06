<?php

/**
 * @file tests/classes/security/authorization/AuthorizationPolicyTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorizationPolicyTest
 * @ingroup tests_classes_security_authorization
 *
 * @see AuthorizationPolicy
 *
 * @brief Test class for AuthorizationPolicy
 */

namespace PKP\tests\classes\security\authorization;

use APP\core\Application;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\tests\PKPTestCase;

class AuthorizationPolicyTest extends PKPTestCase
{
    /**
     * @covers AuthorizationPolicy
     */
    public function testAuthorizationPolicy()
    {
        $policy = new AuthorizationPolicy('some message');

        // Test advice.
        self::assertTrue($policy->hasAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE));
        self::assertFalse($policy->hasAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY));
        self::assertEquals('some message', $policy->getAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE));
        self::assertNull($policy->getAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY));

        // Test authorized context objects.
        self::assertFalse($policy->hasAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP));
        $someContextObject = new \PKP\core\DataObject();
        $someContextObject->setData('test1', 'test1');
        $policy->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP, $someContextObject);
        self::assertTrue($policy->hasAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP));
        self::assertEquals($someContextObject, $policy->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP));
        self::assertEquals([Application::ASSOC_TYPE_USER_GROUP => $someContextObject], $policy->getAuthorizedContext());

        // Test authorized context.
        $someOtherContextObject = new \PKP\core\DataObject();
        $someOtherContextObject->setData('test2', 'test2');
        $authorizedContext = [Application::ASSOC_TYPE_USER_GROUP => $someOtherContextObject];
        $policy->setAuthorizedContext($authorizedContext);
        self::assertEquals($authorizedContext, $policy->getAuthorizedContext());

        // Test default policies.
        self::assertTrue($policy->applies());
        self::assertEquals(AuthorizationPolicy::AUTHORIZATION_DENY, $policy->effect());
    }
}
