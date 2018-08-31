<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridHandler
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Handle NavigationMenuItems grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');
import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');

class NavigationMenuItemsGridHandler extends GridHandler {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			$ops = array(
				'fetchGrid', 'fetchRow',
				'addNavigationMenuItem', 'editNavigationMenuItem',
				'updateNavigationMenuItem',
				'deleteNavigationMenuItem', 'saveSequence',
			)
		);
		$this->addRoleAssignment(ROLE_ID_SITE_ADMIN, $ops);
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc GridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$context = $request->getContext();
		$contextId = $context?$context->getId():CONTEXT_ID_NONE;

		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		$navigationMenuItemId = $request->getUserVar('navigationMenuItemId');
		if ($navigationMenuItemId) {
			$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
			$navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId);
			if (!$navigationMenuItem ||  $navigationMenuItem->getContextId() != $contextId) {
				return false;
			}
		}
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Basic grid configuration
		$this->setTitle('manager.navigationMenuItems');

		// Set the no items row text
		$this->setEmptyRowText('grid.navigationMenus.navigationMenuItems.noneExist');

		// Columns
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenuItemsGridCellProvider');
		$navigationMenuItemsCellProvider = new NavigationMenuItemsGridCellProvider();
		$this->addColumn(
			new GridColumn('title',
				'common.title',
				null,
				null,
				$navigationMenuItemsCellProvider
			)
		);

		// Load language components
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);

		// Add grid action.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$this->addAction(
			new LinkAction(
				'addNavigationMenuItem',
				new AjaxModal(
					$router->url($request, null, null, 'addNavigationMenuItem', null, null),
					__('grid.action.addNavigationMenuItem'),
					'modal_add_item',
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
	protected function loadData($request, $filter) {
		$context = $request->getContext();

		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		return $navigationMenuItemDao->getByContextId($contextId);
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	protected function getRowInstance() {
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenuItemsGridRow');
		return new NavigationMenuItemsGridRow();

	}

	//
	// Public grid actions.
	//
	/**
	 * Update NavigationMenuItem
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$navigationMenuId = (int)$request->getUserVar('navigationMenuId');
		$navigationMenuIdParent = (int)$request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);

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
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __($notificationLocaleKey)));

			// Prepare the grid row data.
			return DAO::getDataChangedEvent($navigationMenuItemId);
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Display form to edit a navigation menu item object.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int) $request->getUserVar('navigationMenuItemId');
		$navigationMenuIdParent = (int) $request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);
		$navigationMenuItemForm->initData();

		return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
	}

	/**
	 * Add NavigationMenuItem
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function addNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$navigationMenuIdParent = (int)$request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);

		$navigationMenuItemForm->initData();

		return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
	}

	/**
	 * Delete a navigation Menu item.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int) $request->getUserVar('navigationMenuItemId');

		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId, $contextId);
		if ($navigationMenuItem) {
			$navigationMenuItemDao->deleteObject($navigationMenuItem);

			// Create notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedNavigationMenuItem')));

			return DAO::getDataChangedEvent($navigationMenuItemId);
		}

		return new JSONMessage(false);
	}
}


