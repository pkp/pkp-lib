<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/PKPNotificationsUnsubscribeForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationsUnsubscribeForm
 * @ingroup notification_form
 *
 * @brief Form to unsubscribe from email notifications.
 */


import('lib.pkp.classes.form.Form');

class PKPNotificationsUnsubscribeForm extends Form {
	/** @var Notification The notification that triggered the unsubscribe event */
	var $_notification;

	/** @var string The unsubscribe validation token */
	var $_validationToken;

	/**
	 * Constructor.
	 *
	 * @param $notification Notification The notification that triggered the unsubscribe event
	 * @param $validationToken string $name The unsubscribe validation token
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
	public function readInputData() {
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
	public function fetch($request, $template = null, $display = false) {
		$userId = $this->_notification->getUserId();
		$contextId = $this->_notification->getContextId();

		if ($contextId != $request->getContext()->getId()) {
			$dispatcher = $request->getDispatcher();
			$dispatcher->handle404();
		}

		$emailSettings = $this->getNotificationSettingsMap();

		$userDao = DAORegistry::getDAO('UserDAO');

		$user = $userDao->getById($userId);
		$context = $request->getContext();

		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign([
			'contextName' => $context->getLocalizedName(),
			'userEmail' => $user->getEmail(),
			'emailSettings' => $emailSettings,
			'validationToken' => $this->_validationToken,
			'notificationId' => $this->_notification->getId(),
		]);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute
	 */
	public function execute(...$functionArgs) {
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


