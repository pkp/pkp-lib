<?php

/**
 * @file controllers/grid/plugins/PluginGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGridRow
 *
 * @ingroup controllers_grid_plugins
 *
 * @brief Plugin grid row definition
 */

namespace PKP\controllers\grid\plugins;

use PKP\controllers\grid\GridRow;
use PKP\core\PKPRouter;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\plugins\Plugin;
use PKP\security\Role;

class PluginGridRow extends GridRow
{
    /** @var array */
    public $_userRoles;

    /**
     * Constructor
     *
     * @param array $userRoles
     */
    public function __construct($userRoles)
    {
        parent::__construct();
        $this->_userRoles = $userRoles;
    }


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

        // Is this a new row or an existing row?
        $plugin = & $this->getData(); /** @var Plugin $plugin */
        assert(is_a($plugin, 'Plugin'));

        $rowId = $this->getId();

        // Only add row actions if this is an existing row
        if (!is_null($rowId)) {
            $router = $request->getRouter(); /** @var PKPRouter $router */

            $actionArgs = array_merge(
                ['plugin' => $plugin->getName()],
                $this->getRequestArgs()
            );

            if ($this->_canEdit($plugin)) {
                foreach ($plugin->getActions($request, $actionArgs) as $action) {
                    $this->addAction($action);
                }
            }

            // Administrative functions.
            if (in_array(Role::ROLE_ID_SITE_ADMIN, $this->_userRoles)) {
                $this->addAction(new LinkAction(
                    'delete',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('manager.plugins.deleteConfirm'),
                        __('common.delete'),
                        $router->url($request, null, null, 'deletePlugin', null, $actionArgs),
                        'modal_delete'
                    ),
                    __('common.delete'),
                    'delete'
                ));

                $this->addAction(new LinkAction(
                    'upgrade',
                    new AjaxModal(
                        $router->url($request, null, null, 'upgradePlugin', null, $actionArgs),
                        __('manager.plugins.upgrade'),
                        'modal_upgrade'
                    ),
                    __('grid.action.upgrade'),
                    'upgrade'
                ));
            }
        }
    }


    //
    // Protected helper methods
    //
    /**
     * Return if user can edit a plugin settings or not.
     *
     * @param Plugin $plugin
     *
     * @return bool
     */
    protected function _canEdit($plugin)
    {
        if ($plugin->isSitePlugin()) {
            if (in_array(Role::ROLE_ID_SITE_ADMIN, $this->_userRoles)) {
                return true;
            }
        } else {
            if (in_array(Role::ROLE_ID_MANAGER, $this->_userRoles)) {
                return true;
            }
        }
        return false;
    }
}
