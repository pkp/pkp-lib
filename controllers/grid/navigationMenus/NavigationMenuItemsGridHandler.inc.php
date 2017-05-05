<?php

/**
 * @file controllers/grid/announcements/AnnouncementGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementGridHandler
 * @ingroup controllers_grid_announcements
 *
 * @brief Handle announcements grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');
import('lib.pkp.classes.controllers.grid.DateGridCellProvider');

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
	 * @param $requireAnnouncementsEnabled Iff true, allow access only if context settings enable announcements
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
		$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
		$rangeInfo = $this->getGridRangeInfo($request, $this->getId());
		return $announcementDao->getAnnouncementsNotExpiredByAssocId($context->getAssocType(), $context->getId(), $rangeInfo);
	}


	//
	// Public grid actions.
	//
	/**
	 * Load and fetch the announcement form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function updateNavigationMenuItem($args, $request) {
		$announcementId = (int)$request->getUserVar('announcementId');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$announcementForm = new NavigationMenuItemsForm($contextId, $announcementId, true);

		$announcementForm->initData($args, $request);

		return new JSONMessage(true, $announcementForm->fetch($request));
	}

	/**
	 * Load and fetch the announcement form in read-only mode.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function addNavigationMenuItem($args, $request) {
		$announcementId = (int)$request->getUserVar('announcementId');
		$context = $request->getContext();
		$contextId = $context->getId();

		import('lib.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsForm');
		$announcementForm = new NavigationMenuItemsForm($contextId, $announcementId, true);

		$announcementForm->initData($args, $request);

		return new JSONMessage(true, $announcementForm->fetch($request));
	}
}

?>
