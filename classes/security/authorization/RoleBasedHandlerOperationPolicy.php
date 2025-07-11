<?php

/**
 * @file classes/security/authorization/RoleBasedHandlerOperationPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RoleBasedHandlerOperationPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations via role based access
 *  control.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\core\PKPBaseController;

class RoleBasedHandlerOperationPolicy extends HandlerOperationPolicy
{
    /** @var array the target roles */
    public $_roles = [];

    /** @var bool */
    public $_allRoles;

    /**
     * Constructor
     *
     * @param \PKP\core\PKPRequest $request
     * @param array|integer $roles either a single role ID or an array of role ids
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param string $message a message to be displayed if the authorization fails
     * @param bool $allRoles whether all roles must match ("all of") or whether it is
     *  enough for only one role to match ("any of"). Default: false ("any of")
     */
    public function __construct(
        $request,
        $roles,
        $operations,
        $message = 'user.authorization.roleBasedAccessDenied',
        $allRoles = false
    ) {
        parent::__construct($request, $operations, $message);

        // Make sure a single role doesn't have to be
        // passed in as an array.
        assert(is_integer($roles) || is_array($roles));
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        $this->_roles = $roles;
        $this->_allRoles = $allRoles;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        // Check whether the user has one of the allowed roles
        // assigned. If that's the case we'll permit access.
        // Get user roles grouped by context.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (empty($userRoles)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if (!$this->_checkUserRoleAssignment($userRoles)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        if (!$this->_checkOperationWhitelist()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // If the request run through laravel route,
        // we need to have controller based checking.
        if ($routeController = PKPBaseController::getRouteController()) {

            $routeController->markRoleAssignmentsChecked();

            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        $handler = $this->getRequest()->getRouter()->getHandler();
        $handler->markRoleAssignmentsChecked();

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }


    //
    // Private helper methods
    //
    /**
     * Check whether the given user has been assigned
     * to any of the allowed roles. If so then grant
     * access.
     *
     * @param array $userRoles
     *
     * @return bool
     */
    public function _checkUserRoleAssignment($userRoles)
    {
        // Find matching roles.
        $foundMatchingRole = false;
        foreach ($this->_roles as $roleId) {
            $foundMatchingRole = in_array($roleId, $userRoles);

            if ($this->_allRoles) {
                if (!$foundMatchingRole) {
                    // When the "all roles" flag is switched on then
                    // one missing role is enough to fail.
                    return false;
                }
            } else {
                if ($foundMatchingRole) {
                    // When the "all roles" flag is not set then
                    // one matching role is enough to succeed.
                    return true;
                }
            }
        }

        if ($this->_allRoles) {
            // All roles matched, otherwise we'd have failed before.
            return true;
        } else {
            // None of the roles matched, otherwise we'd have succeeded already.
            return false;
        }
    }
}
