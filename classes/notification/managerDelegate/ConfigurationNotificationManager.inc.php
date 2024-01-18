<?php

/**
 * @file classes/notification/managerDelegate/ConfigurationNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfigurationNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Configuration notification types manager delegate.
 */

use PKP\notification\NotificationManagerDelegate;
use PKP\db\DAORegistry;

class ConfigurationNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function __construct($notificationType) {
		parent::__construct($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		$notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO');
		$notificationSettings = $notificationSettingsDao->getNotificationSettings($notification->getId());
		assert(!is_null($notificationSettings['contents']));
		switch ($notification->getType()) {
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_USER:
				return $notificationSettings['contents'];
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PLUGIN:
				return $notificationSettings['contents'];
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());
		switch ($notification->getType()) {
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_USER:
				return $dispatcher->url($request, 'management', 'settings', 'access');
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PLUGIN:
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'management', 'settings', 'website',null, '#plugins');
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationManager::getIconClass()
	 */
	public function getIconClass($notification) {
		switch ($notification->getType()) {
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_USER:
				return 'notifyIconPageAlert';
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PLUGIN:
				return 'notifyIconeEdit';
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		switch ($notification->getType()) {
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_USER:
				return NOTIFICATION_STYLE_CLASS_INFORMATION;
			case PKPNotification::NOTIFICATION_TYPE_CONFIGURE_PLUGIN:
				return NOTIFICATION_STYLE_CLASS_INFORMATION;
			default:
				assert(false);
		}
	}
}


