<?php
/**
 * @file classes/notification/managerDelegate/AnnouncementNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementNotificationManager
 * @ingroup managerDelegate
 *
 * @brief New announcement notification manager.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\core\Services;
use APP\notification\Notification;
use PKP\announcement\Announcement;
use PKP\core\PKPApplication;

use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Repo;
use PKP\mail\Mail;
use PKP\mail\MailTemplate;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;
use PKP\user\User;

class AnnouncementNotificationManager extends NotificationManagerDelegate
{
    /** @var Announcement The announcement to send a notification about */
    public $_announcement;

    /**
     * Initializes the class.
     *
     * @param Announcement $announcement The announcement to send
     */
    public function initialize(Announcement $announcement): void
    {
        $this->_announcement = $announcement;
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification): string
    {
        return __('emails.announcement.subject');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationContents($request, $notification): EmailTemplate
    {
        return Repo::emailTemplate()->getByKey($notification->getContextId(), 'ANNOUNCEMENT');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $request->getContext()->getData('path'),
            'announcement',
            'view',
            $this->_announcement->getId()
        );
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
     * @return PKPNotification|null The notification instance or null if no notification created
     */
    public function notify(User $user): ?PKPNotification
    {
        return parent::createNotification(
            Application::get()->getRequest(),
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT,
            $this->_announcement->getAssocId(),
            null,
            null,
            Notification::NOTIFICATION_LEVEL_NORMAL,
            ['contents' => $this->_announcement->getLocalizedTitle()],
            false,
            function ($mail) use ($user) {
                return $this->_setupMessage($mail, $user);
            }
        );
    }

    /**
     * @copydoc PKPNotificationManager::getMailTemplate()
     *
     * @param null|mixed $emailKey
     */
    protected function getMailTemplate($emailKey = null): MailTemplate
    {
        $contextId = $this->_announcement->getAssocId();
        $context = Application::get()->getRequest()->getContext();
        if ($context->getId() != $contextId) {
            $context = Services::get('context')->get($contextId);
        }
        return new MailTemplate('ANNOUNCEMENT', null, $context, false);
    }

    /**
     * Setups a customized message for the given user.
     *
     * @param Mail $mail The message which will be customized
     * @param User $user The user who will be notified
     *
     * @return Mail The prepared message
     */
    private function _setupMessage(Mail $mail, User $user): Mail
    {
        $mail->assignParams($this->_getMessageParams($user));
        return $mail;
    }

    /**
     * Retrieves the message parameters.
     *
     * @return array An array with the parameters and their values
     */
    private function _getMessageParams(): array
    {
        return [
            'title' => $this->_announcement->getLocalizedTitle(),
            'summary' => $this->_announcement->getLocalizedDescriptionShort(),
            'announcement' => $this->_announcement->getLocalizedDescription(),
            'url' => Application::get()->getRequest()->getDispatcher()->url(
                Application::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                Application::get()->getRequest()->getContext()->getData('path'),
                'announcement',
                'view',
                $this->_announcement->getId()
            ),
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\AnnouncementNotificationManager', '\AnnouncementNotificationManager');
}
