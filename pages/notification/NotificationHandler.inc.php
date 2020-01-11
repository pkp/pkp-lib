<?php

/**
 * @file pages/notification/NotificationHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing notifications.
 */

import('classes.handler.Handler');
import('classes.notification.Notification');

class NotificationHandler extends Handler {

	/**
	 * Return formatted notification data using Json.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function fetchNotification($args, $request) {
		$this->setupTemplate($request);
		$user = $request->getUser();
		$userId = $user?$user->getId():null;
		$context = $request->getContext();
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notifications = array();

		// Get the notification options from request.
		$notificationOptions = $request->getUserVar('requestOptions');

		if (!$user) {
			$notifications = array();
		} elseif (is_array($notificationOptions)) {
			// Retrieve the notifications.
			$notifications = $this->_getNotificationsByOptions($notificationOptions, $context->getId(), $userId);
		} else {
			// No options, get only TRIVIAL notifications.
			$notifications = $notificationDao->getByUserId($userId, NOTIFICATION_LEVEL_TRIVIAL);
			$notifications = $notifications->toArray();
		}

		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage();

		if (is_array($notifications) && !empty($notifications)) {
			$formattedNotificationsData = array();
			$notificationManager = new NotificationManager();

			// Format in place notifications.
			$formattedNotificationsData['inPlace'] = $notificationManager->formatToInPlaceNotification($request, $notifications);

			// Format general notifications.
			$formattedNotificationsData['general'] = $notificationManager->formatToGeneralNotification($request, $notifications);

			// Delete trivial notifications from database.
			$notificationManager->deleteTrivialNotifications($notifications);

			$json->setContent($formattedNotificationsData);
		}

		return $json;
	}

	/**
	 * Get the notifications using options.
	 * @param $notificationOptions Array
	 * @param $contextId int
	 * @param $userId int
	 * @return Array
	 */
	function _getNotificationsByOptions($notificationOptions, $contextId, $userId = null) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationsArray = array();
		$notificationMgr = new NotificationManager();

		foreach ($notificationOptions as $level => $levelOptions) {
			if ($levelOptions) {
				foreach ($levelOptions as $type => $typeOptions) {
					if ($typeOptions) {
						$notificationMgr->isVisibleToAllUsers($type, $typeOptions['assocType'], $typeOptions['assocId']) ? $workingUserId = null : $workingUserId = $userId;
						$notificationsResultFactory = $notificationDao->getByAssoc($typeOptions['assocType'], $typeOptions['assocId'], $workingUserId, $type, $contextId);
						$notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
					} else {
						if ($userId) {
							$notificationsResultFactory = $notificationDao->getByUserId($userId, $level, $type, $contextId);
							$notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
						}
					}
				}
			} else {
				if ($userId) {
					$notificationsResultFactory = $notificationDao->getByUserId($userId, $level, null, $contextId);
					$notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
				}
			}
			$notificationsResultFactory = null;
		}

		return $notificationsArray;
	}

	/**
	 * Add notifications from a result factory to an array of
	 * existing notifications.
	 * @param $resultFactory DAOResultFactory
	 * @param $notificationArray Array
	 */
	function _addNotificationsToArray($resultFactory, $notificationArray) {
		if (!$resultFactory->wasEmpty()) {
			$notificationArray = array_merge($notificationArray, $resultFactory->toArray());
		}

		return $notificationArray;
	}

	/**
	 * Override setupTemplate() so we can load other locale components.
	 * @copydoc PKPHandler::setupTemplate()
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_SUBMISSION);
		parent::setupTemplate($request);
	}
}


