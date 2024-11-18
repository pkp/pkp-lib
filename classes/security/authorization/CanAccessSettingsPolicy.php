<?php
/**
 * @file classes/security/authorization/CanAccessSettingsPolicy.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CanAccessSettingsPolicy
 *
 * @brief Check to ensure that the user has access to the context settings area.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\security\Role;

class CanAccessSettingsPolicy extends AuthorizationPolicy
{
    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // At least one user group must be an admin, or a manager with setup access.
        $userGroups = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_GROUP);
        foreach ($userGroups as $userGroup) {
            if ($userGroup->getRoleId() == ROLE_ID_SITE_ADMIN) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            if ($userGroup->getRoleId() == Role::ROLE_ID_MANAGER && $userGroup->getPermitSettings()) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
