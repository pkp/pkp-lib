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
use PKP\userGroup\UserGroup;

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
    public function effect(): int
    {
        $request = $this->_request;
        $user = $request->getUser();

        if (!$user) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        $context = $request->getRouter()->getContext($request);

        $userGroups = UserGroup::withUserIds([$user->getId()])
            ->withContextIds($context ? [$context->getId(), Application::SITE_CONTEXT_ID] : [Application::SITE_CONTEXT_ID])
            ->whereHas('userUserGroups', function ($query) use ($user) {
                $query->withUserId($user->getId())->withActive();
            })
            ->get()
            ->all();

        $roleIds = array_map(fn ($userGroup) => $userGroup->roleId, $userGroups);
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES, $roleIds);
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP, $userGroups);

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\UserRolesRequiredPolicy', '\UserRolesRequiredPolicy');
}
