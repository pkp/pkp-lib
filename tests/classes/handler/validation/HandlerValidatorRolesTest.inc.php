<?php

/**
 * @file tests/classes/handler/validation/HandlerValidatorRolesTest.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
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
import('lib.pkp.classes.core.PKPRouter');

class HandlerValidatorRolesTest extends PKPTestCase {
	private $roleExistsReturnValues;

	/**
	 * @covers HandlerValidatorRoles
	 */
	public function testHandlerValidatorRoles() {
		$contextDepth = PKPApplication::getApplication()->getContextDepth();

		// tests: contextId, userId, role type, user has role in context?, match all roles?, expected role context, expected result of isValid()
		$fullContext = array_fill(0, $contextDepth, 3);
		$emptyContext = array();
		$tests = array(
			// Test different role types
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), true, false, array_fill(0, $contextDepth, 3), true),
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), true, true, array_pad(array(3), $contextDepth, 0), true),
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_SITE_ADMIN_ROLE), true, false, array_fill(0, $contextDepth, 0), true),
			// Test logged out user
			array($fullContext, null, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), true, false, array_fill(0, $contextDepth, 3), false),
			// Test incomplete context
			array($emptyContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), true, false, array_fill(0, $contextDepth, 0), true),
			array($emptyContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE), false, false, array_fill(0, $contextDepth, 0), false),
			// Test with several roles ("all" switched off) - expected role context is null because we don't test expected context for multiple roles
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, false), false, null, true),
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, true), false, null, true),
			// Test with several roles ("all" switched on) - expected role context is null because we don't test expected context for multiple roles
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, false), true, null, false),
			array($fullContext, 7, array(HANDLER_VALIDATOR_ROLES_FULL_CONTEXT_ROLE, HANDLER_VALIDATOR_ROLES_MANAGER_ROLE), array(true, true), true, null, true)
		);
		foreach($tests as $testNumber => $test) $this->executeHandlerValidatorRolesTest($test, $testNumber);
	}

	/**
	 * Execute a test instance
	 * @param $test array
	 */
	private function executeHandlerValidatorRolesTest($test, $testNumber) {
		$contextIds = $test[0];
		$someUserId = $test[1];
		$roles = array();
		foreach($test[2] as $roleType) $roles[] = $this->getTestRoleId($roleType);
		$userHasRoleInContext = $test[3];

		// Mock a fully qualified context
		$application = PKPApplication::getApplication();
		$this->mockContext($contextIds, $someUserId);

		// Mock the role DAO
		if (count($roles) == 1) {
			// Only test expected arguments when testing a single role
			$roleExistsExpectedArgs = array_merge($test[5], array($someUserId, $roles[0]));
		} else {
			$roleExistsExpectedArgs = null;
		}
		$roleDao = $this->getMockRoleDao($roleExistsExpectedArgs, $userHasRoleInContext);

		// Run the test
		$validator = new HandlerValidatorRoles($handler = null, true, null, array(), $roles, $test[4]);
		self::assertEquals($test[6], $validator->isValid(), "Test number $testNumber failed.");
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
				$roleId = $roleDao->getRoleIdFromPath('author');
				// Harvester doesn't have an author role so use submitter there.
				if (is_null($roleId)) $roleId = $roleDao->getRoleIdFromPath('submitter');
				break;

			case HANDLER_VALIDATOR_ROLES_MANAGER_ROLE:
				$roleId = $roleDao->getRoleIdFromPath('manager');
				break;

			case HANDLER_VALIDATOR_ROLES_SITE_ADMIN_ROLE:
				$roleId = $roleDao->getRoleIdFromPath('admin');
				break;

			default:
				self::fail('Invalid role type.');
		}

		self::assertNotNull($roleId);
		return $roleId;
	}

	/**
	 * Creates a mock context object.
	 * @param $contextClass string the class to be instantiated
	 * @param $contextId integer
	 * @return a DataObject that returns the given id or
	 *  null if no id is given.
	 */
	private function getMockContext($contextClass, $contextId) {
		if (is_null($contextId)) {
			return null;
		}

		// Mock a context
		$mockContext = $this->getMock($contextClass, array('getId'));

		// Set up the mock getId() method
		$mockContext->expects($this->any())
		            ->method('getId')
		            ->will($this->returnValue($contextId));

		return $mockContext;
	}

	/**
	 * Mocks the context of an application by manipulating
	 * the registered DAOs.
	 * @param $contextIds array an array of context IDs to be set up.
	 *  Missing IDs will be replaced with null.
	 * @param $userId the user id to be returned by the request
	 */
	private function mockContext($contextIds, $userId) {
		$application = PKPApplication::getApplication();

		// Mock the context
		if (count($contextIds)) {
			$_SERVER['PATH_INFO'] = '/'.implode('/', array_fill(0, count($contextIds), 'context')).'/';
		} else {
			$_SERVER['PATH_INFO'] = '/';
		}
		foreach($application->getContextList() as $contextName) {
			// Get a mock DAO for the requested context.
			$contextClass = ucfirst($contextName);
			$daoName = $contextClass.'DAO';
			$daoMethod = 'get'.$contextClass.'ByPath';
			$mockContextDao = $this->getMock($daoName, array($daoMethod));

			// Set up the mock context retrieval method
			$mockContextDao->expects($this->any())
			               ->method($daoMethod)
			               ->will($this->returnValue($this->getMockContext($contextClass, array_shift($contextIds))));

			// Register the mock context DAO
			DAORegistry::registerDAO($daoName, $mockContextDao);
			unset($mockContextDao);
		}

		// Mock the UserDAO and the session with a test user
		if (is_null($userId)) {
			$user = null;
		} else {
			$user = new User();
			$user->setId($userId);
		}
		$mockUserDao = $this->getMock('UserDAO', array('getUser'));
		$mockUserDao->expects($this->any())
		            ->method('getUser')
		            ->will($this->returnValue($user));
		DAORegistry::registerDAO('UserDAO', $mockUserDao);
		$sessionManager =& SessionManager::getManager();
		$session =& $sessionManager->getUserSession();
		$session->setUserId($userId);

		// Mock the request
		$mockRequest = $this->getMock('Request', array('getRouter', 'getBasePath', 'getRemoteAddr', 'getUserAgent'));
		$router = new PKPRouter();
		$router->setApplication($application);
		$mockRequest->expects($this->any())
		            ->method('getRouter')
		            ->will($this->returnValue($router));
		$mockRequest->expects($this->any())
		            ->method('getBasePath')
		            ->will($this->returnValue('/'));
		$mockRequest->expects($this->any())
		            ->method('getRemoteAddr')
		            ->will($this->returnValue(''));
		$mockRequest->expects($this->any())
		            ->method('getUserAgent')
		            ->will($this->returnValue(''));
		Registry::set('request', $mockRequest);
	}

	/**
	 * Create a mock role DAO
	 * @param $roleExistsReturnValue boolean
	 * @return RoleDAO
	 */
	private function getMockRoleDao($roleExistsExpectedArgs, $roleExistsReturnValue) {
		// Mock a RoleDAO object
		$mockRoleDao = $this->getMock('RoleDAO', array('roleExists'));

		// Set up the mock getRoleIdFromPath() method
		$intermediateResult = $mockRoleDao->expects($this->any())->method('roleExists');
		$expectedUserId = $roleExistsExpectedArgs[count($roleExistsExpectedArgs)-2];
		if (is_array($roleExistsReturnValue)) {
			// Test with several roles

			// executions return different results
			$this->roleExistsReturnValues = $roleExistsReturnValue;
			$intermediateResult->will($this->returnCallback(array($this, 'roleExistsCallback')));
		} else {
			// Test with a single role

			// When the user does not exist then roleExists() will never be called.
			if (!is_null($expectedUserId)) {
				$intermediateResult = call_user_func_array(array($intermediateResult, 'with'), $roleExistsExpectedArgs);
			}

			// executions always return the same result
			$intermediateResult->will($this->returnValue($roleExistsReturnValue));
		}

		DAORegistry::registerDAO('RoleDAO', $mockRoleDao);
		return $mockRoleDao;
	}

	public function roleExistsCallback() {
		// Return the next return value
		return array_shift($this->roleExistsReturnValues);
	}
}
?>
