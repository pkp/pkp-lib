<?php

/**
 * @file classes/notification/NotificationManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */


import('classes.notification.Notification');

class NotificationManager {
	/**
	 * Constructor.
	 */
	function NotificationManager() {
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $userId int
	 * @param $contents string
	 * @param $param string
	 * @param $location string
	 * @param $isLocalized bool
	 * @param $assocType int
	 * @param $assocId int
	 * @return Notification object
	 */
	function createNotification($userId, $contents, $param, $location, $isLocalized, $assocType, $level = NOTIFICATION_LEVEL_NORMAL) {
		$notification = new Notification();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$notification->setUserId($userId);
		$notification->setContents($contents);
		$notification->setParam($param);
		$notification->setLocation($location);
		$notification->setIsLocalized($isLocalized);
		$notification->setAssocType($assocType);
		$notification->setContext($contextId);
		$notification->setLevel($level);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);

		return $notification;
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $title string
	 * @param $contents string
	 * @param $param string
	 * @param $isLocalized boolean
	 * @return Notification object
	 */
	function createTrivialNotification($title, $contents, $assocType = NOTIFICATION_TYPE_SUCCESS, $param = null, $isLocalized = 1) {
		$notification = new Notification();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$user =& Request::getUser();
		$notification->setUserId($user->getId());
		$notification->setTitle($title);
		$notification->setContents($contents);
		$notification->setParam($param);
		$notification->setIsLocalized($isLocalized);
		$notification->setContext($contextId);
		$notification->setAssocType($assocType);
		$notification->setLevel(NOTIFICATION_LEVEL_TRIVIAL);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);

		return $notification;
	}

	/**
	 * Send an update to all users on the mailing list
	 * @param $notification object Notification
	 */
	function sendToMailingList($notification) {
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$mailList = $notificationSettingsDao->getMailList();
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		foreach ($mailList as $email) {
			if ($notification->getIsLocalized()) {
				$params = array('param' => $notification->getParam());
				$notificationContents = Locale::translate($notification->getContents(), $params);
			} else {
				$notificationContents = $notification->getContents();
			}

			import('classes.mail.MailTemplate');
			$context =& Request::getContext();
			$site =& Request::getSite();

			$mail = new MailTemplate('NOTIFICATION_MAILLIST');
			$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
			$mail->assignParams(array(
				'notificationContents' => $notificationContents,
				'url' => $notification->getLocation(),
				'siteTitle' => $context->getLocalizedTitle(),
				'unsubscribeLink' => Request::url(null, 'notification', 'unsubscribeMailList')
			));
			$mail->addRecipient($email);
			$mail->send();
		}
	}

	/**
	 * Get current notifications.
	 * @param User $user The user that will be used to get
	 * notifications.
	 * @param int $level The notification level.
	 * @return array
	 */
	function getNotifications($user, $level) {
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notifications =& $notificationDao->getNotificationsByUserId($user->getId(), $level);
		$notificationsArray =& $notifications->toArray();
		unset($notifications);

		return $notificationsArray;
	}

	/**
	 * Deletes notifications from database.
	 * @param array $notifications
	 */
	function deleteNotifications($notifications) {
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		foreach($notifications as $notification) {
			// Don't delete normal level notifications.
			if($notification->getLevel() !== NOTIFICATION_LEVEL_NORMAL) {
				$notificationDao->deleteNotificationById($notification->getId());
			}
		}
	}

	/**
	 * General notification data formating.
	 * @param array $notifications
	 * @return array
	 */
	function formatToGeneralNotification($notifications) {
		$formattedNotificationsData = array();
		foreach ($notifications as $notification) {
			$title = $notification->getTitle();
			$contents = $notification->getContents();
			if ($notification->getIsLocalized()) {
				$title = Locale::translate($title);
				$contents = Locale::translate($contents, $notification->getParam());
			}
			$formattedNotificationsData[] = array(
				'pnotify_title' => (!is_null($title)) ? $title : $defaultTitle,
				'pnotify_text' => $contents,
				'pnotify_addClass' => $notification->getStyleClass(),
				'pnotify_notice_icon' => 'notifyIcon' . $notification->getIconClass()
			);
		}

		return $formattedNotificationsData;
	}

	/**
	 * In place notification data formating.
	 * @param $notifications array
	 * @return array
	 */
	function formatToInPlaceNotification($notifications) {
		$formattedNotificationsData = null;

		if (!empty($notifications)) {
			$templateMgr =& TemplateManager::getManager();
			$templateMgr->assign('notifications', $notifications);
			$formattedNotificationsData = $templateMgr->fetch('controllers/notification/inPlaceNotificationContent.tpl');
		}

		return $formattedNotificationsData;
	}
}

?>
