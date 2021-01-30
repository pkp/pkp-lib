<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/NotificationSettingsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	function __construct() {
		parent::__construct('user/notificationSettingsForm.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$userVars = array();
		foreach($this->getNotificationSettingsMap() as $notificationSetting) {
			$userVars[] = $notificationSetting['settingName'];
			$userVars[] = $notificationSetting['emailSettingName'];
		}

		$this->readUserVars($userVars);
	}

	/**
	 * Get all notification settings form names and their setting type values
	 * @return array
	 */
	protected function getNotificationSettingsMap() {
		$result = array(
			NOTIFICATION_TYPE_SUBMISSION_SUBMITTED => array('settingName' => 'notificationSubmissionSubmitted',
				'emailSettingName' => 'emailNotificationSubmissionSubmitted',
				'settingKey' => 'notification.type.submissionSubmitted'),
			NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED => array('settingName' => 'notificationEditorAssignmentRequired',
				'emailSettingName' => 'emailNotificationEditorAssignmentRequired',
				'settingKey' => 'notification.type.editorAssignmentTask'),
			NOTIFICATION_TYPE_METADATA_MODIFIED => array('settingName' => 'notificationMetadataModified',
				'emailSettingName' => 'emailNotificationMetadataModified',
				'settingKey' => 'notification.type.metadataModified'),
			NOTIFICATION_TYPE_REVIEWER_COMMENT => array('settingName' => 'notificationReviewerComment',
				'emailSettingName' => 'emailNotificationReviewerComment',
				'settingKey' => 'notification.type.reviewerComment'),
			NOTIFICATION_TYPE_NEW_QUERY => array('settingName' => 'notificationNewQuery',
				'emailSettingName' => 'emailNotificationNewQuery',
				'settingKey' => 'notification.type.queryAdded'),
			NOTIFICATION_TYPE_QUERY_ACTIVITY => array('settingName' => 'notificationQueryActivity',
				'emailSettingName' => 'emailNotificationQueryActivity',
				'settingKey' => 'notification.type.queryActivity'),
			NOTIFICATION_TYPE_NEW_ANNOUNCEMENT => array('settingName' => 'notificationNewAnnouncement',
				'emailSettingName' => 'emailNotificationNewAnnouncement',
				'settingKey' => 'notification.type.newAnnouncement'),
			NOTIFICATION_TYPE_EDITORIAL_REPORT => array('settingName' => 'notificationEditorialReport',
				'emailSettingName' => 'emailNotificationEditorialReport',
				'settingKey' => 'notification.type.editorialReport')
		);

		HookRegistry::call(strtolower_codesafe(get_class($this) . '::getNotificationSettingsMap'), array($this, &$result));

		return $result;
	}

	/**
	 * Get a list of notification category names (to display as headers)
	 *  and the notification types under each category
	 * @return array
	 */
	public function getNotificationSettingCategories() {
		$result = array(
			// Changing the `categoryKey` for public notification types will disrupt
			// the email notification opt-in/out feature during user registration
			// @see RegistrationForm::execute()
			array('categoryKey' => 'notification.type.public',
				'settings' => array(
					NOTIFICATION_TYPE_NEW_ANNOUNCEMENT,
				)
			),
			array('categoryKey' => 'notification.type.submissions',
				'settings' => array(
					NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
					NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
					NOTIFICATION_TYPE_METADATA_MODIFIED,
					NOTIFICATION_TYPE_NEW_QUERY,
					NOTIFICATION_TYPE_QUERY_ACTIVITY,
				)
			),
			array('categoryKey' => 'notification.type.reviewing',
				'settings' => array(
					NOTIFICATION_TYPE_REVIEWER_COMMENT,
				)
			),
			array('categoryKey' => 'user.role.editors',
				'settings' => array(
					NOTIFICATION_TYPE_EDITORIAL_REPORT,
				)
			),
		);

		HookRegistry::call(strtolower_codesafe(get_class($this) . '::getNotificationSettingCategories'), array($this, &$result));

		return $result;
	}

	/**
	 * @copydoc Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		$userId = $request->getUser()->getId();
		$notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /* @var $notificationSubscriptionSettingsDao NotificationSubscriptionSettingsDAO */
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'blockedNotifications' => $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_notification', $userId, $contextId),
			'emailSettings' => $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_emailed_notification', $userId, $contextId),
			'notificationSettingCategories' => $this->getNotificationSettingCategories(),
			'notificationSettings' => $this->getNotificationSettingsMap(),
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute
	 */
	function execute(...$functionParams) {
		parent::execute(...$functionParams);

		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$userId = $user->getId();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$blockedNotifications = array();
		$emailSettings = array();
		foreach($this->getNotificationSettingsMap() as $settingId => $notificationSetting) {
			// Get notifications that the user wants blocked
			if(!$this->getData($notificationSetting['settingName'])) $blockedNotifications[] = $settingId;
			// Get notifications that the user wants to be notified of by email
			if($this->getData($notificationSetting['emailSettingName'])) $emailSettings[] = $settingId;
		}

		$notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /* @var $notificationSubscriptionSettingsDao NotificationSubscriptionSettingsDAO */
		$notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings('blocked_notification', $blockedNotifications, $userId, $contextId);
		$notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings('blocked_emailed_notification', $emailSettings, $userId, $contextId);

		return true;
	}
}


