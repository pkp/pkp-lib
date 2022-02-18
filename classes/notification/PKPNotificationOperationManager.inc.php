<?php

/**
 * @file classes/notification/PKPNotificationOperationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationOperationManager
 * @ingroup notification
 *
 * @see NotificationDAO
 * @see Notification
 * @brief Base class for notification manager that implements
 * basic notification operations and default notifications info.
 * Subclasses can implement specific information.
 */

namespace PKP\notification;

use APP\core\Application;
use PKP\facades\Locale;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Firebase\JWT\JWT;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

use PKP\mail\MailTemplate;

abstract class PKPNotificationOperationManager implements INotificationInfoProvider
{
    //
    // Implement INotificationInfoProvider with default values.
    //
    /**
     * @copydoc INotificationInfoProvider::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        return null;
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        return null;
    }

    /**
     * Provide the notification message as default content.
     *
     * @copydoc INotificationInfoProvider::getNotificationContents()
     */
    public function getNotificationContents($request, $notification)
    {
        return $this->getNotificationMessage($request, $notification);
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationTitle()
     */
    public function getNotificationTitle($notification)
    {
        return __('notification.notification');
    }

    /**
     * @copydoc INotificationInfoProvider::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        return '';
    }

    /**
     * @copydoc INotificationInfoProvider::getIconClass()
     */
    public function getIconClass($notification)
    {
        return '';
    }

    /**
     * @copydoc INotificationInfoProvider::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers($notificationType, $assocType, $assocId)
    {
        return false;
    }


    //
    // Notification manager operations.
    //
    /**
     * Iterate through the localized params for a notification's locale key.
     *  For each parameter, return (in preferred order) a value for the user's current locale,
     *  a param for the journal's default locale, or the first value (in case the value
     *  is not localized)
     *
     * @param array $params
     *
     * @return array
     */
    public function getParamsForCurrentLocale($params)
    {
        $locale = Locale::getLocale();
        $primaryLocale = Locale::getPrimaryLocale();

        $localizedParams = [];
        foreach ($params as $name => $value) {
            if (!is_array($value)) {
                // Non-localized text
                $localizedParams[$name] = $value;
            } elseif (isset($value[$locale])) {
                // Check if the parameter is in the user's current locale
                $localizedParams[$name] = $value[$locale];
            } elseif (isset($value[$primaryLocale])) {
                // Check if the parameter is in the default site locale
                $localizedParams[$name] = $value[$primaryLocale];
            } else {
                $context = Application::get()->getRequest()->getContext();
                // Otherwise, iterate over all supported locales and return the first match
                $locales = $context->getSupportedLocaleNames();
                foreach ($locales as $localeKey) {
                    if (isset($value[$localeKey])) {
                        $localizedParams[$name] = $value[$localeKey];
                    }
                }
            }
        }

        return $localizedParams;
    }

    /**
     * Create a new notification with the specified arguments and insert into DB
     *
     * @param PKPRequest $request
     * @param int $userId (optional)
     * @param int $notificationType
     * @param int $contextId
     * @param int $assocType
     * @param int $assocId
     * @param int $level
     * @param array $params
     * @param bool $suppressEmail Whether or not to suppress the notification email.
     * @param callable $mailConfigurator Enables the customization of the Notification email
     *
     * @return Notification object|null
     */
    public function createNotification($request, $userId = null, $notificationType = null, $contextId = null, $assocType = null, $assocId = null, $level = Notification::NOTIFICATION_LEVEL_NORMAL, $params = null, $suppressEmail = false, callable $mailConfigurator = null)
    {
        $blockedNotifications = $this->getUserBlockedNotifications($userId, $contextId);

        if (!in_array($notificationType, $blockedNotifications)) {
            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $notification = $notificationDao->newDataObject(); /** @var Notification $notification */
            $notification->setUserId((int) $userId);
            $notification->setType((int) $notificationType);
            $notification->setContextId((int) $contextId);
            $notification->setAssocType((int) $assocType);
            $notification->setAssocId((int) $assocId);
            $notification->setLevel((int) $level);

            $notificationId = $notificationDao->insertObject($notification);

            // Send notification emails
            if ($notification->getLevel() != Notification::NOTIFICATION_LEVEL_TRIVIAL && !$suppressEmail) {
                $notificationEmailSettings = $this->getUserBlockedEmailedNotifications($userId, $contextId);

                if (!in_array($notificationType, $notificationEmailSettings)) {
                    $this->sendNotificationEmail($request, $notification, $contextId, $mailConfigurator);
                }
            }

            if ($params) {
                $notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /** @var NotificationSettingsDAO $notificationSettingsDao */
                foreach ($params as $name => $value) {
                    $notificationSettingsDao->updateNotificationSetting($notificationId, $name, $value);
                }
            }

            return $notification;
        }
    }

    /**
     * Create a new notification with the specified arguments and insert into DB
     * This is a static method
     *
     * @param int $userId
     * @param int $notificationType
     * @param array $params
     *
     * @return Notification object
     */
    public function createTrivialNotification($userId, $notificationType = PKPNotification::NOTIFICATION_TYPE_SUCCESS, $params = null)
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notification = $notificationDao->newDataObject();
        $notification->setUserId($userId);
        $notification->setContextId(\PKP\core\PKPApplication::CONTEXT_ID_NONE);
        $notification->setType($notificationType);
        $notification->setLevel(Notification::NOTIFICATION_LEVEL_TRIVIAL);

        $notificationId = $notificationDao->insertObject($notification);

        if ($params) {
            $notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /** @var NotificationSettingsDAO $notificationSettingsDao */
            foreach ($params as $name => $value) {
                $notificationSettingsDao->updateNotificationSetting($notificationId, $name, $value);
            }
        }

        return $notification;
    }

