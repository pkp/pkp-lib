<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/PKPNotificationsUnsubscribeForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationsUnsubscribeForm
 * @ingroup notification_form
 *
 * @brief Form to unsubscribe from email notifications.
 */


import('lib.pkp.classes.form.Form');

class PKPNotificationsUnsubscribeForm extends Form {
	var $_notification;
	var $_validationToken;

	/**
	 * Constructor.
	 */
	function __construct($notification, $validationToken) {
		parent::__construct('notification/unsubscribeNotificationsForm.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		$this->_notification = $notification;
		$this->_validationToken = $validationToken;
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$userVars = array();
		foreach($this->getNotificationSettingsMap() as $notificationSetting) {
			$userVars[] = $notificationSetting['emailSettingName'];
		}

		$this->readUserVars($userVars);
	}

	/**
	 * Get all notification settings form names and their setting type values
	 * @return array
	 */
	protected function getNotificationSettingsMap() {
		$notificationManager = new NotificationManager();
		return $notificationManager->getNotificationSettingsMap();
	}

	/**
	 * @copydoc Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$userId = $this->_notification->getUserId();
		$contextId = $this->_notification->getContextId();

		$emailSettings = $this->getNotificationSettingsMap();

		$contextDao = Application::getContextDAO();
		$userDao = DAORegistry::getDAO('UserDAO');

		$user = $userDao->getById($userId);
		$context = $contextDao->getById($contextId);

		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign(array(
			'contextName' => $context->getLocalizedName(),
			'userEmail' => $user->getEmail(),
			'emailSettings' => $emailSettings,
			'validationToken' => $this->_validationToken,
			'notificationId' => $this->_notification->getId(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute
	 */
	function execute() {
		$emailSettings = array();
		foreach($this->getNotificationSettingsMap() as $settingId => $notificationSetting) {
			// Get notifications that the user wants to be notified of by email
			if($this->getData($notificationSetting['emailSettingName'])) $emailSettings[] = $settingId;
		}

		$notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
		$notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings('blocked_emailed_notification', $emailSettings, $this->_notification->getUserId(), $this->_notification->getContextId());

		return true;
	}
}


