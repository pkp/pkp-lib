<?php

/**
 * @file controllers/grid/announcements/AnnouncementTypeGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeGridHandler
 * @ingroup controllers_grid_announcements
 *
 * @brief Handle announcement type grid requests.
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
			array(
				'fetchGrid', 'fetchRow',
				'addNavigationMenu', 'editNavigationMenu',
				'updateNavigationMenu',
				'deleteNavigationMenu',
				'addMenuItems'
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

		$navigationMenuId = $request->getUserVar('navigationMenuId');
		if ($navigationMenuId) {
			// Ensure announcement type is valid and for this context
			$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /* @var $announcementTypeDao AnnouncementTypeDAO */
			$navigationMenu = $navigationMenuDao->getById($navigationMenuId);
			if (!$navigationMenu ||  $navigationMenu->getContextId() != $context->getId()) {
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
		$this->setEmptyRowText('manager.navigationMenus.noneCreated');

		$context = $request->getContext();

		// Columns
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenusGridCellProvider');
		$navigationMenuCellProvider = new NavigationMenusGridCellProvider();
		$this->addColumn(
		    new GridColumn('title',
		        'common.title',
		        null,
		        null,
		        $navigationMenuCellProvider,
		        array('width' => 60)
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
		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
		return $navigationMenuDao->getByContextId($context->getId());
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
	 * Display form to add announcement type.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function addNavigationMenu($args, $request) {
		return $this->editNavigationMenu($args, $request);
	}

	/**
	 * Display form to edit an announcement type.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editNavigationMenu($args, $request) {
		$navigationMenuId = (int)$request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = $context->getId();

		$announcementTypeForm = new NavigationMenuForm($contextId, $navigationMenuId);
		$announcementTypeForm->initData($args, $request);

		return new JSONMessage(true, $announcementTypeForm->fetch($request));
	}

	/**
	 * Save an edited/inserted announcement type.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenu($args, $request) {

		// Identify the announcement type id.
		$navigationMenuId = $request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = $context->getId();

		// Form handling.
		$announcementTypeForm = new NavigationMenuForm($contextId, $navigationMenuId);
		$announcementTypeForm->readInputData();

		if ($announcementTypeForm->validate()) {
			$announcementTypeForm->execute($request);

			if ($navigationMenuId) {
				// Successful edit of an existing announcement type.
				$notificationLocaleKey = 'notification.editedNavigationMenu';
			} else {
				// Successful added a new announcement type.
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
	 * Delete an announcement type.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteNavigationMenu($args, $request) {
		$navigationMenuId = (int) $request->getUserVar('navigationMenuId');
		$context = $request->getContext();

		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenu = $navigationMenuDao->getById($navigationMenuId, $context->getId());
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

	/**
	 * Display form to add navigation menu items.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addMenuItems($args, $request) {
		$navigationMenuId = (int)$request->getUserVar('navigationMenuId');
		$context = $request->getContext();
		$contextId = $context->getId();

		$navigationMenuItemsForm = new NavigationMenuItemsForm($contextId, $navigationMenuId);
		$navigationMenuItemsForm->initData($args, $request);

		return new JSONMessage(true, $navigationMenuItemsForm->fetch($request));
	}
}

?>
