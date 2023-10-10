<?php
/**
 * @file classes/security/authorization/UserRolesRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRolesRequiredPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Policy to build an authorized user roles object. Because we may have
 * users with no roles, we don't deny access when no user roles are found.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use APP\core\Request;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\security\RoleDAO;

class UserRolesRequiredPolicy extends AuthorizationPolicy
{
    /** @var Request */
    public $_request;

    /**
     * Constructor
     *
     * @param Request $request
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

        $context = $request->getRouter()->getContext($request);
        $roleContext = $context?->getId() ?? Application::CONTEXT_ID_NONE;

        $contextRoles = $this->_getContextRoles($roleContext, $userRoles);

        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES, $contextRoles);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }

    /**
     * Get the current context roles from all user roles.
     * @param array<int,Role[]> $userRoles List of roles grouped by contextId
     */
    protected function _getContextRoles(int $contextId, array $userRoles): array
    {
        // Adapt the role context based on the passed role id.
        $contextRoles = [];

        // Check if user has site level or manager roles.
        if (array_key_exists(Application::CONTEXT_ID_NONE, $userRoles) &&
            array_key_exists(Role::ROLE_ID_SITE_ADMIN, $userRoles[Application::CONTEXT_ID_NONE])) {
            // site level role
            $contextRoles[] = Role::ROLE_ID_SITE_ADMIN;
        }

        // Get the user roles related to the passed context.
        if ($contextId != Application::CONTEXT_ID_NONE && isset($userRoles[$contextId])) {
            // Filter the user roles to the found context id.
            return array_merge(
                $contextRoles,
                array_map(fn ($role) => $role->getRoleId(), $userRoles[$contextId])
            );
        } else {
            // Context id not present in user roles array.
            return $contextRoles;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\UserRolesRequiredPolicy', '\UserRolesRequiredPolicy');
}
