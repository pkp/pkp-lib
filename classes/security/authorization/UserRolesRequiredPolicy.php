<?php
/**
 * @file classes/security/authorization/UserRolesRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRolesRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to build an authorized user roles object. Because we may have
 * users with no roles, we don't deny access when no user roles are found.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\db\DAORegistry;

use PKP\security\Role;

class UserRolesRequiredPolicy extends AuthorizationPolicy
{
    /** @var Request */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct();
        $this->_request = $request;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $request = $this->_request;
        $user = $request->getUser();

        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get all user roles.
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $userRoles = $roleDao->getByUserIdGroupedByContext($user->getId());

        // Prepare an array with the context ids of the request.
        $application = Application::get();
        $contextDepth = $application->getContextDepth();
        $router = $request->getRouter();
        $roleContext = [];
        for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
            $context = $router->getContext($request, $contextLevel);
            $roleContext[] = $context ? $context->getId() : Application::CONTEXT_ID_NONE;
        }

        $contextRoles = $this->_getContextRoles($roleContext, $contextDepth, $userRoles);

        $this->addAuthorizedContextObject(ASSOC_TYPE_USER_ROLES, $contextRoles);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }

    /**
     * Get the current context roles from all user roles.
     *
     * @param array $roleContext
     * @param int $contextDepth
     * @param array $userRoles
     *
     * @return mixed array or null
     */
    public function _getContextRoles($roleContext, $contextDepth, $userRoles)
    {
        // Adapt the role context based on the passed role id.
        $workingRoleContext = $roleContext;
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $contextRoles = [];

        // Check if user has site level or manager roles.
        if ($contextDepth > 0) {
            if (array_key_exists(Application::CONTEXT_ID_NONE, $userRoles) &&
            array_key_exists(Role::ROLE_ID_SITE_ADMIN, $userRoles[Application::CONTEXT_ID_NONE])) {
                // site level role
                $contextRoles[] = Role::ROLE_ID_SITE_ADMIN;
            }
            if ($contextDepth == 2 &&
                array_key_exists(Application::CONTEXT_ID_NONE, $userRoles[$workingRoleContext[0]]) &&
                array_key_exists(Role::ROLE_ID_MANAGER, $userRoles[$workingRoleContext[0]][Application::CONTEXT_ID_NONE])) {
                // This is a main context managerial role (i.e. conference-level).
                $contextRoles[] = Role::ROLE_ID_MANAGER;
            }
        } else {
            // Application has no context.
            return $this->_prepareContextRolesArray($userRoles[Application::CONTEXT_ID_NONE]);
        }

        // Get the user roles related to the passed context.
        for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
            $contextId = $workingRoleContext[$contextLevel - 1];
            if ($contextId != Application::CONTEXT_ID_NONE && isset($userRoles[$contextId])) {
                // Filter the user roles to the found context id.
                $userRoles = $userRoles[$contextId];

                // If we reached the context depth, search for the role id.
                if ($contextLevel == $contextDepth) {
                    return $this->_prepareContextRolesArray($userRoles, $contextRoles);
                }
            } else {
                // Context id not present in user roles array.
                return $contextRoles;
            }
        }
    }

    /**
     * Prepare an array with the passed user roles. Can optionally
     * add those roles to an already created array.
     *
     * @param array $userRoles
     * @param array $contextRoles
     *
     * @return array
     */
    public function _prepareContextRolesArray($userRoles, $contextRoles = [])
    {
        foreach ($userRoles as $role) {
            $contextRoles[] = $role->getRoleId();
        }
        return $contextRoles;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\UserRolesRequiredPolicy', '\UserRolesRequiredPolicy');
}
