<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/NotificationSettingsForm.php
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

namespace PKP\notification\form;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;

use PKP\form\Form;
use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;

class PKPNotificationSettingsForm extends Form
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('user/notificationSettingsForm.tpl');

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $userVars = [];
        foreach ($this->getNotificationSettingsMap() as $notificationSetting) {
            $userVars[] = $notificationSetting['settingName'];
            $userVars[] = $notificationSetting['emailSettingName'];
        }

        $this->readUserVars($userVars);
    }

    /**
     * Get all notification settings form names and their setting type values
     *
     * @return array
     */
    protected function getNotificationSettingsMap()
    {
        $notificationManager = new NotificationManager();
        return $notificationManager->getNotificationSettingsMap();
    }

    /**
     * Get a list of notification category names (to display as headers)
     *  and the notification types under each category
     *
     * @return array
     */
    public function getNotificationSettingCategories()
    {
        $result = [
            // Changing the `categoryKey` for public notification types will disrupt
            // the email notification opt-in/out feature during user registration
            // @see RegistrationForm::execute()
            ['categoryKey' => 'notification.type.public',
                'settings' => [
                    PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT,
                ]
            ],
            ['categoryKey' => 'notification.type.submissions',
                'settings' => [
                    PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                    PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
                    PKPNotification::NOTIFICATION_TYPE_METADATA_MODIFIED,
                    PKPNotification::NOTIFICATION_TYPE_NEW_QUERY,
                    PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                ]
            ],
            ['categoryKey' => 'notification.type.reviewing',
                'settings' => [
                    PKPNotification::NOTIFICATION_TYPE_REVIEWER_COMMENT,
                ]
            ],
            ['categoryKey' => 'user.role.editors',
                'settings' => [
                    PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REMINDER,
                    PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT,
                ]
            ],
        ];

        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        HookRegistry::call(strtolower_codesafe(end($classNameParts) . '::getNotificationSettingCategories'), [$this, &$result]);

        return $result;
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        $userId = $request->getUser()->getId();
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'blockedNotifications' => $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_notification', $userId, $contextId),
            'emailSettings' => $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings('blocked_emailed_notification', $userId, $contextId),
            'notificationSettingCategories' => $this->getNotificationSettingCategories(),
            'notificationSettings' => $this->getNotificationSettingsMap(),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $userId = $user->getId();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;

        $blockedNotifications = [];
        $emailSettings = [];
        foreach ($this->getNotificationSettingsMap() as $settingId => $notificationSetting) {
            // Get notifications that the user wants blocked
            if (!$this->getData($notificationSetting['settingName'])) {
                $blockedNotifications[] = $settingId;
            }
            // Get notifications that the user wants to be notified of by email
            if ($this->getData($notificationSetting['emailSettingName'])) {
                $emailSettings[] = $settingId;
            }
        }

        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings($notificationSubscriptionSettingsDao::BLOCKED_NOTIFICATION_KEY, $blockedNotifications, $userId, $contextId);
        $notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings($notificationSubscriptionSettingsDao::BLOCKED_EMAIL_NOTIFICATION_KEY, $emailSettings, $userId, $contextId);

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\form\PKPNotificationSettingsForm', '\PKPNotificationSettingsForm');
}
