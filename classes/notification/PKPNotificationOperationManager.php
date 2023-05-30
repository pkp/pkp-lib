<?php

/**
 * @file classes/notification/PKPNotificationOperationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationOperationManager
 *
 * @ingroup notification
 *
 * @see NotificationDAO
 * @see Notification
 *
 * @brief Base class for notification manager that implements
 * basic notification operations and default notifications info.
 * Subclasses can implement specific information.
 */

namespace PKP\notification;

use APP\core\Application;
use APP\core\Request;
use APP\notification\Notification;
use APP\template\TemplateManager;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;

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
     * @param ?PKPRequest $request
     * @param int $userId (optional)
     * @param int $notificationType
     * @param int $contextId
     * @param int $assocType
     * @param int $assocId
     * @param int $level
     * @param array $params
     *
     * @return ?Notification
     */
    public function createNotification($request, $userId = null, $notificationType = null, $contextId = null, $assocType = null, $assocId = null, $level = Notification::NOTIFICATION_LEVEL_NORMAL, $params = null)
    {
        $blockedNotifications = $this->getUserBlockedNotifications($userId, $contextId);

        if (in_array($notificationType, $blockedNotifications)) {
            return null;
        }
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notification = $notificationDao->newDataObject(); /** @var Notification $notification */
        $notification->setUserId((int) $userId);
        $notification->setType((int) $notificationType);
        $notification->setContextId((int) $contextId);
        $notification->setAssocType((int) $assocType);
        $notification->setAssocId((int) $assocId);
        $notification->setLevel((int) $level);

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
        $notification->setContextId(PKPApplication::CONTEXT_ID_NONE);
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
     * General notification data formatting.
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

    /**
     * Return a fully formatted notification for display
     *
     * @param PKPRequest $request
     * @param object $notification Notification
     *
     * @return string
     */
    private function formatNotification($request, $notification, $notificationTemplate)
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
     * @param null|mixed $context
     *
     * @return string
     */
    public function getUnsubscribeNotificationUrl($request, $notification, $context = null)
    {
        $application = Application::get();
        $dispatcher = $application->getDispatcher();
        $contextPath = null;
        if ($context) {
            if ($context->getId() !== $notification->getContextId()) {
                throw new InvalidArgumentException('Trying to build notification unsubscribe URL with the wrong context');
            }
            $contextPath = $context->getData('urlPath');
        }

        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $contextPath,
            'notification',
            'unsubscribe',
            null,
            ['validate' => $this->createUnsubscribeToken($notification), 'id' => $notification->getId()]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\PKPNotificationOperationManager', '\PKPNotificationOperationManager');
}
