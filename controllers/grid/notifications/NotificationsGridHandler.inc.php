<?php

/**
 * @file controllers/grid/notifications/NotificationsGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationsGridHandler
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of notifications for a given user
 */

// Import UI base classes.
import('lib.pkp.classes.controllers.grid.GridHandler');

// Other classes associated with this grid
import('lib.pkp.controllers.grid.notifications.NotificationsGridRow');
import('lib.pkp.controllers.grid.notifications.NotificationsGridCellProvider');

class NotificationsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function NotificationsGridHandler() {
		parent::GridHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER),
			array('fetchGrid', 'deleteNotification')
		);
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
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);

		$cellProvider = new NotificationsGridCellProvider();
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
	// Public methods
	//
	/**
	 * Delete a notification
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function deleteNotification($args, $request) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$user = $request->getUser();
		$notification = $notificationDao->getById(
			$request->getUserVar('notificationId'),
			$user->getId()
		);
		$notificationDao->deleteObject($notification);
		return DAO::getDataChangedEvent();
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

	/**
	 * @copydoc GridHandler::getRowInstance
	 */
	protected function getRowInstance() {
		return new NotificationsGridRow();
	}
}

?>
