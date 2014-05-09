<?php

/**
 * @file tests/classes/handler/validation/HandlerValidatorRolesTest.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidatorRolesTest
 * @ingroup tests_classes_handler_validation
 * @see HandlerValidatorRoles
 *
 * @brief Test class for HandlerValidatorRoles.
 */

define('HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE', 0x01);
define('HANDLER_VALIDATOR_ROLES_MANAGER_ROLE', 0x02);
define('HANDLER_VALIDATOR_ROLES_SITE_ADMIN_ROLE', 0x03);

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.handler.validation.HandlerValidatorRoles');

class HandlerValidatorRolesTest extends PKPTestCase {

	/**
	 * @covers HandlerValidatorRoles
	 */
	public function testHandlerValidatorRoles() {
		$contextDepth = PKPApplication::getApplication()->getContextDepth();

		// tests: userId, role type, user has role in context?, match all roles?, expected result of isValid()
		$tests = array(
			// Test different role types.
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), true, false, true),
			array(7, array(HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), true, true, true),
			array(7, array(HANDLER_VALIDATOR_ROLES_SITE_ADMIN_ROLE), true, false, true),
			// Test logged out user.
			array(null, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), true, false, false),
			// Test user without context role.
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), false, false, false),
			// Test with several roles ("all" switched off) - expected role context is null because we don't test expected context for multiple roles
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, false), false, true),
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, true), false, true),
			// Test with several roles ("all" switched on) - expected role context is null because we don't test expected context for multiple roles
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, false), true, false),
			array(7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, true), true, true)
		);
		foreach($tests as $testNumber => $test) $this->executeHandlerValidatorRolesTest($test, $testNumber);
	}

	/**
	 * Execute a test instance
	 * @param $test array
	 */
	private function executeHandlerValidatorRolesTest($test, $testNumber) {
		$someUserId = $test[0];
		$roles = array();
		foreach($test[1] as $roleType) $roles[] = $this->getTestRoleId($roleType);
		$userHasRoleInContext = $test[2];

		// Mock a handler.
		$userRoles = array();
		if ($someUserId ==  null) {
			// Testing logged out user. No user roles in context then.
			$userRoles = null;
		}
		// Testing contexts where user don't have a role.
		if (!$userHasRoleInContext) {
			$userRoles = null;
		}

		// Testing multiple roles.
		if (is_array($userHasRoleInContext)) {
			foreach ($userHasRoleInContext as $key => $hasRole) {
				if ($hasRole) {
					$userRoles[] = $roles[$key];
				}
			}
		}

		// Other tests.
		if (!is_null($userRoles) && empty($userRoles)) {
			$userRoles = $roles;
		}

		$mockHandler = $this->getMockHandler($userRoles);

		// Run the test
		$validator = new HandlerValidatorRoles($mockHandler, true, null, array(), $roles, $test[3]);
		self::assertEquals($test[4], $validator->isValid(), "Test number $testNumber failed.");
	}

	/**
	 * Returns a sample role id of a given role type
	 * @param $roleType integer
	 * @return integer
	 */
	private function getTestRoleId($roleType) {
		$roleDao = DAORegistry::getDAO('RoleDAO');

		switch($roleType) {
			case HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE:
				$roleId = ROLE_ID_AUTHOR;
				break;

			case HANDLER_VALIDATOR_ROLES_MANAGER_ROLE:
				$roleId = ROLE_ID_MANAGER;
				break;

			case HANDLER_VALIDATOR_ROLES_SITE_ADMIN_ROLE:
				$roleId = ROLE_ID_SITE_ADMIN;
				break;

			default:
				self::fail('Invalid role type.');
		}

		self::assertNotNull($roleId);
		return $roleId;
	}


	/**
	 * Creates a mock handler object.
	 * @return a Handler that returns the authorized context.
	 */
	private function getMockHandler($roles) {
		// Mock a handler.
		$mockHandler = $this->getMock('PKPHandler', array('getAuthorizedContext'));

		// Set up the mock getAuthorizedContext() method.
		$mockHandler->expects($this->once())
		->method('getAuthorizedContext')
		->will($this->returnValue(array(ASSOC_TYPE_USER_ROLES => $roles)));

		return $mockHandler;
	}
}
?>
