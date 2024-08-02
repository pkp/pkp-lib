<?php

/**
 * @file pages/notification/NotificationHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 *
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing notifications.
 */

namespace PKP\pages\notification;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\notification\form\PKPNotificationsUnsubscribeForm;
use PKP\notification\Notification;

class NotificationHandler extends Handler
{
    /**
     * Return formatted notification data using Json.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchNotification($args, $request)
    {
        $this->setupTemplate($request);
        $user = $request->getUser();
        $userId = $user ? $user->getId() : null;
        $context = $request->getContext();
        $notifications = [];

        // Get the notification options from request.
        $notificationOptions = $request->getUserVar('requestOptions');

        if (!$user) {
            $notifications = [];
        } elseif (is_array($notificationOptions)) {
            // Retrieve the notifications.
            $notifications = $this->_getNotificationsByOptions($notificationOptions, $context->getId(), $userId);
        } else {
            // No options, get only TRIVIAL notifications.
            $notifications = Notification::withUserId($userId)
                ->withLevel(Notification::NOTIFICATION_LEVEL_TRIVIAL)
                ->get()
                ->all();
        }

        $json = new JSONMessage();

        if (is_array($notifications) && !empty($notifications)) {
            $formattedNotificationsData = [];
            $notificationManager = new NotificationManager();

            // Format in place notifications.
            $formattedNotificationsData['inPlace'] = $notificationManager->formatToInPlaceNotification($request, $notifications);

            // Format general notifications.
            $formattedNotificationsData['general'] = $notificationManager->formatToGeneralNotification($request, $notifications);

            // Delete trivial notifications from database.
            $notificationManager->deleteTrivialNotifications($notifications);

            $json->setContent($formattedNotificationsData);
        }

        return $json;
    }

    /**
     * Notification Unsubscribe handler
     *
     * @param array $args
     * @param Request $request
     */
    public function unsubscribe($args, $request)
    {
        $validationToken = $request->getUserVar('validate');
        $notificationId = $request->getUserVar('id');

        $notification = $this->_validateUnsubscribeRequest($validationToken, $notificationId);
        $notificationsUnsubscribeForm = new PKPNotificationsUnsubscribeForm($notification, $validationToken);

        // Show the form on a get request
        if (!$request->isPost()) {
            $notificationsUnsubscribeForm->display($request);
            return;
        }

        // Otherwise process the result
        $this->setupTemplate($request);

        $notificationsUnsubscribeForm->readInputData();

        $templateMgr = TemplateManager::getManager($request);

        $unsubscribeResult = false;
        if ($notificationsUnsubscribeForm->validate()) {
            $notificationsUnsubscribeForm->execute();

            $unsubscribeResult = true;
        }

        $user = Repo::user()->get($notification->userId, true);

        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($notification->contextId);

        $templateMgr->assign([
            'contextName' => $context?->getLocalizedName(),
            'userEmail' => $user?->getEmail(),
            'unsubscribeResult' => $unsubscribeResult,
        ]);

        $templateMgr->display('notification/unsubscribeNotificationsResult.tpl');
    }

    /**
     * Performs all unsubscribe validation token validations
     *
     * @param string $validationToken
     * @param int $notificationId
     *
     * @return Notification
     */
    public function _validateUnsubscribeRequest($validationToken, $notificationId)
    {
        if ($validationToken == null || $notificationId == null) {
            $this->getDispatcher()->handle404();
        }

        /** @var Notification $notification */
        $notification = Notification::find($notificationId);

        if (!isset($notification) || $notification->id == null) {
            $this->getDispatcher()->handle404();
        }

        $notificationManager = new NotificationManager();

        if (!$notificationManager->validateUnsubscribeToken($validationToken, $notification)) {
            $this->getDispatcher()->handle404();
        }

        return $notification;
    }

    /**
     * Get the notifications using options.
     */
    public function _getNotificationsByOptions(array $notificationOptions, int $contextId, ?int $userId = null): array
    {
        $notificationsArray = [];
        $notificationMgr = new NotificationManager();
        foreach ($notificationOptions as $level => $levelOptions) {
            if ($levelOptions) {
                foreach ($levelOptions as $type => $typeOptions) {
                    if ($typeOptions) {
                        $notificationMgr->isVisibleToAllUsers($type, $typeOptions['assocType'], $typeOptions['assocId']) ? $workingUserId = null : $workingUserId = $userId;
                        $notifications = Notification::withAssoc($typeOptions['assocType'], $typeOptions['assocId'])
                            ->withUserId($workingUserId)
                            ->withType($type)
                            ->withContextId($contextId)
                            ->get();
                        $notificationsArray = array_merge($notificationsArray, $notifications->all());
                    } else {
                        if ($userId) {
                            $notifications = Notification::withUserId($userId)
                                ->withLevel($level)
                                ->withType($type)
                                ->withContextId($contextId)
                                ->get();
                            $notificationsArray = array_merge($notificationsArray, $notifications->all());
                        }
                    }
                }
            } else {
                if ($userId) {
                    $notifications = Notification::withUserId($userId)
                        ->withLevel($level)
                        ->withContextId($contextId)
                        ->get();
                    $notificationsArray = array_merge($notificationsArray, $notifications->all());
                }
            }
        }

        return $notificationsArray;
    }
}
