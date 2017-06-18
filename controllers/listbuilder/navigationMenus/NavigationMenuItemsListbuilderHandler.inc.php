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
import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsManagementForm');

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
			array('fetch', 'updateNavigationMenuItems')
		);
	}

	/**
	 * @copydoc GridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		//import('lib.pkp.classes.security.authorization.internal.NavigationMenuRequiredPolicy');
		//$this->addPolicy(new NavigationMenuRequiredPolicy($request, $args, 'navigationMenuIdParent'));
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
		$this->setTitle('manager.navigationMenus.navigationMenuItemsManagement');
		$this->setSaveFieldName('navigation_menu_items');

		// Name column
		$nameColumn = new ListbuilderGridColumn($this, 'name', 'common.name');

		// Add lists.
		$this->addList(new ListbuilderList('selectedMenuItems', 'manager.navigationMenus.selectedMenuItems'));
		$this->addList(new ListbuilderList('unselectedMenuItems', 'manager.navigationMenus.unselectedMenuItems'));

		import('lib.pkp.controllers.listbuilder.navigationMenus.NavigationMenuItemsListbuilderGridCellProvider');
		$nameColumn->setCellProvider(new NavigationMenuItemsListbuilderGridCellProvider());
		$this->addColumn($nameColumn);

		// Clear this grid's actions.
		$this->_actions = array();
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc MultipleListsListbuilderHandler::setListsData()
	 */
	function setListsData($request, $filter) {
		$context = $request->getContext();
		//$navigationMenu = $this->getAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU);
		$navigationMenuId = (int) $request->getUserVar('navigationMenuIdParent');

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$lists = $this->getLists();

		$navigationMenuItems = $navigationMenuItemDao->getByNavigationMenuId($navigationMenuId);
		$navigationMenuItemsArray = $navigationMenuItems->toAssociativeArray();
		$lists['selectedMenuItems']->setData($navigationMenuItemsArray);

		$navigationMenuItemsNoParent = $navigationMenuItemDao->getByContextIdNotHavingThisNavigationMenuId($context->getId(), $navigationMenuId);
		$navigationMenuItemsNoParentArray = $navigationMenuItemsNoParent->toAssociativeArray();
		$lists['unselectedMenuItems']->setData($navigationMenuItemsNoParentArray);
	}

	/**
	 * Save an edited/inserted NavigationMenus.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenuItems($args, $request) {
		// Identify the NavigationMenu id.
		$navigationMenuId = $request->getUserVar('navigationMenuIdParent');
		$context = $request->getContext();
		$contextId = $context->getId();

		// Form handling.
		$navigationMenusForm = new NavigationMenuItemsManagementForm($contextId, $navigationMenuId);
		$navigationMenusForm->readInputData();

		if ($navigationMenusForm->validate()) {
			$navigationMenusForm->execute($request);

			// Successful edit of an existing NavigationMenu.
			$notificationLocaleKey = 'notification.editedNavigationMenu';

			// Record the notification to user.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __($notificationLocaleKey)));

			// Prepare the grid row data.
			//return DAO::getDataChangedEvent($navigationMenuId);
			return new JSONMessage(true);
		} else {
			return new JSONMessage(false);
		}
	}
}

?>