    /**
     * Deletes trivial notifications from database.
     *
     * @param array $notifications
     */
    public function deleteTrivialNotifications($notifications)
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        foreach ($notifications as $notification) {
            // Delete only trivial notifications.
            if ($notification->getLevel() == Notification::NOTIFICATION_LEVEL_TRIVIAL) {
                $notificationDao->deleteById($notification->getId(), $notification->getUserId());
            }
        }
    }

    /**
     * General notification data formating.
     *
     * @param PKPRequest $request
     * @param array $notifications
     *
     * @return array
     */
    public function formatToGeneralNotification($request, $notifications)
    {
        $formattedNotificationsData = [];
        foreach ($notifications as $notification) { /** @var Notification $notification */
            $formattedNotificationsData[$notification->getLevel()][$notification->getId()] = [
                'title' => $this->getNotificationTitle($notification),
                'text' => $this->getNotificationContents($request, $notification),
                'addclass' => $this->getStyleClass($notification),
                'notice_icon' => $this->getIconClass($notification),
                'styling' => 'jqueryui',
            ];
        }

        return $formattedNotificationsData;
    }

    /**
     * In place notification data formating.
     *
     * @param PKPRequest $request
     * @param array $notifications
     *
     * @return array
     */
    public function formatToInPlaceNotification($request, $notifications)
    {
        $formattedNotificationsData = null;

        if (!empty($notifications)) {
            $templateMgr = TemplateManager::getManager($request);
            foreach ((array)$notifications as $notification) {
                $formattedNotificationsData[$notification->getLevel()][$notification->getId()] = $this->formatNotification($request, $notification, 'controllers/notification/inPlaceNotificationContent.tpl');
            }
        }

        return $formattedNotificationsData;
    }

    /**
     * Get set of notifications types user does not want to be notified of.
     *
     * @param int $userId The notification user
     * @param int $contextId
     *
     * @return array
     */
    protected function getUserBlockedNotifications($userId, $contextId)
    {
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        return $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY, $userId, (int) $contextId);
    }

    /**
     * Get set of notification types user will also be notified by email.
     *
     * @return array
     */
    protected function getUserBlockedEmailedNotifications($userId, $contextId)
    {
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        return $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY, $userId, (int) $contextId);
    }

    /**
     * Get a template mail instance.
     *
     * @param string $emailKey
     *
     * @return MailTemplate
     *
     * @see MailTemplate
     */
    protected function getMailTemplate($emailKey = null)
    {
        return new MailTemplate($emailKey, null, null, false);
    }

    /**
     * Get a notification content with a link action.
     *
     * @param LinkAction $linkAction
     * @param Request $request
     *
     * @return string
     */
    protected function fetchLinkActionNotificationContent($linkAction, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('linkAction', $linkAction);
        return $templateMgr->fetch('controllers/notification/linkActionNotificationContent.tpl');
    }


    //
    // Private helper methods.
    //
    /**
     * Return a string of formatted notifications for display
     *
     * @param PKPRequest $request
     * @param object $notifications DAOResultFactory
     * @param string $notificationTemplate optional Template to use for constructing an individual notification for display
     *
     * @return string
     */
    private function formatNotifications($request, $notifications, $notificationTemplate = 'notification/notification.tpl')
    {
        $notificationString = '';

        // Build out the notifications based on their associated objects and format into a string
        while ($notification = $notifications->next()) {
            $notificationString .= $this->formatNotification($request, $notification, $notificationTemplate);
        }

        return $notificationString;
    }

    /**
     * Return a fully formatted notification for display
     *
     * @param PKPRequest $request
     * @param object $notification Notification
     *
     * @return string
     */
    private function formatNotification($request, $notification, $notificationTemplate = 'notification/notification.tpl')
    {
        $templateMgr = TemplateManager::getManager($request);

        // Set the date read if it isn't already set
        if (!$notification->getDateRead()) {
            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $dateRead = $notificationDao->setDateRead($notification->getId(), Core::getCurrentDate());
            $notification->setDateRead($dateRead);
        }

        $user = $request->getUser();
        $templateMgr->assign([
            'isUserLoggedIn' => $user,
            'notificationDateCreated' => $notification->getDateCreated(),
            'notificationId' => $notification->getId(),
            'notificationContents' => $this->getNotificationContents($request, $notification),
            'notificationTitle' => $this->getNotificationTitle($notification),
            'notificationStyleClass' => $this->getStyleClass($notification),
            'notificationIconClass' => $this->getIconClass($notification),
            'notificationDateRead' => $notification->getDateRead(),
        ]);

        if ($notification->getLevel() != Notification::NOTIFICATION_LEVEL_TRIVIAL) {
            $templateMgr->assign('notificationUrl', $this->getNotificationUrl($request, $notification));
        }

        return $templateMgr->fetch($notificationTemplate);
    }

    /**
     * Send an email to a user regarding the notification
     *
     * @param PKPRequest $request
     * @param object $notification Notification
     * @param int|null $contextId Context ID
     * @param callable $mailConfigurator If specified, must return a MailTemplate instance. A ready MailTemplate object will be provided as argument
     */
    protected function sendNotificationEmail($request, $notification, ?int $contextId, callable $mailConfigurator = null)
    {
        $userId = $notification->getUserId();
        $user = Repo::user()->get($userId, true);
        if ($user && !$user->getDisabled()) {
            $context = $request->getContext();
            if ($contextId && (!$context || $context->getId() != $contextId)) {
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($contextId);
            }

            $site = $request->getSite();
            $mail = $this->getMailTemplate('NOTIFICATION');

            if ($context) {
                $mail->setReplyTo($context->getContactEmail(), $context->getContactName());
            } else {
                $mail->setReplyTo($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
            }

            $emailParams = [
                'notificationContents' => $this->getNotificationContents($request, $notification),
                'notificationUrl' => $this->getNotificationUrl($request, $notification),
                'siteTitle' => $context ? $context->getLocalizedName() : $site->getLocalizedTitle(),
            ];

            $notificationManager = new NotificationManager();
            if (array_key_exists($notification->getType(), $notificationManager->getNotificationSettingsMap())) {
                $unsubscribeUrl = $this->getUnsubscribeNotificationUrl($request, $notification);

                $unsubscribeLink = '<br /><a href=\'' . $unsubscribeUrl . '\'>' . __('notification.unsubscribeNotifications') . '</a>';
                $emailParams = array_merge([
                    'unsubscribeLink' => $unsubscribeLink,
                ], $emailParams);
            } else {
                // Clear unsubscribe params that are not yet assigned
                $body = $mail->getBody();
                $body = str_replace('{$unsubscribeLink}', '', $body);

                $mail->setBody($body);
            }

            $mail->assignParams($emailParams);

            $mail->addRecipient($user->getEmail(), $user->getFullName());
            if (is_callable($mailConfigurator)) {
                $mail = $mailConfigurator($mail);
            }
            if (!$mail->send() && $request->getUser()) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
            }
        }
    }

    /**
     * Creates and returns a unique string for the given notification, that will be encoded and validated against.
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function createUnsubscribeUniqueKey($notification)
    {
        $uniqueKey = 'unsubscribe' . '-' . $notification->getContextId() . '-' . $notification->getUserId() . '-' . $notification->getId();

        return $uniqueKey;
    }

    /**
     * Creates and returns an encoded token that will be used to validate an unsubscribe url.
     *
     * @param Notification $notification
     *
     * @return string
     */
    public function createUnsubscribeToken($notification)
    {
        $encodeString = $this->createUnsubscribeUniqueKey($notification);

        $secret = Config::getVar('security', 'api_key_secret', '');
        $jwt = '';
        if ($secret !== '') {
            $jwt = JWT::encode(json_encode($encodeString), $secret, 'HS256');
        }

        return $jwt;
    }

    /**
     * The given notification is validated against the requested token.
     *
     * @param string $token
     * @param Notification $notification
     *
     * @return bool
     */
    public function validateUnsubscribeToken($token, $notification)
    {
        $encodeString = $this->createUnsubscribeUniqueKey($notification);

        $secret = Config::getVar('security', 'api_key_secret', '');
        $jwt = '';
        if ($secret !== '') {
            $jwt = json_decode(JWT::decode($token, $secret, ['HS256']));
        }

        if ($jwt == $encodeString) {
            return true;
        }

        return false;
    }

    /**
     * Returns the unsubscribe url for the given notification.
     *
     * @param PKPRequest $request
     * @param Notification $notification
     *
     * @return string
     */
    public function getUnsubscribeNotificationUrl($request, $notification)
    {
        $application = Application::get();
        $dispatcher = $application->getDispatcher();
        $unsubscribeUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'notification', 'unsubscribe', null, ['validate' => $this->createUnsubscribeToken($notification), 'id' => $notification->getId()]);

        return $unsubscribeUrl;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\PKPNotificationOperationManager', '\PKPNotificationOperationManager');
}
