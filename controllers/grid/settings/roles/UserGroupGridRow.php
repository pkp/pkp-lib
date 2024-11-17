<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridRow
 *
 * @ingroup controllers_grid_settings_roles
 *
 * @brief User group grid row definition
 */

namespace PKP\controllers\grid\settings\roles;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\userGroup\UserGroup;

class UserGroupGridRow extends GridRow
{
    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $userGroup = $this->getData(); /** @var UserGroup $userGroup */
        assert($userGroup != null);

        $rowId = $this->getId();

        $actionArgs = ['userGroupId' => $userGroup->getId()];
        $this->setRequestArgs($actionArgs);

        // Only add row actions if this is an existing row.
        if (!empty($rowId) && is_numeric($rowId)) {
            $router = $request->getRouter();

            $this->addAction(new LinkAction(
                'editUserGroup',
                new AjaxModal(
                    $router->url($request, null, null, 'editUserGroup', null, $actionArgs),
                    __('grid.action.edit'),
                    'side-modal'
                ),
                __('grid.action.edit'),
                'edit'
            ));

            $this->addAction(new LinkAction(
                'removeUserGroup',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('settings.roles.removeText'),
                    null,
                    $router->url($request, null, null, 'removeUserGroup', null, $actionArgs),
                    'negative'
                ),
                __('grid.action.remove'),
                'delete'
            ));
        }
    }
}
