<?php

/**
 * @file tests/classes/security/authorization/RoleBasedHandlerOperationPolicyTest.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RoleBasedHandlerOperationPolicyTest
 * @ingroup tests_classes_security_authorization
 * @see RoleBasedHandlerOperation
 *
 * @brief Test class for the RoleBasedHandlerOperation class
 */

import('lib.pkp.tests.classes.security.authorization.PolicyTestCase');
import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

class RoleBasedHandlerOperationPolicyTest extends PolicyTestCase {
	/**
	 * @covers RoleBasedHandlerOperationPolicy
	 */
	public function testUserGroupAuthorization() {
		// Create a user-group-based test environment.
		$contextManipulationPolicy = $this->getAuthorizationContextManipulationPolicy();

		// Test the user-group/role policy with a default
		// authorized request.
		$request = $this->getMockRequest('permittedOperation');
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_TEST, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($contextManipulationPolicy);
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());

		// Test the default message.
		self::assertEquals('user.authorization.roleBasedAccessDenied', $rolePolicy->getAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE));

		// Test the policy with a non-authorized role.
		$nonAuthorizedRole = 0x01;
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, $nonAuthorizedRole, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($contextManipulationPolicy);
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());

		// Test the policy with an authorized role but a non-authorized operation.
		$request = $this->getMockRequest('privateOperation');
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_TEST, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($contextManipulationPolicy);
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());
	}

	/**
	 * @covers RoleBasedHandlerOperationPolicy
	 */
	public function testRoleAuthorization() {
		// Create a test user;
		import('classes.user.User');
		$testUser = new User();
		$testUser->setId(3);

		// Create a test context.
		$application = PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();
		if ($contextDepth > 0) {
			$testContext = new DataObject();
			$testContextId = 5;
			$testContext->setId($testContextId);
			$userHasRoleContextArgs = array($testContextId, $testUser->getId());
			$userHasRoleSiteArgs = array(0, $testUser->getId());
		} else {
			$testContext = null;
			$userHasRoleSiteArgs = $userHasRoleContextArgs = array($testUser->getId());
		}

		// Create a non-authorized role.
		$nonAuthorizedRole = ROLE_ID_SITE_ADMIN;

		// Test the user-group/role policy with a default
		// authorized request.
		$request = $this->getMockRequest('permittedOperation', $testContext, $testUser);
		$this->mockRoleDao(
			array(
				array(
					'userHasRoleExpectedArgs' => array_merge($userHasRoleContextArgs, array(ROLE_ID_TEST)),
					'userHasRoleReturnValue' => true
				)
			)
		);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_TEST, $nonAuthorizedRole), 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());

		// Test the user-group/role policy with a non-authorized role.
		$this->mockRoleDao(
			array(
				array(
					// The context is 0 this time because we're looking at the site admin role.
					'userHasRoleExpectedArgs' => array_merge($userHasRoleSiteArgs, array($nonAuthorizedRole)),
					'userHasRoleReturnValue' => false
				)
			)
		);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, $nonAuthorizedRole, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());


		// Test the policy with an authorized role but a non-authorized operation.
		$request = $this->getMockRequest('privateOperation', null, $testUser);
		$userHasRoleInvocation= array(
			// The context is 0 this time because we're testing without a context.
			'userHasRoleExpectedArgs' => array_merge($userHasRoleSiteArgs, array(ROLE_ID_TEST)),
			'userHasRoleReturnValue' => true
		);
		$this->mockRoleDao(array($userHasRoleInvocation));
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_TEST, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());

		// Test the policy with an authorized role and a non-authorized operation
		// but bypass the the operation check.
		// FIXME: Remove the "bypass operation check" code once we've removed the
		// HandlerValidatorRole compatibility class, see #5868.
		$this->mockRoleDao(array($userHasRoleInvocation));
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_TEST, array(), 'some.message', false, true);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());

		// Test the "all roles must match" feature.
		$request = $this->getMockRequest('permittedOperation', $testContext, $testUser);
		$this->mockRoleDao(
			array(
				array(
					'userHasRoleExpectedArgs' => array_merge($userHasRoleContextArgs, array(ROLE_ID_TEST)),
					'userHasRoleReturnValue' => true
				),
				array(
					'userHasRoleExpectedArgs' => array_merge($userHasRoleSiteArgs, array(ROLE_ID_SITE_ADMIN)),
					'userHasRoleReturnValue' => true
				)
			)
		);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_TEST, ROLE_ID_SITE_ADMIN), 'permittedOperation', 'some.message', true, false);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());

		// Test again the "all roles must match" feature but this time
		// with one role not matching.
		$this->mockRoleDao(
			array(
				array(
					'userHasRoleExpectedArgs' => array_merge($userHasRoleContextArgs, array(ROLE_ID_TEST)),
					'userHasRoleReturnValue' => true
				),
				array(
					'userHasRoleExpectedArgs' => array_merge($userHasRoleSiteArgs, array(ROLE_ID_SITE_ADMIN)),
					'userHasRoleReturnValue' => false
				)
			)
		);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_TEST, ROLE_ID_SITE_ADMIN), 'permittedOperation', 'some.message', true, false);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());
	}
}
?>