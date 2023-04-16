<?php
/**
 * @defgroup notification_form Notification Form
 */

/**
 * @file classes/notification/form/PKPNotificationsUnsubscribeForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationsUnsubscribeForm
 *
 * @ingroup notification_form
 *
 * @brief Form to unsubscribe from email notifications.
 */

namespace PKP\notification\form;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\notification\NotificationSubscriptionSettingsDAO;

class PKPNotificationsUnsubscribeForm extends Form
{
    /** @var Notification The notification that triggered the unsubscribe event */
    public $_notification;

    /** @var string The unsubscribe validation token */
    public $_validationToken;

    /**
     * Constructor.
     *
     * @param Notification $notification The notification that triggered the unsubscribe event
     * @param string $validationToken $name The unsubscribe validation token
     */
    public function __construct($notification, $validationToken)
    {
        parent::__construct('notification/unsubscribeNotificationsForm.tpl');

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

        $this->_notification = $notification;
        $this->_validationToken = $validationToken;
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $userVars = [];
        foreach ($this->getNotificationSettingsMap() as $notificationSetting) {
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
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $userId = $this->_notification->getUserId();
        $contextId = $this->_notification->getContextId();

        if ($contextId != $request->getContext()->getId()) {
            $dispatcher = $request->getDispatcher();
            $dispatcher->handle404();
        }

        $emailSettings = $this->getNotificationSettingsMap();

        $user = Repo::user()->get($userId);
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
    public function execute(...$functionArgs)
    {
        $emailSettings = [];
        foreach ($this->getNotificationSettingsMap() as $settingId => $notificationSetting) {
            // Get notifications that the user wants to be notified of by email
            if ($this->getData($notificationSetting['emailSettingName'])) {
                $emailSettings[] = $settingId;
            }
        }

        /** @var NotificationSubscriptionSettingsDAO */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        $notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings('blocked_emailed_notification', $emailSettings, $this->_notification->getUserId(), $this->_notification->getContextId());

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\form\PKPNotificationsUnsubscribeForm', '\PKPNotificationsUnsubscribeForm');
}
