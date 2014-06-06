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

		$router = $request->getRouter();
		$this->addAction(
			new LinkAction(
				'deleteNotifications',
				new NullAction(),
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
	 * @see GridHandler::getJSHandler()
	 */
	public function getJSHandler() {
		return '$.pkp.controllers.grid.notifications.NotificationsGridHandler';
	}

	/**
	 * @see GridHandler::setUrls()
	 */
	function setUrls($request) {
		$router = $request->getRouter();
		parent::setUrls($request, array(
			'markNewUrl' => $router->url($request, null, null, 'markNew', null, $this->getRequestArgs()),
			'markReadUrl' => $router->url($request, null, null, 'markRead', null, $this->getRequestArgs()),
			'deleteUrl' => $router->url($request, null, null, 'deleteNotifications', null, $this->getRequestArgs()),
		));
	}

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
		$notListableTaskTypes = $this->getNotListableTaskTypes();
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
	 * Mark notifications unread
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function markNew($args, $request) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$user = $request->getUser();

		foreach ((array) $request->getUserVar('selectedElements') as $notificationId) {
			if ($notification = $notificationDao->getById($notificationId, $user->getId())) {
				$notificationDao->setDateRead($notificationId, null);
			}
		}
		return DAO::getDataChangedEvent();
	}

	/**
	 * Mark notifications unread
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function markRead($args, $request) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$user = $request->getUser();

		foreach ((array) $request->getUserVar('selectedElements') as $notificationId) {
			if ($notification = $notificationDao->getById($notificationId, $user->getId())) {
				$notificationDao->setDateRead($notificationId, Core::getCurrentDate());
			}
		}
		return DAO::getDataChangedEvent();
	}

	/**
	 * Delete notifications
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function deleteNotifications($args, $request) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$user = $request->getUser();

		foreach ((array) $request->getUserVar('selectedElements') as $notificationId) {
			if ($notification = $notificationDao->getById($notificationId, $user->getId())) {
				$notificationDao->deleteObject($notification);
			}
		}
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
	static function getNotListableTaskTypes() {
		return array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF);
	}
}

?>
