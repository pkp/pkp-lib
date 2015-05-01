<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/NotificationSettingsForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationSettingsForm
 * @ingroup notification_form
 *
 * @brief Form to edit notification settings.
 */


import('lib.pkp.classes.form.Form');

class PKPNotificationSettingsForm extends Form {
	/**
	 * Constructor.
	 */
	function PKPNotificationSettingsForm() {
		parent::Form('user/notificationSettingsForm.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * @copydoc
	 */
	function fetch($request) {
		$context = $request->getContext();
		$user = $request->getUser();
		$userId = $user->getId();

		$notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
		$blockedNotifications = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_notification', $userId, $context->getId());
		$emailSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_emailed_notification', $userId, $context->getId());

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('blockedNotifications', $blockedNotifications);
		$templateMgr->assign('emailSettings', $emailSettings);
		$templateMgr->assign('titleVar', __('common.title'));
		$templateMgr->assign('userVar', __('common.user'));
		return parent::fetch();
	}
}

?>
