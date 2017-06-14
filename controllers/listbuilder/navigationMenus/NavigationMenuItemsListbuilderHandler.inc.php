<?php
/**
 * @file controllers/listbuilder/navigationMenus/NavigationMenuItemsListbuilderHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsListbuilderHandler
 * @ingroup controllers_listbuilder_navigationMenus
 *
 * @brief Class for NavigationMenuItems administration.
 */

import('lib.pkp.classes.controllers.listbuilder.MultipleListsListbuilderHandler');
import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');

class NavigationMenuItemsListbuilderHandler extends MultipleListsListbuilderHandler {
	/** @var int the ID of the navigationMenuId */
	var $navigationMenuId;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
			array('fetch', 'addNavigationMenuItem', 'editNavigationMenuItem', 'deleteNavigationMenuItem', 'getDataChangedEvent', 'fetchGrid', 'fetchRow', 'updateNavigationMenuItem')
		);
	}

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
	 * @copydoc ListbuilderHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);

		// Basic configuration
		$this->setTitle('manager.setup.layout.blockManagement');
		$this->setSaveFieldName('blocks');

		// Name column
		$nameColumn = new ListbuilderGridColumn($this, 'name', 'common.name');

		// Add lists.
		$this->addList(new ListbuilderList('sidebarContext', 'manager.setup.layout.sidebar'));
		$this->addList(new ListbuilderList('unselected', 'manager.setup.layout.unselected'));

		import('lib.pkp.controllers.listbuilder.navigationMenus.NavigationMenuItemsListbuilderGridCellProvider');
		$nameColumn->setCellProvider(new NavigationMenuItemsListbuilderGridCellProvider());
		$this->addColumn($nameColumn);

		// Add grid action.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addNavigationMenu',
				new AjaxModal(
					$router->url($request, null, null, 'addNavigationMenuItem', null, null),
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
	 * @copydoc GridHandler::getRowInstance()
	 */
	protected function getRowInstance() {
		import('lib.pkp.controllers.grid.navigationMenus.NavigationMenusGridRow');
		return new NavigationMenusGridRow();
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
	 * Delete a navigation Menu item.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int) $request->getUserVar('navigationMenuItemId');
		$navigationMenuId = (int) $request->getUserVar('navigationMenuId');
		$context = $request->getContext();

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($navigationMenuItemId, $context->getId());
		if ($navigationMenuItem && $request->checkCSRF()) {
			$navigationMenuItemDao->deleteObject($navigationMenuItem);

			// Create notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedNavigationMenuItem')));

			return DAO::getDataChangedEvent($navigationMenuItemId, $navigationMenuId);
		}

		return new JSONMessage(false);
	}

	/**
	 * Load and fetch the navigation menu items form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenuItem($args, $request) {
		$navigationMenuItemId = (int)$request->getUserVar('navigationMenuItemId');
		$this->navigationMenuId = (int)$request->getUserVar('navigationMenuId');
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
			return DAO::getDataChangedEvent($navigationMenuItemId, $this->navigationMenuId);
		} else {
			return new JSONMessage(false);
		}
	}

	///**
	// * @copydoc GridHandler::getFilterSelectionData()
	// */
	//function getFilterSelectionData($request) {
	//    $statusId = (string) $request->getUserVar('statusId');
	//    return array(
	//        'statusId' => $statusId,
	//    );
	//}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc MultipleListsListbuilderHandler::setListsData()
	 */
	function setListsData($request, $filter) {
		$context = $request->getContext();

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$lists = $this->getLists();

		if ($request->getUserVar('rowId')){
			$navigationMenuItemId = $request->getUserVar('rowId')[0];
			$this->navigationMenuId = $request->getUserVar('rowId')['parentElementId'];
		} else {
			$this->navigationMenuId = $request->getUserVar('navigationMenuId');
		}

		$navigationMenuItems = $navigationMenuItemDao->getByNavigationMenuId($this->navigationMenuId);
		$navigationMenuItemsArray = $navigationMenuItems->toAssociativeArray();
		$lists['sidebarContext']->setData($navigationMenuItemsArray);
		$navigationMenuItemsNoParent = $navigationMenuItemDao->getWithoutParentByContextId($context->getId());
		$navigationMenuItemsNoParentArray = $navigationMenuItemsNoParent->toAssociativeArray();
		$lists['unselected']->setData($navigationMenuItemsNoParentArray);
	}
}

?>
