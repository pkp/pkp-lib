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

define('ROLE_ID_TEST_2', 0x8888);
define('ROLE_ID_NON_AUTHORIZED', 0x7777);
define('ROLE_ID_OCS_MANAGERIAL_ROLE', 0x6666);

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

	private
		/**
		* @var array
		* @see mockRoleDao() below
		*/
		$getByUserIdGroupedByContextInvocations,
		/**
		 * @var RoleDAO
		*/
		$roleDao;

	/**
	 * @see PKPTestCase::setUp()
	 */
	protected function setUp() {
		parent::setUp();
		$this->roleDao = DAORegistry::getDAO('RoleDAO');
	}

	/**
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() {
		DAORegistry::registerDAO('RoleDAO', $this->roleDao);
		Registry::set('user', $user = null);
		parent::tearDown();
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
		$testContextObjects = array();
		if ($contextDepth > 0) {
			for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
				$testContext = new DataObject();
				$testContext->setId(5);
				$testContextObjects[] = $testContext;
				unset($testContext);
			}
		}

		// Construct the user roles array, based on context depth.
		switch ($contextDepth) {
			case 1:
				// OJS and OMP cases.
				$getByUserIdGroupedByContextInvocation = array(
					'getByUserIdGroupedByContextExpectedArgs' => $testUser->getId(),
					'getByUserIdGroupedByContextReturnValue' => array(
						// Numeric array index is the context id.
						// In this case, is zero because we have a site level role.
						CONTEXT_ID_NONE => array(ROLE_ID_SITE_ADMIN => ROLE_ID_SITE_ADMIN),
						// Press/Journal context.
						5 => array(
							ROLE_ID_TEST => ROLE_ID_TEST,
							ROLE_ID_TEST_2 => ROLE_ID_TEST_2
						)
					)
				);
				$allUserRoles = array(ROLE_ID_TEST, ROLE_ID_TEST_2, ROLE_ID_SITE_ADMIN);
				break;
			case 2:
				// OCS case.
				$getByUserIdGroupedByContextInvocation = array(
					'getByUserIdGroupedByContextExpectedArgs' => $testUser->getId(),
					'getByUserIdGroupedByContextReturnValue' => array(
						// First numeric array index is for the conference. The
						// second is for the schedConf.
						// Both are 0 because we are testing a role in site context.
						CONTEXT_ID_NONE => array(CONTEXT_ID_NONE => array(ROLE_ID_SITE_ADMIN => ROLE_ID_SITE_ADMIN)),
						// Conference context.
						5 => array(
							// No context (still conference context).
							CONTEXT_ID_NONE => array(ROLE_ID_OCS_MANAGERIAL_ROLE => ROLE_ID_OCS_MANAGERIAL_ROLE),
							// SchedConf context.
							5 => array(
								ROLE_ID_TEST => ROLE_ID_TEST,
								ROLE_ID_TEST_2 => ROLE_ID_TEST_2
							)
						)
					)
				);
				$allUserRoles = array(ROLE_ID_TEST, ROLE_ID_TEST_2, ROLE_ID_SITE_ADMIN, ROLE_ID_OCS_MANAGERIAL_ROLE);
				break;
			default:
				$getByUserIdGroupedByContextInvocation = array();
				break;
		}

		// Configure the mock Role DAO.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);

		// Test the user-group/role policy with a default
		// authorized request.
		$request = $this->getMockRequest('permittedOperation', $testContextObjects, $testUser);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_TEST, ROLE_ID_NON_AUTHORIZED), 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertArrayHasKey(ROLE_ID_TEST, $authorizedRoles);

		// Test the user-group/role policy with a non-authorized role.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_NON_AUTHORIZED, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertEmpty($authorizedRoles);

		// Test the policy with an authorized role but a non-authorized operation.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);
		$request = $this->getMockRequest('privateOperation', null, $testUser);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_SITE_ADMIN, 'permittedOperation');
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertEmpty($authorizedRoles);

		// Test the policy with an authorized role and a
		// non-authorized operation but bypass the the operation check.
		// FIXME: Remove the "bypass operation check" code once we've removed the
		// HandlerValidatorRole compatibility class, see #5868.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, ROLE_ID_SITE_ADMIN, array(), 'some.message', false, true);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertArrayHasKey(ROLE_ID_SITE_ADMIN, $authorizedRoles);

		// Test the "all roles must match" feature.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);
		$request = $this->getMockRequest('permittedOperation', $testContextObjects, $testUser);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, $allUserRoles, 'permittedOperation', 'some.message', true, false);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_PERMIT, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertArrayHasKey(ROLE_ID_TEST, $authorizedRoles);
		self::assertArrayHasKey(ROLE_ID_TEST_2, $authorizedRoles);
		self::assertArrayHasKey(ROLE_ID_SITE_ADMIN, $authorizedRoles);
		if ($contextDepth == 2) {
			self::assertArrayHasKey(ROLE_ID_OCS_MANAGERIAL_ROLE, $authorizedRoles);
		}

		// Test again the "all roles must match" feature but this time
		// with one role not matching.
		$this->mockRoleDao($getByUserIdGroupedByContextInvocation);
		array_push($allUserRoles, ROLE_ID_NON_AUTHORIZED);
		$rolePolicy = new RoleBasedHandlerOperationPolicy($request, $allUserRoles, 'permittedOperation', 'some.message', true, false);
		$decisionManager = new AuthorizationDecisionManager();
		$decisionManager->addPolicy($rolePolicy);
		self::assertEquals(AUTHORIZATION_DENY, $decisionManager->decide());
		$authorizedRoles = $decisionManager->getAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES);
		self::assertEmpty($authorizedRoles);
	}

	/**
	* Mocks the role DAO.
	* @param $getByUserIdGroupedByContextInvocations array a two dimensional array.
	*  - the first key is the invocation
	*  - the second key's first entry contains the expected arguments for the userHasRole() call
	*  - the second key's second entry contains the return value for the userHasRole() call
	* @param $userHasRoleReturnValue boolean
	*/
	protected function mockRoleDao($getByUserIdGroupedByContextInvocations) {
		// Create a mock role DAO.
		import('classes.security.RoleDAO');
		$mockRoleDao = $this->getMock('RoleDAO', array('getRoleIdFromPath', 'getByUserIdGroupedByContext'));

		// Mock getRoleIdFromPath().
		$mockRoleDao->expects($this->any())
		->method('getRoleIdFromPath')
		->will($this->returnValue(ROLE_ID_OCS_MANAGERIAL_ROLE));

		// Mock userHasRole().
		$mockRoleDao->expects($this->once())
		->method('getByUserIdGroupedByContext')
		->will($this->returnCallback(array($this, 'mockGetByUserIdGroupedByContext')));

		// Register the mock RoleDAO.
		DAORegistry::registerDAO('RoleDAO', $mockRoleDao);

		// Configure the mock getByUserIdGroupedByContext() call.
		$this->getByUserIdGroupedByContextInvocations = $getByUserIdGroupedByContextInvocations;
	}

	/**
	 * Callback used by the mock RoleDAO created
	 * in mockRoleDao().
	 * @see RoleDAO::getByUserIdGroupedByContext() in the different
	 *  apps for the expected arguments. These depend on the context
	 *  depth of the application.
	 * @return boolean
	 */
	public function mockGetByUserIdGroupedByContext() {
		$getByUserIdGroupedByContextInvocation = $this->getByUserIdGroupedByContextInvocations;
		$functionArgs = func_get_args();
		self::assertEquals($getByUserIdGroupedByContextInvocation['getByUserIdGroupedByContextExpectedArgs'], $functionArgs[0]);
		return $getByUserIdGroupedByContextInvocation['getByUserIdGroupedByContextReturnValue'];
	}
}
?>