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
import('lib.pkp.controllers.grid.notifications.NotificationsGridCellProvider');

class NotificationsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function NotificationsGridHandler() {
		parent::GridHandler();
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

		// PROTOTYPING: Use a NullAction for markNew/markRead actions.
		// This is (currently) not hooked up to anything.
		import('lib.pkp.classes.linkAction.request.NullAction');
		$this->addAction(
			new LinkAction(
				'markNew',
				new NullAction(),
				__('grid.action.markNew'),
				'edit' // FIXME: Icon
			),
			GRID_ACTION_POSITION_BELOW
		);
		$this->addAction(
			new LinkAction(
				'markRead',
				new NullAction(),
				__('grid.action.markRead'),
				'edit' // FIXME: Icon
			),
			GRID_ACTION_POSITION_BELOW
		);

		// PROTOTYPING: Use a RemoteActionConfirmationModal for delete
		// action. This does not yet properly map onto the existing
		// deleteNotification function here, which handles deleting a
		// single notification. It does not submit selected notification
		// IDs e.g. as a form submission.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		$this->addAction(
			new LinkAction(
				'deleteNotification',
				new RemoteActionConfirmationModal(
					__('common.confirmDelete'),
					__('grid.action.delete'),
					$router->url($request, null, null, 'deleteNotification', null, array()), 'modal_delete'
				),
				__('grid.action.delete'),
				'delete'
			),
			GRID_ACTION_POSITION_BELOW
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.selectableItems.SelectableItemsFeature');
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new SelectableItemsFeature(), new PagingFeature());
	}

	/**
	 * @copydoc GridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedNotifications';
	}

	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		return false; // Nothing is selected by default
	}

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
}

?>
