<?php

/**
 * @file pages/notification/NotificationHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	 * Display help table of contents.
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, &$request) {
		$this->validate();
		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();
		$router =& $request->getRouter();

		$user = $request->getUser();
		if(isset($user)) {
			$userId = $user->getId();
			$templateMgr->assign('isUserLoggedIn', true);
		} else {
			$userId = 0;

			$templateMgr->assign('emailUrl', $router->url($request, null, 'notification', 'subscribeMailList'));
			$templateMgr->assign('isUserLoggedIn', false);
		}
		$context =& $request->getContext();
		$contextId = isset($context)?$context->getId():null;

		$notificationManager = new NotificationManager();
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');

		$rangeInfo =& Handler::getRangeInfo('notifications');

		// Construct the formatted notification string to display in the template
		$formattedNotifications = $notificationManager->getFormattedNotificationsForUser($request, $userId, NOTIFICATION_LEVEL_NORMAL, $contextId, $rangeInfo);

		// Get the same notifications used for the string so we can paginate
		$notifications = $notificationDao->getByUserId($userId, NOTIFICATION_LEVEL_NORMAL, null, $contextId, $rangeInfo);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$templateMgr->assign('formattedNotifications', $formattedNotifications);
		$templateMgr->assign('notifications', $notifications);
		$templateMgr->assign('unread', $notificationDao->getNotificationCount(false, $userId, $contextId));
		$templateMgr->assign('read', $notificationDao->getNotificationCount(true, $userId, $contextId));
		$templateMgr->assign('url', $router->url($request, null, 'notification', 'settings'));
		$templateMgr->display('notification/index.tpl');
	}

	/**
	 * Delete a notification
	 * @param $args array
	 * @param $request Request
	 */
	function delete($args, &$request) {
		$this->validate();

		$notificationId = array_shift($args);
		if (array_shift($args) == 'ajax') {
			$isAjax = true;
		} else $isAjax = false;

		$user = $request->getUser();
		if(isset($user)) {
			$userId = (int) $user->getId();

			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteById($notificationId, $userId);
		}

		if (!$isAjax) {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * View and modify notification settings
	 * @param $args array
	 * @param $request Request
	 */
	function settings($args, &$request) {
		$this->validate();
		$this->setupTemplate();


		$user = $request->getUser();
		if(isset($user)) {
			import('classes.notification.form.NotificationSettingsForm');
			$notificationSettingsForm = new NotificationSettingsForm();
			$notificationSettingsForm->display($request);
		} else {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * Save user notification settings
	 * @param $args array
	 * @param $request Request
	 */
	function saveSettings($args, &$request) {
		$this->validate();
		$this->setupTemplate(true);

		import('classes.notification.form.NotificationSettingsForm');

		$notificationSettingsForm = new NotificationSettingsForm();
		$notificationSettingsForm->readInputData();

		if ($notificationSettingsForm->validate()) {
			$notificationSettingsForm->execute($request);
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification', 'settings'));
		} else {
			$notificationSettingsForm->display($request);
		}
	}

	/**
	 * Fetch the existing or create a new URL for the user's RSS feed
	 * @param $args array
	 * @param $request Request
	 */
	function getNotificationFeedUrl($args, &$request) {
		$user =& $request->getUser();
		$router =& $request->getRouter();
		$context =& $router->getContext($request);

		if(isset($user)) {
			$userId = $user->getId();
		} else {
			$userId = 0;
		}

		$notificationSubscriptionSettingsDao =& DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
		$feedType = array_shift($args);

		$token = $notificationSubscriptionSettingsDao->getRSSTokenByUserId($userId, $context->getId());

		if ($token) {
			$request->redirectUrl($router->url($request, null, 'notification', 'notificationFeed', array($feedType, $token)));
		} else {
			$token = $notificationSubscriptionSettingsDao->insertNewRSSToken($userId, $context->getId());
			$request->redirectUrl($router->url($request, null, 'notification', 'notificationFeed', array($feedType, $token)));
		}
	}

	/**
	 * Fetch the actual RSS feed
	 * @param $args array
	 * @param $request Request
	 */
	function notificationFeed($args, &$request) {

		if(isset($args[0]) && isset($args[1])) {
			$type = $args[0];
			$token = $args[1];
		} else {
			return false;
		}

		$this->setupTemplate(true);

		$application = PKPApplication::getApplication();
		$appName = $application->getNameKey();

		$site =& $request->getSite();
		$siteTitle = $site->getLocalizedTitle();

		$notificationSubscriptionSettingsDao =& DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
		$context =& $request->getContext();
		$userId = $notificationSubscriptionSettingsDao->getUserIdByRSSToken($token, $context->getId());

		// Make sure the feed type is specified and valid
		$typeMap = array(
			'rss' => 'rss.tpl',
			'rss2' => 'rss2.tpl',
			'atom' => 'atom.tpl'
		);
		$contentTypeMap = array(
			'rss' => 'rssContent.tpl',
			'rss2' => 'rss2Content.tpl',
			'atom' => 'atomContent.tpl'
		);
		$mimeTypeMap = array(
			'rss' => 'application/rdf+xml',
			'rss2' => 'application/rss+xml',
			'atom' => 'application/atom+xml'
		);
		if (!isset($typeMap[$type])) return false;

		$notificationManager = new NotificationManager();
		$notifications = $notificationManager->getFormattedNotificationsForUser($request, $userId, NOTIFICATION_LEVEL_NORMAL, $context->getId(), null, 'notification/' . $contentTypeMap[$type]);

		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$version = $versionDao->getCurrentVersion();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('version', $version->getVersionString());
		$templateMgr->assign('selfUrl', $request->getCompleteUrl());
		$templateMgr->assign('locale', AppLocale::getPrimaryLocale());
		$templateMgr->assign('appName', $appName);
		$templateMgr->assign('siteTitle', $siteTitle);
		$templateMgr->assign_by_ref('formattedNotifications', $notifications);

		$templateMgr->display('notification/' . $typeMap[$type], $mimeTypeMap[$type]);

		return true;
	}

	/**
	 * Display the public notification email subscription form
	 * @param $args array
	 * @param $request Request
	 */
	function subscribeMailList($args, &$request) {
		$this->setupTemplate();

		$user = $request->getUser();

		if(!isset($user)) {
			import('lib.pkp.classes.notification.form.NotificationMailingListForm');
			$notificationMailingListForm = new NotificationMailingListForm();
			$notificationMailingListForm->display($request);
		} else {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * Save the public notification email subscription form
	 * @param $args array
	 * @param $request Request
	 */
	function saveSubscribeMailList($args, &$request) {
		$this->validate();
		$this->setupTemplate(true);

		import('lib.pkp.classes.notification.form.NotificationMailingListForm');

		$notificationMailingListForm = new NotificationMailingListForm();
		$notificationMailingListForm->readInputData();

		if ($notificationMailingListForm->validate()) {
			$notificationMailingListForm->execute($request);
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification', 'mailListSubscribed', array('success')));
		} else {
			$notificationMailingListForm->display($request);
		}
	}

	/**
	 * Display a success or error message if the user was subscribed
	 * @param $args array
	 * @param $request Request
	 */
	function mailListSubscribed($args, &$request) {
		$this->setupTemplate();
		$status = array_shift($args);
		$templateMgr =& TemplateManager::getManager();

		if ($status == 'success') {
			$templateMgr->assign('status', 'subscribeSuccess');
		} else {
			$templateMgr->assign('status', 'subscribeError');
			$templateMgr->assign('error', true);
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Confirm the subscription (accessed via emailed link)
	 * @param $args array
	 * @param $request Request
	 */
	function confirmMailListSubscription($args, &$request) {
		$this->setupTemplate();
		$userToken = array_shift($args);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('confirm', true);

		$context =& $request->getContext();

		$notificationMailListDao =& DAORegistry::getDAO('NotificationMailListDAO');
		$settingId = $notificationMailListDao->getMailListIdByToken($userToken, $context->getId());

		if($settingId) {
			$notificationMailListDao->confirmMailListSubscription($settingId);
			$templateMgr->assign('status', 'confirmSuccess');
		} else {
			$templateMgr->assign('status', 'confirmError');
			$templateMgr->assign('error', true);
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Save the maillist unsubscribe form
	 * @param $args array
	 * @param $request Request
	 */
	function unsubscribeMailList($args, &$request) {
		$context =& $request->getContext();

		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();

		$userToken = array_shift($args);

		$notificationMailListDao =& DAORegistry::getDAO('NotificationMailListDAO');
		if(isset($userToken)) {
			if($notificationMailListDao->unsubscribeGuest($userToken, $context->getId())) {
				$templateMgr->assign('status', "unsubscribeSuccess");
				$templateMgr->display('notification/maillistSubscribed.tpl');
			} else {
				$templateMgr->assign('status', "unsubscribeError");
				$templateMgr->assign('error', true);
				$templateMgr->display('notification/maillistSubscribed.tpl');
			}
		}
	}

	/**
	 * Return formatted notification data using Json.
	 * @param $args array
	 * @param $request Request
	 *
	 * @return JSONMessage
	 */
	function fetchNotification($args, &$request) {
		$this->setupTemplate();
		$user =& $request->getUser();
		$context =& $request->getContext();
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notifications = array();

		// Get the notification options from request.
		$notificationOptions = $request->getUserVar('requestOptions');

		if (is_array($notificationOptions)) {
			// Retrieve the notifications.
			$notifications = $this->_getNotificationsByOptions($notificationOptions, $context->getId(), $user->getId());
		} else {
			// No options, get only TRIVIAL notifications.
			$notifications =& $notificationDao->getByUserId($user->getId(), NOTIFICATION_LEVEL_TRIVIAL);
			$notifications =& $notifications->toArray();
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

		return $json->getString();
	}

	/**
	 * Get the notifications using options.
	 * @param $notificationOptions Array
	 * @param $contextId int
	 * @param $userId int
	 * @return Array
	 */
	function &_getNotificationsByOptions($notificationOptions, $contextId, $userId = null) {
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationsArray = array();
		$notificationMgr = new NotificationManager();
		$allUsersNotificationTypes = $notificationMgr->getAllUsersNotificationTypes();

		foreach ($notificationOptions as $level => $levelOptions) {
			if ($levelOptions) {
				foreach ($levelOptions as $type => $typeOptions) {
					if ($typeOptions) {
						in_array($type, $allUsersNotificationTypes) ? $workingUserId = null : $workingUserId = $userId;
						$notificationsResultFactory =& $notificationDao->getByAssoc($typeOptions['assocType'], $typeOptions['assocId'], $workingUserId, $type, $contextId);
						$notificationsArray =& $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
					} else {
						if ($userId) {
							$notificationsResultFactory =& $notificationDao->getByUserId($userId, $level, $type, $contextId);
							$notificationsArray =& $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
						}
					}
				}
			} else {
				if ($userId) {
					$notificationsResultFactory =& $notificationDao->getByUserId($userId, $level, null, $contextId);
					$notificationsArray =& $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
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
	function &_addNotificationsToArray(&$resultFactory, &$notificationArray) {
		if (!$resultFactory->wasEmpty()) {
			$notificationArray = array_merge($notificationArray, $resultFactory->toArray());
		}

		return $notificationArray;
	}

	/**
	 * Override setupTemplate() so we can load other locale components.
	 * @see PKPHandler::setupTemplate()
	 */
	function setupTemplate() {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_SUBMISSION);
		parent::setupTemplate();
	}
}

?>
