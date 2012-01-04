<?php
/**
 * @defgroup notification_form
 */

/**
 * @file classes/notification/form/NotificationMailingListForm.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationMailingListForm
 * @ingroup notification_form
 *
 * @brief Form to subscribe to the notification mailing list
 */

// $Id$


import('lib.pkp.classes.form.Form');
import('classes.notification.Notification');

class NotificationMailingListForm extends Form {
	/**
	 * Constructor.
	 */
	function NotificationMailingListForm() {
		parent::Form('notification/maillist.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'notification.mailList.emailInvalid'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailsDoNotMatch', create_function('$email,$form', 'return $email == $form->getData(\'confirmEmail\');'), array(&$this)));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('email', 'confirmEmail'));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('new', true);

		$templateMgr->assign('settings', Notification::getSubscriptionSettings());

		return parent::display();
	}

	/**
	 * Save the form
	 */
	function execute() {
		$userEmail = $this->getData('email');

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		if($password = $notificationSettingsDao->subscribeGuest($userEmail)) {
			Notification::sendMailingListEmail($userEmail, $password, 'NOTIFICATION_MAILLIST_WELCOME');
			return true;
		} else {
			PKPRequest::redirect(null, 'notification', 'mailListSubscribed', array('error'));
			return false;
		}
	}
}

?>
