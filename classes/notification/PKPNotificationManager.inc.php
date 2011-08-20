<?php

/**
 * @file classes/notification/PKPNotificationManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */


import('classes.notification.Notification');

class PKPNotificationManager {
	/**
	 * Constructor.
	 */
	function PKPNotificationManager() {
	}

	/**
	 * Construct a set of notifications and return them as a formatted string
	 * @param $request PKPRequest
	 * @param $userId int
	 * @param $level int optional
	 * @param $contextId int optional
	 * @param $rangeInfo object optional
	 * @param $notificationTemplate string optional Template to use for constructing an individual notification for display
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function getFormattedNotificationsForUser(&$request, $userId, $level = NOTIFICATION_LEVEL_NORMAL, $contextId = null, $rangeInfo = null, $notificationTemplate = 'notification/notification.tpl') {
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notifications = $notificationDao->getNotificationsByUserId($contextId, $userId, $level, $rangeInfo);

		return $this->formatNotifications($request, $notifications, $notificationTemplate);
	}

	/*
	 * Return a string of formatted notifications for display
	 * @param $request PKPRequest
	 * @param $notifications object DAOResultFactory
	 * @param $notificationTemplate string optional Template to use for constructing an individual notification for display
	 * @return string
	 */
	function formatNotifications(&$request, $notifications, $notificationTemplate = 'notification/notification.tpl') {
		$notificationString = '';

		// Build out the notifications based on their associated objects and format into a string
		while($notification =& $notifications->next()) {
			$notificationString .= $this->formatNotification($request, $notification, $notificationTemplate);
			unset($notification);
		}

		return $notificationString;
	}

	/**
	 * Return a fully formatted notification for display
	 * @param $request PKPRequest
	 * @param $notification object Notification
	 * @return string
	 */
	function formatNotification(&$request, $notification, $notificationTemplate = 'notification/notification.tpl') {
		$templateMgr =& TemplateManager::getManager();

		// Set the date read if it isn't already set
		if (!$notification->getDateRead()) {
			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$dateRead = $notificationDao->setDateRead($notification->getId());
			$notification->setDateRead($dateRead);
		}

		$templateMgr->assign('notificationDateCreated', $notification->getDateCreated());
		$templateMgr->assign('notificationId', $notification->getId());
		$templateMgr->assign('notificationContents',$this->getNotificationContents($request, $notification));
		$templateMgr->assign('notificationIconClass', $this->getIconClass($notification));
		$templateMgr->assign('notificationDateRead', $notification->getDateRead());
		if($notification->getLevel() != NOTIFICATION_LEVEL_TRIVIAL) {
			$templateMgr->assign('notificationUrl', $this->getNotificationUrl($request, $notification));
		}

		$user =& $request->getUser();
		$templateMgr->assign('isUserLoggedIn', $user);

		return $templateMgr->fetch($notificationTemplate);
	}

	/**
	 * Construct a URL for the notification based on its type and associated object
	 * @param $request PKPRequest
	 * @param $notification Notification
	 * @return string
	 */
	function getNotificationUrl(&$request, &$notification) {
		assert(isset($type));
	}

	/**
	 * Construct the contents for the notification based on its type and associated object
	 * @param $request PKPRequest
	 * @param $notification Notification
	 * @return string
	 */
	function getNotificationContents(&$request, &$notification) {
		$type = $notification->getType();
		assert(isset($type));

		switch ($type) {
			case NOTIFICATION_TYPE_SUCCESS:
				$contents = __('common.changesSaved');
				break;
			default:
				$contents = null;
		}

		return $contents;
	}

