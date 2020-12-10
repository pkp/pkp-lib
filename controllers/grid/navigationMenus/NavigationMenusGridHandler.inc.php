<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenusGridHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusGridHandler
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Handle NavigationMenus grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuForm');

class NavigationMenusGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			$ops = array(
				'fetchGrid', 'fetchRow',
				'addNavigationMenu', 'editNavigationMenu',
				'updateNavigationMenu',
				'deleteNavigationMenu'
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


		$navigationMenuId = $request->getUserVar('navigationMenuId');
		if ($navigationMenuId) {
			// Ensure NavigationMenus is valid and for this context
			$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /* @var $navigationMenuDao NavigationMenuDAO */
			$navigationMenu = $navigationMenuDao->getById($navigationMenuId);
			if (!$navigationMenu ||  $navigationMenu->getContextId() != $contextId) {
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
		$this->setTitle('manager.navigationMenus');

		// Set the no items row text
		$this->setEmptyRowText('grid.navigationMenus.navigationMenu.noneExist');

		// Columns
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenusGridCellProvider');
		$navigationMenuCellProvider = new NavigationMenusGridCellProvider();

		$this->addColumn(
			new GridColumn('title',
				'common.title',
				null,
				null,
				$navigationMenuCellProvider
			)
		);

		$this->addColumn(
			new GridColumn('nmis',
				'manager.navigationMenuItems',
				null,
				null,
				$navigationMenuCellProvider
			)
		);

		// Load language components
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);

		// Add grid action.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addNavigationMenu',
				new AjaxModal(
					$router->url($request, null, null, 'addNavigationMenu', null, null),
					__('grid.action.addNavigationMenu'),
					'modal_add_item',
					true
				),
				__('grid.action.addNavigationMenu'),
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

		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /* @var $navigationMenuDao NavigationMenuDAO */
		return $navigationMenuDao->getByContextId($contextId);
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	protected function getRowInstance() {
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenusGridRow');
		return new NavigationMenusGridRow();
	}

	//
	// Public grid actions.
	//
	/**
	 * Display form to add NavigationMenus.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function addNavigationMenu($args, $request) {
		return $this->editNavigationMenu($args, $request);
	}

	/**
	 * Display form to edit NavigationMenus.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editNavigationMenu($args, $request) {
		$navigationMenuId = (int)$request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		$navigationMenuForm = new NavigationMenuForm($contextId, $navigationMenuId);
		$navigationMenuForm->initData();

		return new JSONMessage(true, $navigationMenuForm->fetch($request));
	}

	/**
	 * Save an edited/inserted NavigationMenus.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenu($args, $request) {
		// Identify the NavigationMenu id.
		$navigationMenuId = $request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		// Form handling.
		$navigationMenusForm = new NavigationMenuForm($contextId, $navigationMenuId);
		$navigationMenusForm->readInputData();

		if ($navigationMenusForm->validate()) {
			$navigationMenusForm->execute();

			if ($navigationMenuId) {
				// Successful edit of an existing NavigationMenu.
				$notificationLocaleKey = 'notification.editedNavigationMenu';
			} else {
				// Successful added a new NavigationMenu.
				$notificationLocaleKey = 'notification.addedNavigationMenu';
			}

			// Record the notification to user.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __($notificationLocaleKey)));

			// Prepare the grid row data.
			return DAO::getDataChangedEvent($navigationMenuId);
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Delete a NavigationMenu.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteNavigationMenu($args, $request) {
		$navigationMenuId = (int) $request->getUserVar('navigationMenuId');
		$context = $request->getContext();

		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /* @var $navigationMenuDao NavigationMenuDAO */
		$navigationMenu = $navigationMenuDao->getById($navigationMenuId, $context?$context->getId():CONTEXT_SITE);
		if ($navigationMenu && $request->checkCSRF()) {
			$navigationMenuDao->deleteObject($navigationMenu);

			// Create notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedNavigationMenu')));

			return DAO::getDataChangedEvent($navigationMenuId);
		}

		return new JSONMessage(false);
	}


}


