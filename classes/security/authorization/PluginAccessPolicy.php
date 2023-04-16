<?php
/**
 * @file classes/security/authorization/PluginAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to plugins.
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\PluginLevelRequiredPolicy;
use PKP\security\authorization\internal\PluginRequiredPolicy;
use PKP\security\Role;

class PluginAccessPolicy extends PolicySet
{
    public const ACCESS_MODE_MANAGE = 1;
    public const ACCESS_MODE_ADMIN = 2;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     * @param int $accessMode
     */
    public function __construct($request, &$args, $roleAssignments, $accessMode = self::ACCESS_MODE_ADMIN)
    {
        parent::__construct();

        // A valid plugin is required.
        $this->addPolicy(new PluginRequiredPolicy($request));

        // Managers and site admin have access to plugins. We'll have to define
        // differentiated policies for those roles in a policy set.
        $pluginAccessPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        $pluginAccessPolicy->setEffectIfNoPolicyApplies(AuthorizationPolicy::AUTHORIZATION_DENY);

        //
        // Managerial role
        //
        if (isset($roleAssignments[Role::ROLE_ID_MANAGER])) {
            if ($accessMode & self::ACCESS_MODE_MANAGE) {
                // Managers have edit settings access mode...
                $managerPluginAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
                $managerPluginAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_MANAGER, $roleAssignments[Role::ROLE_ID_MANAGER]));

                // ...only to context-level plugins.
                $managerPluginAccessPolicy->addPolicy(new PluginLevelRequiredPolicy($request, true));

                $pluginAccessPolicy->addPolicy($managerPluginAccessPolicy);
            }
        }

        //
        // Site administrator role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SITE_ADMIN])) {
            // Site admin have access to all plugins...
            $siteAdminPluginAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $siteAdminPluginAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SITE_ADMIN, $roleAssignments[Role::ROLE_ID_SITE_ADMIN]));

            if ($accessMode & self::ACCESS_MODE_MANAGE) {
                // ...of site level only.
                $siteAdminPluginAccessPolicy->addPolicy(new PluginLevelRequiredPolicy($request, false));
            }

            $pluginAccessPolicy->addPolicy($siteAdminPluginAccessPolicy);
        }

        $this->addPolicy($pluginAccessPolicy);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PluginAccessPolicy', '\PluginAccessPolicy');
    define('ACCESS_MODE_MANAGE', PluginAccessPolicy::ACCESS_MODE_MANAGE);
    define('ACCESS_MODE_ADMIN', PluginAccessPolicy::ACCESS_MODE_ADMIN);
}