	/**
	 * get notification style class
	 * @param $notification Notification
	 * @return string
	 */
	function getStyleClass(&$notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifySuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyError';
			case NOTIFICATION_TYPE_INFORMATION: return 'notifyInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyHelp';
		}
	}

	/**
	 * get notification icon style class
	 * @param $notification Notification
	 * @return string
	 */
	function getIconClass(&$notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
			case NOTIFICATION_TYPE_INFORMATION: return 'notifyIconInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
			default: return 'notifyIconPageAlert';
		}
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $request PKPRequest
	 * @param $userId int
	 * @param $notificationType int
	 * @param $contextId int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $level int
	 * @param $contents string Override the notification's default contents
	 * @return Notification object
	 */
	function createNotification(&$request, $userId, $notificationType, $contextId = null, $assocType, $assocId, $level = NOTIFICATION_LEVEL_NORMAL, $contents = null) {
		$contextId = $contextId? (int) $contextId: 0;

		// Get set of notifications user does not want to be notified of
		$notificationSubscriptionSettingsDao =& DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
		$blockedNotifications = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_notification', $userId, $contextId);

		if(!in_array($notificationType, $blockedNotifications)) {
			$notification = new Notification();
			$notification->setUserId((int) $userId);
			$notification->setType((int) $notificationType);
			$notification->setContextId((int) $contextId);
			$notification->setAssocType((int) $assocType);
			$notification->setAssocId((int) $assocId);
			$notification->setLevel((int) $level);

			// If we have custom values for contents, or url, set them so we can store the values in the settings table
			if ($contents) $notification->setData('contents', $contents);

			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notificationDao->insertNotification($notification);

			// Send notification emails
			if ($notification->getLevel() != NOTIFICATION_LEVEL_TRIVIAL) {
				$notificationSubscriptionSettingsDao =& DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
				$notificationEmailSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('emailed_notification', $userId, $contextId);

				if(in_array($notificationType, $notificationEmailSettings)) {
					$this->sendNotificationEmail($request, $notification);
				}
			}

			return $notification;
		}
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $userId int
	 * @param $contents string
	 * @param $notificationType int
	 * @return Notification object
	 */
	function createTrivialNotification($userId, $contents = null, $notificationType = NOTIFICATION_TYPE_SUCCESS) {
		$notification = new Notification();
		$notification->setUserId($userId);
		$notification->setContextId(0);
		$notification->setType($notificationType);
		$notification->setLevel(NOTIFICATION_LEVEL_TRIVIAL);

		// If we have custom values for contents set them so we can store the values in the settings table
		if ($contents) $notification->setData('contents', $contents);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);

		return $notification;
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
				$notificationDao->deleteNotificationById($notification->getId(), $notification->getUserId());
			}
		}
	}

	/**
	 * General notification data formating.
	 * @param $request PKPRequest
	 * @param array $notifications
	 * @return array
	 */
	function formatToGeneralNotification(&$request, $notifications) {
		$formattedNotificationsData = array();
		foreach ($notifications as $notification) {
			$contents = $this->getNotificationContents(&$request, $notification);

			$formattedNotificationsData[] = array(
				'pnotify_title' => __('notification.notification'),
				'pnotify_text' => $contents,
				'pnotify_addClass' => $this->getStyleClass($notification),
				'pnotify_notice_icon' => 'notifyIcon' . $this->getIconClass($notification)
			);
		}

		return $formattedNotificationsData;
	}

	/**
	 * In place notification data formating.
	 * @param $request PKPRequest
	 * @param $notifications array
	 * @return string
	 */
	function formatToInPlaceNotification(&$request, $notifications) {
		$formattedNotificationsData = null;

		if (!empty($notifications)) {
			$templateMgr =& TemplateManager::getManager();
			// Cast the notifications back as an ItemIterator for further processing
			import('lib.pkp.classes.core.ArrayItemIterator');
			$notifications =& new ArrayItemIterator($notifications, 1, 1);

			$templateMgr->assign('inPlaceNotificationContent',
				$this->formatNotifications($request, $notifications, 'controllers/notification/inPlaceNotificationContent.tpl')
			);
			$formattedNotificationsData = $templateMgr->fetch('controllers/notification/inPlaceNotifications.tpl');
		}

		return $formattedNotificationsData;
	}

	/**
	 * Send an email to a user regarding the notification
	 * @param $request PKPRequest
	 * @param $notification object Notification
	 */
	function sendNotificationEmail(&$request, $notification) {
		$userId = $notification->getUserId();
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user = $userDao->getUser($userId);
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		$notificationContents = $notification->getContents();

		import('classes.mail.MailTemplate');
		$site =& $request->getSite();
		$mail = new MailTemplate('NOTIFICATION');
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		$mail->assignParams(array(
			'notificationContents' => $notificationContents,
			'url' => $notification->getUrl(),
			'siteTitle' => $site->getLocalizedTitle()
		));
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->send();
	}

	/**
	 * Send an update to all users on the mailing list
	 * @param $request PKPRequest
	 * @param $notification object Notification
	 */
	function sendToMailingList(&$request, $notification) {
		$notificationMailListDao =& DAORegistry::getDAO('NotificationMailListDAO');
		$mailList = $notificationMailListDao->getMailList($notification->getContextId());
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		foreach ($mailList as $email) {
			if ($notification->getIsLocalized()) {
				$params = array('param' => $notification->getParam());
				$notificationContents = Locale::translate($notification->getContents(), $params);
			} else {
				$notificationContents = $notification->getContents();
			}

			import('classes.mail.MailTemplate');
			$context =& $request->getContext();
			$site =& $request->getSite();

			$mail = new MailTemplate('NOTIFICATION_MAILLIST');
			$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
			$mail->assignParams(array(
				'notificationContents' => $notificationContents,
				'url' => $notification->getLocation(),
				'siteTitle' => $context->getLocalizedTitle(),
				'unsubscribeLink' => $request->url(null, 'notification', 'unsubscribeMailList')
			));
			$mail->addRecipient($email);
			$mail->send();
		}
	}

	/**
	 * Static function to send an email to a mailing list user regarding signup or a lost password
	 * @param $request PKPRequest
	 * @param $email string
	 * @param $token string the user's token (for confirming and unsubscribing)
	 * @param $template string The mail template to use
	 */
	function sendMailingListEmail(&$request, $email, $token, $template) {
		import('classes.mail.MailTemplate');
		$site = $request->getSite();

		$params = array(
			'siteTitle' => $site->getLocalizedTitle(),
			'unsubscribeLink' => $request->url(null, 'notification', 'unsubscribeMailList', array($token))
		);

		if ($template == 'NOTIFICATION_MAILLIST_WELCOME') {
			$confirmLink = $request->url(null, 'notification', 'confirmMailListSubscription', array($token));
			$params["confirmLink"] = $confirmLink;
		}

		$mail = new MailTemplate($template);
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		$mail->assignParams($params);
		$mail->addRecipient($email);
		$mail->send();
	}
}

?>
