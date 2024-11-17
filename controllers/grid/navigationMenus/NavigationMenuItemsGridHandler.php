<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridHandler
 *
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Handle NavigationMenuItems grid requests.
 */

namespace PKP\controllers\grid\navigationMenus;

use APP\controllers\grid\navigationMenus\form\NavigationMenuItemsForm;
use APP\core\Request;
use APP\notification\NotificationManager;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\navigationMenu\NavigationMenuItemDAO;
use PKP\notification\Notification;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class NavigationMenuItemsGridHandler extends GridHandler
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
                'fetchGrid', 'fetchRow',
                'addNavigationMenuItem', 'editNavigationMenuItem',
                'updateNavigationMenuItem',
                'deleteNavigationMenuItem', 'saveSequence',
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

        $navigationMenuItemId = $request->getUserVar('navigationMenuItemId');
        if ($navigationMenuItemId) {
            $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
            $navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId);
            if (!$navigationMenuItem || $navigationMenuItem->getContextId() != $contextId) {
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
        $this->setTitle('manager.navigationMenuItems');

        // Set the no items row text
        $this->setEmptyRowText('grid.navigationMenus.navigationMenuItems.noneExist');

        // Columns
        $navigationMenuItemsCellProvider = new NavigationMenuItemsGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'title',
                'common.title',
                null,
                null,
                $navigationMenuItemsCellProvider
            )
        );

        // Add grid action.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'addNavigationMenuItem',
                new AjaxModal(
                    $router->url($request, null, null, 'addNavigationMenuItem', null, null),
                    __('grid.action.addNavigationMenuItem'),
                    null,
                    true
                ),
                __('grid.action.addNavigationMenuItem'),
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

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        return $navigationMenuItemDao->getByContextId($contextId);
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new NavigationMenuItemsGridRow();
    }

    //
    // Public grid actions.
    //
    /**
     * Update NavigationMenuItem
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function updateNavigationMenuItem($args, $request)
    {
        $navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::SITE_CONTEXT_ID;
        if ($context) {
            $contextId = $context->getId();
        }

        $navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId);

        $navigationMenuItemForm->readInputData();

        if ($navigationMenuItemForm->validate()) {
            $navigationMenuItemForm->execute();

            if ($navigationMenuItemId) {
                // Successful edit of an existing $navigationMenuItem.
                $notificationLocaleKey = 'notification.editedNavigationMenuItem';
            } else {
                // Successful added a new $navigationMenuItemForm.
                $notificationLocaleKey = 'notification.addedNavigationMenuItem';
            }

            // Record the notification to user.
            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __($notificationLocaleKey)]);

            // Prepare the grid row data.
            return \PKP\db\DAO::getDataChangedEvent($navigationMenuItemId);
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Display form to edit a navigation menu item object.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editNavigationMenuItem($args, $request)
    {
        $navigationMenuItemId = (int) $request->getUserVar('navigationMenuItemId');
        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::SITE_CONTEXT_ID;
        if ($context) {
            $contextId = $context->getId();
        }

        $navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId);
        $navigationMenuItemForm->initData();

        return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
    }

    /**
     * Add NavigationMenuItem
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function addNavigationMenuItem($args, $request)
    {
        $navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::SITE_CONTEXT_ID;
        if ($context) {
            $contextId = $context->getId();
        }

        $navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId);
        $navigationMenuItemForm->initData();

        return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
    }

    /**
     * Delete a navigation Menu item.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteNavigationMenuItem($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $navigationMenuItemId = (int) $request->getUserVar('navigationMenuItemId');
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId);
        if ($navigationMenuItem) {
            $navigationMenuItemDao->deleteObject($navigationMenuItem);

            // Create notification.
            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.removedNavigationMenuItem')]);

            return \PKP\db\DAO::getDataChangedEvent($navigationMenuItemId);
        }

        return new JSONMessage(false);
    }
}
