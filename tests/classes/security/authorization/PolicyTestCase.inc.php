<?php

/**
 * @file tests/classes/security/authorization/PolicyTestCase.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PolicyTestCase
 * @ingroup tests_classes_security_authorization
 * @see RoleBasedHandlerOperation
 *
 * @brief Abstract base test class that provides infrastructure
 *  for several types of policy tests.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.security.UserGroup');
import('lib.pkp.classes.core.PKPRequest');

define('ROLE_ID_TEST', 0x9999);

abstract class PolicyTestCase extends PKPTestCase {
	private
		/**
		 * @var AuthorizationContext internal state variable that
		 *  contains the policy that will be used to manipulate
		 *  the authorization context
		 */
		$authorizationContextManipulationPolicy,
		/**
		 * @var array
		 * @see mockRoleDao() below
		 */
	 	$roleExistsInvocations;

	/**
	 * Create an authorization context manipulation policy.
	 *
	 * @returns $testPolicy AuthorizationPolicy the policy that
	 *  will be used by the decision manager to call this
	 *  mock method.
	 */
	protected function getAuthorizationContextManipulationPolicy() {
		if (is_null($this->authorizationContextManipulationPolicy)) {
			// Use a policy to prepare an authorized context
			// with a user group.
			$policy = $this->getMock('AuthorizationPolicy', array('effect'));
			$policy->expects($this->any())
			       ->method('effect')
			       ->will($this->returnCallback(array($this, 'mockEffect')));
			$this->authorizationContextManipulationPolicy = $policy;
		}
		return $this->authorizationContextManipulationPolicy;
	}

	/**
	 * Callback method that will be called in place of the effect()
	 * method of a mock policy.
	 * @return integer AUTHORIZATION_PERMIT
	 */
	public function mockEffect() {
		// Add a user group to the authorized context
		// of the authorization context manipulation policy.
		$policy = $this->getAuthorizationContextManipulationPolicy();
		$userGroup = new UserGroup();
		$userGroup->setRoleId(ROLE_ID_TEST);
		$policy->addAuthorizedContextObject(ASSOC_TYPE_USER_GROUP, $userGroup);
		return AUTHORIZATION_PERMIT;
	}

	/**
	 * Instantiate a mock request to the given operation.
	 * @param $requestedOp string the requested operation
	 * @param $context mixed a request context to be returned
	 *  by the router.
	 * @param $user User a user to be put into the registry.
	 * @return PKPRequest
	 */
	protected function getMockRequest($requestedOp, $context = null, $user = null) {
		// Mock a request to the permitted operation.
		$request = new PKPRequest();

		// Mock a router.
		$router = $this->getMock('PKPRouter', array('getRequestedOp', 'getContext'));

		// Mock the getRequestedOp() method.
		$router->expects($this->any())
		       ->method('getRequestedOp')
		       ->will($this->returnValue($requestedOp));

		// Mock the getContext() method.
		$router->expects($this->any())
		       ->method('getContext')
		       ->will($this->returnValue($context));

		// Put a user into the registry if one has been
		// passed in.
		if ($user instanceof User) {
			Registry::set('user', $user);
		}

		$request->setRouter($router);
		return $request;
	}

	/**
	 * Mocks the role DAO.
	 * @param $roleExistsInvocations array a two dimensional array.
	 *  - the first key is the invocation
	 *  - the second key's first entry contains the expected arguments for the roleExists() call
	 *  - the second key's second entry contains the return value for the roleExists() call
	 * @param $roleExistsReturnValue boolean
	 */
	protected function mockRoleDao($roleExistsInvocations) {
		// Create a mock role DAO.
		import('classes.security.RoleDAO');
		$mockRoleDao = $this->getMock('RoleDAO', array('getRoleIdFromPath', 'roleExists'));

		// Mock getRoleIdFromPath().
		$mockRoleDao->expects($this->any())
		            ->method('getRoleIdFromPath')
		            ->will($this->returnValue(ROLE_ID_TEST));

		// Mock roleExists().
		$mockRoleDao->expects($this->any())
		            ->method('roleExists')
		            ->will($this->returnCallback(array($this, 'mockRoleExists')));

		// Register the mock RoleDAO.
		DAORegistry::registerDAO('RoleDAO', $mockRoleDao);

		// Configure the mock roleExists() call.
		$this->roleExistsInvocations = $roleExistsInvocations;
	}

	/**
	 * Callback used by the mock RoleDAO created
	 * in mockRoleDao().
	 * @see RoleDAO::roleExists() in the different apps for the
	 *  expected arguments. These depend on the context depth of
	 *  the application.
	 * @return boolean
	 */
	public function mockRoleExists() {
		$roleExistsInvocation = array_shift($this->roleExistsInvocations);
		self::assertEquals($roleExistsInvocation['roleExistsExpectedArgs'], func_get_args());
		return $roleExistsInvocation['roleExistsReturnValue'];
	}
}
?>