<?php
/**
 * @defgroup notification_form
 */

/**
 * @file classes/notification/form/NotificationSettingsForm.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationSettingsForm
 * @ingroup notification_form
 *
 * @brief Form to edit notification settings.
 */

// $Id$


import('lib.pkp.classes.form.Form');

class PKPNotificationSettingsForm extends Form {
	/**
	 * Constructor.
	 */
	function PKPNotificationSettingsForm() {
		parent::Form('notification/settings.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$user = Request::getUser();
		$userId = $user->getId();

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$notificationSettings = $notificationSettingsDao->getNotificationSettings($userId);
		$emailSettings = $notificationSettingsDao->getNotificationEmailSettings($userId);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('notificationSettings', $notificationSettings);
		$templateMgr->assign('emailSettings', $emailSettings);
		$templateMgr->assign('titleVar', Locale::translate('common.title'));
		$templateMgr->assign('userVar', Locale::translate('common.user'));
		return parent::display();
	}
}

?>
