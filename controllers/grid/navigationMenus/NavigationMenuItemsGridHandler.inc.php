<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenusGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusGridHandler
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Handle navigationMenus grid requests.
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
			array(
				'fetchGrid', 'fetchRow',
				'addNavigationMenuItem', 'editNavigationMenuItem',
				'updateNavigationMenuItem',
				'deleteNavigationMenuItem'
			)
		);
	}


	//
	// Overridden template methods
	//
	/**
	 * @copydoc GridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		$context = $request->getContext();

		$navigationMenuItemId = $request->getUserVar('navigationMenuItemId');
		if ($navigationMenuItemId) {
			$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
			$navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId);
			if (!$navigationMenuItem ||  $navigationMenuItem->getContextId() != $context->getId()) {
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

		// Set the no items row text
		$this->setEmptyRowText('navigationMenuItems.noneExist');

		$context = $request->getContext();

		// Columns
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenuItemsGridCellProvider');
		$navigationMenuItemsCellProvider = new NavigationMenuItemsGridCellProvider();
		$this->addColumn(
			new GridColumn('title',
				'common.title',
				null,
				null,
				$navigationMenuItemsCellProvider,
				array('width' => 60)
			)
		);

		$this->addColumn(
			new GridColumn('type',
				'common.type',
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
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		return $navigationMenuItemDao->getByContextId($context->getId());
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
	 * Load and fetch the navigation menu items form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$navigationMenuId = (int)$request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, true);

		$navigationMenuItemForm->readInputData();

		if ($navigationMenuItemForm->validate()) {
			$navigationMenuItemForm->execute($request);

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
		$context = $request->getContext();
		$contextId = $context->getId();

		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId);
		$navigationMenuItemForm->initData($args, $request);

		return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
	}

	/**
	 * Load and fetch the navigation menu item form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function addNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, true);

		$navigationMenuItemForm->initData($args, $request);

		return new JSONMessage(true, $navigationMenuItemForm->fetch($request));
	}
}

?>
