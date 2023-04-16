<?php

/**
 * @file controllers/grid/admin/plugins/AdminPluginGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdminPluginGridHandler
 *
 * @ingroup controllers_grid_admin_plugins
 *
 * @brief Handle site level plugins grid requests.
 */

namespace PKP\controllers\grid\admin\plugins;

use APP\core\Application;
use PKP\controllers\grid\plugins\PluginGridHandler;
use PKP\controllers\grid\plugins\PluginGridRow;
use PKP\security\authorization\PluginAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class AdminPluginGridHandler extends PluginGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $roles = [Role::ROLE_ID_SITE_ADMIN];

        $this->addRoleAssignment($roles, ['plugin']);

        parent::__construct($roles);
    }

    //
    // Overriden template methods.
    //
    /**
     * @see GridHandler::getRowInstance()
     */
    public function getRowInstance()
    {
        return new PluginGridRow($this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES));
    }

    /**
     * @see GridHandler::authorize()
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $category = $request->getUserVar('category');
        $pluginName = $request->getUserVar('plugin');
        $verb = $request->getUserVar('verb');

        if ($category && $pluginName) {
            if ($verb) {
                $accessMode = PluginAccessPolicy::ACCESS_MODE_MANAGE;
            } else {
                $accessMode = PluginAccessPolicy::ACCESS_MODE_ADMIN;
            }

            $this->addPolicy(new PluginAccessPolicy($request, $args, $roleAssignments, $accessMode));
        } else {
            $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            foreach ($roleAssignments as $role => $operations) {
                $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
            }
            $this->addPolicy($rolePolicy);
        }

        return parent::authorize($request, $args, $roleAssignments);
    }
}
