<?php

/**
 * @file tests/classes/security/authorization/PolicyTestCase.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PolicyTestCase
 *
 * @ingroup tests_classes_security_authorization
 *
 * @see RoleBasedHandlerOperation
 *
 * @brief Abstract base test class that provides infrastructure
 *  for several types of policy tests.
 */

namespace PKP\tests\classes\security\authorization;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use Mockery\Mock;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\handler\PKPHandler;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;
use PKP\tests\PKPTestCase;
use PKP\user\User;

abstract class PolicyTestCase extends PKPTestCase
{
    protected const ROLE_ID_TEST = 0x9999;

    private ?Context $contextObject = null;
    public $contextObjects;

    /** @var AuthorizationPolicy internal state variable that contains the policy that will be used to manipulate the authorization context */
    private ?AuthorizationPolicy $authorizationContextManipulationPolicy = null;

    /**
     * @copydoc PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'user'];
    }

    /**
     * Return an array with context object(s).
     *
     * @return array
     */
    private function getContextObjects()
    {
        return $this->contextObjects;
    }

    /**
     * Set a context object.
     */
    private function setContextObject(?Context $contextObject)
    {
        $this->contextObject = $contextObject;
    }

    /**
     * Create an authorization context manipulation policy.
     *
     * @return AuthorizationPolicy the policy that
     *  will be used by the decision manager to call this
     *  mock method.
     */
    protected function getAuthorizationContextManipulationPolicy()
    {
        if (is_null($this->authorizationContextManipulationPolicy)) {
            // Use a policy to prepare an authorized context
            // with a user group.
            $policy = $this->getMockBuilder(AuthorizationPolicy::class)
                ->onlyMethods(['effect'])
                ->getMock();
            $policy->expects($this->any())
                ->method('effect')
                ->willReturnCallback($this->mockEffect(...));
            $this->authorizationContextManipulationPolicy = $policy;
        }
        return $this->authorizationContextManipulationPolicy;
    }

    /**
     * Callback method that will be called in place of the effect()
     * method of a mock policy.
     *
     * @return int AUTHORIZATION_PERMIT
     */
    public function mockEffect()
    {
        // Add a user group to the authorized context
        // of the authorization context manipulation policy.
        $policy = $this->getAuthorizationContextManipulationPolicy();
        $userGroup = Repo::userGroup()->newDataObject();
        $userGroup->setRoleId(self::ROLE_ID_TEST);
        $policy->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP, $userGroup);

        // Add user roles array to the authorized context.
        $userRoles = [self::ROLE_ID_TEST, Role::ROLE_ID_SITE_ADMIN];
        $policy->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES, $userRoles);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }

    /**
     * Instantiate a mock request to the given operation.
     *
     * @param string $requestedOp the requested operation
     * @param ?Context $context request context object to be
     * returned by the router.
     * @param User $user a user to be put into the registry.
     *
     * @return PKPRequest|Mock
     */
    protected function getMockRequest($requestedOp, ?Context $context = null, $user = null)
    {
        // Mock a request to the permitted operation.
        $request = new Request();

        $this->setContextObject($context);

        // Mock a router.
        $router = $this->getMockBuilder(PageRouter::class)
            ->onlyMethods(['getHandler', 'getContext', 'getRequestedOp'])
            ->getMock();

        $router->expects($this->any())
            ->method('getHandler')
            ->willReturn(new PKPHandler());

        // Mock the getRequestedOp() method.
        $router->expects($this->any())
            ->method('getRequestedOp')
            ->willReturn($requestedOp);

        // Mock the getContext() method.
        $router->expects($this->any())
            ->method('getContext')
            ->willReturn($context);

        // Put a user into the registry if one has been
        // passed in.
        if ($user instanceof User) {
            Registry::set('user', $user);
        }

        $request->setRouter($router);
        return $request;
    }
}
