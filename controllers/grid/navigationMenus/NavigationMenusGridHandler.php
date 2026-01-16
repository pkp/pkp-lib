<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenusGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusGridHandler
 *
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Handle NavigationMenus grid requests.
 */

namespace PKP\controllers\grid\navigationMenus;

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\VueModal;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\notification\Notification;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class NavigationMenusGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            Role::ROLE_ID_MANAGER,
            $ops = [
                'fetchGrid',
                'fetchRow',
                'deleteNavigationMenu',
            ]
        );
        $this->addRoleAssignment(Role::ROLE_ID_SITE_ADMIN, $ops);
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::SITE_CONTEXT_ID;

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);
        $this->addPolicy(new CanAccessSettingsPolicy());

        $navigationMenuId = $request->getUserVar('navigationMenuId');
        if ($navigationMenuId) {
            // Ensure NavigationMenus is valid and for this context
            $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */
            $navigationMenu = $navigationMenuDao->getById($navigationMenuId);
            if (!$navigationMenu || $navigationMenu->getContextId() != $contextId) {
                return false;
            }
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration
        $this->setTitle('manager.navigationMenus');

        // Set the no items row text
        $this->setEmptyRowText('grid.navigationMenus.navigationMenu.noneExist');

        // Columns
        $navigationMenuCellProvider = new NavigationMenusGridCellProvider();

        $this->addColumn(
            new GridColumn(
                'title',
                'common.title',
                null,
                null,
                $navigationMenuCellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'nmis',
                'manager.navigationMenuItems',
                null,
                null,
                $navigationMenuCellProvider
            )
        );

        // Add grid action.
        $context = $request->getContext();
        $apiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context ? $context->getPath() : 'index',
            'navigationMenus'
        );

        $this->addAction(
            new LinkAction(
                'addNavigationMenu',
                new VueModal(
                    'NavigationMenuFormModal',
                    [
                        'navigationMenu' => null,
                        'apiUrl' => $apiUrl,
                    ]
                ),
                __('grid.action.addNavigationMenu'),
                'add_item'
            )
        );
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $context = $request->getContext();

        $contextId = \PKP\core\PKPApplication::SITE_CONTEXT_ID;
        if ($context) {
            $contextId = $context->getId();
        }

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */
        return $navigationMenuDao->getByContextId($contextId);
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new NavigationMenusGridRow();
    }

    //
    // Public grid actions.
    //
    /**
     * Delete a NavigationMenu.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteNavigationMenu($args, $request)
    {
        $navigationMenuId = (int) $request->getUserVar('navigationMenuId');
        $context = $request->getContext();

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenu = $navigationMenuDao->getById($navigationMenuId, $context ? $context->getId() : \PKP\core\PKPApplication::SITE_CONTEXT_ID);
        if ($navigationMenu && $request->checkCSRF()) {
            $navigationMenuDao->deleteObject($navigationMenu);

            // Create notification.
            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.removedNavigationMenu')]);

            return \PKP\db\DAO::getDataChangedEvent($navigationMenuId);
        }

        return new JSONMessage(false);
    }
}
