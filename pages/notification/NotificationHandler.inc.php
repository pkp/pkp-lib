<?php

/**
 * @file NotificationHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing notifications.
 */

import('handler.Handler');
import('notification.Notification');

class NotificationHandler extends Handler {

	/**
	 * Display help table of contents.
	 */
	function index() {
		$this->validate();
		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();

		$user = Request::getUser();
		if(isset($user)) {
			$userId = $user->getId();
			$templateMgr->assign('isUserLoggedIn', true);
		} else {
			$userId = 0;
			$templateMgr->assign('emailUrl', PKPRequest::url(NotificationHandler::getContextDepthArray(), 'notification', 'subscribeMailList'));
			$templateMgr->assign('isUserLoggedIn', false);
		}

		$rangeInfo =& Handler::getRangeInfo('notifications');
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notifications = $notificationDao->getByUserId($userId, NOTIFICATION_LEVEL_NORMAL, $rangeInfo);

		$templateMgr->assign('notifications', $notifications);
		$templateMgr->assign('unread', $notificationDao->getUnreadNotificationCount($userId));
		$templateMgr->assign('read', $notificationDao->getReadNotificationCount($userId));
		$templateMgr->assign('url', PKPRequest::url(NotificationHandler::getContextDepthArray(), 'notification', 'settings'));
		$templateMgr->display('notification/index.tpl');
	}

	/**
	 * Delete a notification
	 */
	function delete($args) {
		$this->validate();

		$notificationId = array_shift($args);
		if (array_shift($args) == 'ajax') {
			$isAjax = true;
		} else $isAjax = false;

		$user = Request::getUser();
		if(isset($user)) {
			$userId = $user->getId();
			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notifications = $notificationDao->deleteNotificationById($notificationId, $userId);
		}

		if (!$isAjax) PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification');
	}

	/**
	 * View and modify notification settings
	 */
	function settings() {
		$this->validate();
		$this->setupTemplate();


		$user = Request::getUser();
		if(isset($user)) {
			import('notification.form.NotificationSettingsForm');
			$notificationSettingsForm = new NotificationSettingsForm();
			$notificationSettingsForm->display();
		} else PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification');
	}

	/**
	 * Save user notification settings
	 */
	function saveSettings() {
		$this->validate();
		$this->setupTemplate(true);

		import('notification.form.NotificationSettingsForm');

		$notificationSettingsForm = new NotificationSettingsForm();
		$notificationSettingsForm->readInputData();

		if ($notificationSettingsForm->validate()) {
			$notificationSettingsForm->execute();
			PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification', 'settings');
		} else {
			$notificationSettingsForm->display();
		}
	}

	/**
	 * Fetch the existing or create a new URL for the user's RSS feed
	 */
	function getNotificationFeedUrl($args) {
		$user = Request::getUser();
		if(isset($user)) {
			$userId = $user->getId();
		} else $userId = 0;

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$feedType = array_shift($args);

		$token = $notificationSettingsDao->getRSSTokenByUserId($userId);

		if ($token) {
			PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification', 'notificationFeed', array($feedType, $token));
		} else {
			$token = $notificationSettingsDao->insertNewRSSToken($userId);
			PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification', 'notificationFeed', array($feedType, $token));
		}
	}

	/**
	 * Fetch the actual RSS feed
	 */
	function notificationFeed($args) {
		if(isset($args[0]) && isset($args[1])) {
			$type = $args[0];
			$token = $args[1];
		} else return false;

		$this->setupTemplate(true);

		$application = PKPApplication::getApplication();
		$appName = $application->getNameKey();

		$site =& Request::getSite();
		$siteTitle = $site->getLocalizedTitle();

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');

		$userId = $notificationSettingsDao->getUserIdByRSSToken($token);
		$notifications = $notificationDao->getByUserId($userId);

		// Make sure the feed type is specified and valid
		$typeMap = array(
			'rss' => 'rss.tpl',
			'rss2' => 'rss2.tpl',
			'atom' => 'atom.tpl'
		);
		$mimeTypeMap = array(
			'rss' => 'application/rdf+xml',
			'rss2' => 'application/rss+xml',
			'atom' => 'application/atom+xml'
		);
		if (!isset($typeMap[$type])) return false;

		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$version = $versionDao->getCurrentVersion();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('version', $version->getVersionString());
		$templateMgr->assign('selfUrl', Request::getCompleteUrl());
		$templateMgr->assign('locale', AppLocale::getPrimaryLocale());
		$templateMgr->assign('appName', $appName);
		$templateMgr->assign('siteTitle', $siteTitle);
		$templateMgr->assign_by_ref('notifications', $notifications->toArray());

		$templateMgr->display(Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR .
			'pkp' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'notification' . DIRECTORY_SEPARATOR . $typeMap[$type], $mimeTypeMap[$type]);

		return true;
	}

