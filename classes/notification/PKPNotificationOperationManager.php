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
use APP\notification\Notification;
use APP\template\TemplateManager;
use Firebase\JWT\Key;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPJwt as JWT;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use stdClass;

abstract class PKPNotificationOperationManager implements INotificationInfoProvider
{
    //
    // Implement INotificationInfoProvider with default values.
    //
    /**
     * @copydoc INotificationInfoProvider::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, PKPNotification $notification): ?string
    {
        return null;
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, PKPNotification $notification): ?string
    {
        return null;
    }

    /**
     * Provide the notification message as default content.
     *
     * @copydoc INotificationInfoProvider::getNotificationContents()
     */
    public function getNotificationContents(PKPRequest $request, PKPNotification $notification): mixed
    {
        return $this->getNotificationMessage($request, $notification);
    }

    /**
     * @copydoc INotificationInfoProvider::getNotificationTitle()
     */
    public function getNotificationTitle(PKPNotification $notification): string
    {
        return __('notification.notification');
    }

    /**
     * @copydoc INotificationInfoProvider::getStyleClass()
     */
    public function getStyleClass(PKPNotification $notification): string
    {
        return '';
    }

    /**
     * @copydoc INotificationInfoProvider::getIconClass()
     */
    public function getIconClass(PKPNotification $notification): string
    {
        return '';
    }

    /**
     * @copydoc INotificationInfoProvider::isVisibleToAllUsers()
     */
    public function isVisibleToAllUsers(int $notificationType, int $assocType, int $assocId): bool
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
     */
    public function getParamsForCurrentLocale(array $params): array
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
     */
    public function createNotification(PKPRequest $request, ?int $userId = null, ?int $notificationType = null, ?int $contextId = Application::SITE_CONTEXT_ID, ?int $assocType = null, ?int $assocId = null, int $level = Notification::NOTIFICATION_LEVEL_NORMAL, ?array $params = null): ?PKPNotification
    {
        if ($userId && in_array($notificationType, $this->getUserBlockedNotifications($userId, $contextId))) {
            return null;
        }

        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notification = $notificationDao->newDataObject(); /** @var Notification $notification */
        $notification->setUserId((int) $userId);
        $notification->setType((int) $notificationType);
        $notification->setContextId($contextId);
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
     */
    public function createTrivialNotification(int $userId, int $notificationType = PKPNotification::NOTIFICATION_TYPE_SUCCESS, ?array $params = null): PKPNotification
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notification = $notificationDao->newDataObject();
        $notification->setUserId($userId);
        $notification->setContextId(Application::SITE_CONTEXT_ID);
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
     */
    public function deleteTrivialNotifications(array $notifications): void
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
     */
    public function formatToGeneralNotification(PKPRequest $request, array $notifications): array
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
     */
    public function formatToInPlaceNotification(PKPRequest $request, array $notifications): array
    {
        $formattedNotificationsData = null;

        $templateMgr = TemplateManager::getManager($request);
        foreach ($notifications as $notification) {
            $formattedNotificationsData[$notification->getLevel()][$notification->getId()] = $this->formatNotification($request, $notification, 'controllers/notification/inPlaceNotificationContent.tpl');
        }

        return $formattedNotificationsData;
    }

    /**
     * Get set of notifications types user does not want to be notified of.
     */
    protected function getUserBlockedNotifications(int $userId, ?int $contextId): array
    {
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        return $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY, $userId, $contextId);
    }

    /**
     * Get set of notification types user will also be notified by email.
     */
    protected function getUserBlockedEmailedNotifications(int $userId, ?int $contextId): array
    {
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        return $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY, $userId, $contextId);
    }

    /**
     * Get a notification content with a link action.
     */
    protected function fetchLinkActionNotificationContent(LinkAction $linkAction, PKPRequest $request): string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('linkAction', $linkAction);
        return $templateMgr->fetch('controllers/notification/linkActionNotificationContent.tpl');
    }

    /**
     * Return a fully formatted notification for display
     */
    private function formatNotification(PKPRequest $request, PKPNotification $notification, string $notificationTemplate): string
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
     */
    public function createUnsubscribeUniqueKey(PKPNotification $notification): string
    {
        return "unsubscribe-{$notification->getContextId()}-{$notification->getUserId()}-{$notification->getId()}";
    }

    /**
     * Creates and returns an encoded token that will be used to validate an unsubscribe url.
     * Returns an empty string if the API key secret has not been configured.
     */
    public function createUnsubscribeToken(PKPNotification $notification): string
    {
        $secret = Config::getVar('security', 'api_key_secret', '');
        if ($secret === '') {
            return '';
        }

        return  JWT::encode([$this->createUnsubscribeUniqueKey($notification)], $secret, 'HS256');
    }

    /**
     * The given notification is validated against the requested token.
     */
    public function validateUnsubscribeToken(string $token, PKPNotification $notification): bool
    {
        $secret = Config::getVar('security', 'api_key_secret', '');
        if ($secret === '') {
            return false;
        }

        $headers = new stdClass();
        $jwt = ((array)JWT::decode($token, new Key($secret, 'HS256'), $headers))[0]; /** @var string $jwt */
        return $jwt === $this->createUnsubscribeUniqueKey($notification);
    }

    /**
     * Returns the unsubscribe url for the given notification.
     */
    public function getUnsubscribeNotificationUrl(PKPRequest $request, PKPNotification $notification, ?Context $context = null): string
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
