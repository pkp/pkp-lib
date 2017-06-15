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
	/** @var int the ID of the parent navigationMenuId */
	var $navigationMenuIdParent;

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
				'deleteNavigationMenuItem', 'saveSequence'
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
		import('lib.pkp.classes.security.authorization.internal.NavigationMenuRequiredPolicy');
		$this->addPolicy(new NavigationMenuRequiredPolicy($request, $args, 'navigationMenuIdParent'));
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
	 * @copydoc GridHandler::addFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		return array(new OrderGridItemsFeature());
	}



	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Set the no items row text
		$this->setEmptyRowText('grid.navigationMenus.navigationMenuItems.noneExist');

		$context = $request->getContext();

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

		$this->addColumn(
			new GridColumn('enabled',
				'common.enabled',
				null,
				null,
				$navigationMenuItemsCellProvider
			)
		);

		$this->addColumn(
			new GridColumn('path',
				'grid.navigationMenu.navigationMenuItemPath',
				null,
				null,
				$navigationMenuItemsCellProvider
			)
		);

		$this->addColumn(
			new GridColumn('parentNavigationMenuItem',
				'grid.navigationMenu.navigationMenuItemParent',
				null,
				null,
				$navigationMenuItemsCellProvider
			)
		);

		$this->addColumn(
			new GridColumn('default',
				'common.default',
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

		$navigationMenu = $this->getAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU);
		$actionArgs = array(
			'navigationMenuIdParent' => $navigationMenu->getId()
		);

		$this->addAction(
			new LinkAction(
				'addNavigationMenuItem',
				new AjaxModal(
					$router->url($request, null, null, 'addNavigationMenuItem', null, $actionArgs),
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
		$navigationMenu = $this->getAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU);

		//$context = $request->getContext();
		//$contextId = $context->getId();

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		return $navigationMenuItemDao->getByNavigationMenuId($navigationMenu->getId());
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	protected function getRowInstance() {
		$navigationMenu = $this->getAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU);

		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenuItemsGridRow');
		return new NavigationMenuItemsGridRow($navigationMenu->getId());

	}

	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$navigationMenu = $this->getAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU);
		return array_merge(
			parent::getRequestArgs(),
			array('navigationMenuIdParent' => $navigationMenu->getId())
		);
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($gridDataElement) {
		return $gridDataElement->getSequence();
	}

	/**
	 * @copydoc GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($rowId);
		$navigationMenuItem->setSequence($newSequence);
		$navigationMenuItemDao->updateObject($navigationMenuItem);
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
		$navigationMenuIdParent = (int)$request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);

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
		$navigationMenuIdParent = (int) $request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = $context->getId();

		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);
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
		$navigationMenuIdParent = (int)$request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$navigationMenuItemForm = new NavigationMenuItemsForm($contextId, $navigationMenuItemId, $navigationMenuIdParent);

		$navigationMenuItemForm->initData($args, $request);

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
		//$navigationMenuIdParent = (int) $request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId, $context->getId());
		if ($navigationMenuItem && $request->checkCSRF()) {
			$navigationMenuItemDao->deleteObject($navigationMenuItem);

			// Create notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedNavigationMenuItem')));

			return DAO::getDataChangedEvent($navigationMenuItemId);
		}

		return new JSONMessage(false);
	}

	/**
	 * Load and fetch the navigation menu item form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function getNavigationMenuItemsWithNoAssocId($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$navigationMenuIdParent = (int)$request->getUserVar('navigationMenuIdParent');
		$parentNavigationMenuItemId = (int)$request->getUserVar('parentNavigationMenuItemId');

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItems = $navigationMenuItemDao->getPossibleParrentNMIByNavigationMenuId($navigationMenuIdParent, $navigationMenuItemId);

		$templateMgr = TemplateManager::getManager($request);

		$navigationMenuOptions = array();
		if (!$navigationMenuItems->wasEmpty()) {
			$navigationMenuOptions = array(0 => __('common.none'));
		}
		while ($navigationMenuItem = $navigationMenuItems->next()) {
			$navigationMenuOptions[$navigationMenuItem->getId()] = $navigationMenuItem->getLocalizedTitle();
		}
		$templateMgr->assign('navigationMenuItems', $navigationMenuOptions);
		$templateMgr->assign('parentNavigationMenuItemId', $parentNavigationMenuItemId);
		$json = new JSONMessage(true, $templateMgr->fetch('controllers/grid/navigationMenus/navigationMenuItemsList.tpl'));
		return $json;
	}
}

?>
