<?php
/**
 * @file classes/notification/managerDelegate/EditorialReportNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReportNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Editorial report notification manager.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\core\Request;
use APP\notification\Notification;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\user\User;

class EditorialReportNotificationManager extends NotificationManagerDelegate
{
    private Context $_context;
    private Request $_request;

    /**
     * @copydoc NotificationManagerDelegate::__construct()
     */
    public function __construct(int $notificationType)
    {
        parent::__construct($notificationType);
        $this->_request = Application::get()->getRequest();
    }

    /**
     * Initializes the class.
     *
     * @param Context $context The context from where the statistics shall be retrieved
     */
    public function initialize(Context $context): void
    {
        $this->_context = $context;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification): string
    {
        return __('notification.type.editorialReport', [], $this->_context->getPrimaryLocale());
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $application = Application::get();
        $context = $application->getContextDAO()->getById($notification->getContextId());
        return $application->getDispatcher()->url($this->_request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'editorial');
    }

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass($notification): string
    {
        return 'notifyIconInfo';
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass($notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * Sends a notification to the given user.
     *
     * @param User $user The user who will be notified
     *
     * @return PKPNotification|null The notification instance
     */
    public function notify(User $user): ?PKPNotification
    {
        return parent::createNotification(
            $this->_request,
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT,
            $this->_context->getId(),
            null,
            null,
            Notification::NOTIFICATION_LEVEL_TASK,
            ['contents' => __('notification.type.editorialReport.contents', [], $this->_context->getPrimaryLocale())]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorialReportNotificationManager', '\EditorialReportNotificationManager');
}