	/**
	 * Display the public notification email subscription form
	 */
	function subscribeMailList() {
		$this->setupTemplate();

		$user = Request::getUser();

		if(!isset($user)) {
			import('notification.form.NotificationMailingListForm');
			$notificationMailingListForm = new NotificationMailingListForm();
			$notificationMailingListForm->display();
		} else PKPRequest::redirect(NotificationHandler::getContextDepthArray(), 'notification');
	}

	/**
	 * Save the public notification email subscription form
	 */
	function saveSubscribeMailList() {
		$this->validate();
		$this->setupTemplate(true);

		import('notification.form.NotificationMailingListForm');

		$notificationMailingListForm = new NotificationMailingListForm();
		$notificationMailingListForm->readInputData();

		if ($notificationMailingListForm->validate()) {
			$notificationMailingListForm->execute();
			PKPRequest::redirect(null, 'notification', 'mailListSubscribed', array('success'));
		} else {
			$notificationMailingListForm->display();
		}
	}

	/**
	 * Display a success or error message if the user was subscribed
	 */
	function mailListSubscribed($args) {
		$this->setupTemplate();
		$status = array_shift($args);
		$templateMgr =& TemplateManager::getManager();

		if ($status = 'success') {
			$templateMgr->assign('status', 'subscribeSuccess');
		} else {
			$templateMgr->assign('status', 'subscribeError');
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Confirm the subscription (accessed via emailed link)
	 */
	function confirmMailListSubscription($args) {
		$this->setupTemplate();
		$keyHash = array_shift($args);
		$email = array_shift($args);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('confirm', true);

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$settingId = $notificationSettingsDao->getMailListSettingId($email);

		$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
		$accessKey = $accessKeyDao->getAccessKeyByKeyHash('MailListContext', $settingId, $keyHash);

		if($accessKey) {
			$notificationSettingsDao->confirmMailListSubscription($settingId);
			$templateMgr->assign('status', 'confirmSuccess');
		} else {
			$templateMgr->assign('status', 'confirmError');
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Save the maillist unsubscribe form
	 */
	function unsubscribeMailList() {
		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();

		$userEmail = Request::getUserVar('email');
		$userPassword = Request::getUserVar('password');

		if($userEmail != '' && $userPassword != '') {
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
			if($notificationSettingsDao->unsubscribeGuest($userEmail, $userPassword)) {
				$templateMgr->assign('success', "notification.unsubscribeSuccess");
				$templateMgr->display('notification/maillistSettings.tpl');
			} else {
				$templateMgr->assign('error', "notification.unsubscribeError");
				$templateMgr->display('notification/maillistSettings.tpl');
			}
		} else if($userEmail != '' && $userPassword == '') {
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
			if($newPassword = $notificationSettingsDao->resetPassword($userEmail)) {
				Notification::sendMailingListEmail($userEmail, $newPassword, 'NOTIFICATION_MAILLIST_PASSWORD');
				$templateMgr->assign('success', "notification.reminderSent");
				$templateMgr->display('notification/maillistSettings.tpl');
			} else {
				$templateMgr->assign('error', "notification.reminderError");
				$templateMgr->display('notification/maillistSettings.tpl');
			}
		} else {
			$templateMgr->assign('remove', true);
			$templateMgr->display('notification/maillistSettings.tpl');
		}
	}

	/**
	 * Return an array with null values * the context depth
	 */
	 function getContextDepthArray() {
	 	$contextDepthArray = array();

	 	$application = PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();

		for ($i=0; $i < $contextDepth; $i++) {
			array_push($contextDepthArray, null);
		}

		return $contextDepthArray;
	 }
}

?>
