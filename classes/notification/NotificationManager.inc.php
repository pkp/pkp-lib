<?php

/**
 * @file classes/notification/NotificationManager.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */

// $Id$

import('notification.Notification');

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
		AppLocale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		foreach ($mailList as $email) {
			if ($notification->getIsLocalized()) {
				$params = array('param' => $notification->getParam());
				$notificationContents = __($notification->getContents(), $params);
			} else {
				$notificationContents = $notification->getContents();
			}

			import('mail.MailTemplate');
			$context =& Request::getContext();
			$site =& Request::getSite();

			$mail = new MailTemplate('NOTIFICATION_MAILLIST', null, null, null, null, true, true);
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
}

?>
