<?php

/**
 * @file classes/controllers/grid/notifications/PKPNotificationsGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationsGridHandler
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of notifications for a given user
 */

// Import UI base classes.
import('lib.pkp.classes.controllers.grid.GridHandler');

class PKPNotificationsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function PKPNotificationsGridHandler() {
		parent::GridHandler();
	}


	//
	// Getters and Setters
	//

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);

		$cellProvider = $this->_getCellProvider();
		$this->addColumn(
			new GridColumn(
				'task',
				'common.tasks',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('html' => true,
						'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'title',
				'submission.title',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);

		// Set the no items row text
		$this->setEmptyRowText('grid.noItems');
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::loadData()
	 * @return array Grid data.
	 */
	protected function loadData($request, $filter) {
		$user = $request->getUser();

		// Get all level task notifications.
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notifications = $notificationDao->getByUserId($user->getId(), NOTIFICATION_LEVEL_TASK);
		$rowData = $notifications->toAssociativeArray();

		// Remove not listable task types.
		$notListableTaskTypes = $this->_getNotListableTaskTypes();
		foreach ($rowData as $key => $notification) {
			if (in_array($notification->getType(), $notListableTaskTypes)) {
				unset($rowData[$key]);
			}
		}

		return $rowData;
	}


	//
	// Private helper methods.
	//
	/**
	 * Get the notification types that we don't want
	 * to list in this grid.
	 * @return array
	 */
	function _getNotListableTaskTypes() {
		return array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF);
	}

	//
	// Abstract methods
	//
	/**
	 * Get the implementation of the cell provider for this grid.
	 */
	function _getCellProvider() {
		assert(false);
		// return new NotificationsGridCellProvider();
	}
}

?>
