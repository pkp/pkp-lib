<?php

/**
 * @file pages/notification/NotificationHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing notifications.
 */

use APP\handler\Handler;
use APP\notification\Notification;

use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\notification\form\PKPNotificationsUnsubscribeForm;

class NotificationHandler extends Handler
{
    /**
     * Return formatted notification data using Json.
     *
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function fetchNotification($args, $request)
    {
        $this->setupTemplate($request);
        $user = $request->getUser();
        $userId = $user ? $user->getId() : null;
        $context = $request->getContext();
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
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
            $notifications = $notificationDao->getByUserId($userId, Notification::NOTIFICATION_LEVEL_TRIVIAL);
            $notifications = $notifications->toArray();
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
     * @param $args array
     * @param $request Request
     */
    public function unsubscribe($args, $request)
    {
        $validationToken = $request->getUserVar('validate');
        $notificationId = $request->getUserVar('id');

        $notification = $this->_validateUnsubscribeRequest($validationToken, $notificationId);

        // Show the form on a get request
        if (!$request->isPost()) {
            $validationToken = $request->getUserVar('validate');
            $notificationId = $request->getUserVar('id');

            $notification = $this->_validateUnsubscribeRequest($validationToken, $notificationId);


            $notificationsUnsubscribeForm = new PKPNotificationsUnsubscribeForm($notification, $validationToken);
            $notificationsUnsubscribeForm->display($request);
            return;
        }

        // Otherwise process the result
        $this->setupTemplate($request);


        $notificationsUnsubscribeForm = new PKPNotificationsUnsubscribeForm($notification, $validationToken);

        $notificationsUnsubscribeForm->readInputData();

        $templateMgr = TemplateManager::getManager($request);

        $unsubscribeResult = false;
        if ($notificationsUnsubscribeForm->validate()) {
            $notificationsUnsubscribeForm->execute();

            $unsubscribeResult = true;
        }

        $userId = $notification->getUserId();
        $contextId = $notification->getContextId();

        $contextDao = Application::getContextDAO();
        $userDao = DAORegistry::getDAO('UserDAO');

        $user = $userDao->getById($userId);
        $context = $contextDao->getById($contextId);

        $templateMgr->assign([
            'contextName' => $context->getLocalizedName(),
            'userEmail' => $user->getEmail(),
            'unsubscribeResult' => $unsubscribeResult,
        ]);

        $templateMgr->display('notification/unsubscribeNotificationsResult.tpl');
    }

    /**
     * Performs all unsubscribe validation token validations
     *
     * @param $validationToken string
     * @param $notificationId int
     *
     * @return Notification
     */
    public function _validateUnsubscribeRequest($validationToken, $notificationId)
    {
        if ($validationToken == null || $notificationId == null) {
            $this->getDispatcher()->handle404();
        }

        /** @var NotificationDAO $notificationDao */
        $notificationDao = DAORegistry::getDAO('NotificationDAO');
        /** @var Notification $notification */
        $notification = $notificationDao->getById($notificationId);

        if (!isset($notification) || $notification->getId() == null) {
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
     *
     * @param $notificationOptions Array
     * @param $contextId int
     * @param $userId int
     *
     * @return Array
     */
    public function _getNotificationsByOptions($notificationOptions, $contextId, $userId = null)
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationsArray = [];
        $notificationMgr = new NotificationManager();

        foreach ($notificationOptions as $level => $levelOptions) {
            if ($levelOptions) {
                foreach ($levelOptions as $type => $typeOptions) {
                    if ($typeOptions) {
                        $notificationMgr->isVisibleToAllUsers($type, $typeOptions['assocType'], $typeOptions['assocId']) ? $workingUserId = null : $workingUserId = $userId;
                        $notificationsResultFactory = $notificationDao->getByAssoc($typeOptions['assocType'], $typeOptions['assocId'], $workingUserId, $type, $contextId);
                        $notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
                    } else {
                        if ($userId) {
                            $notificationsResultFactory = $notificationDao->getByUserId($userId, $level, $type, $contextId);
                            $notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
                        }
                    }
                }
            } else {
                if ($userId) {
                    $notificationsResultFactory = $notificationDao->getByUserId($userId, $level, null, $contextId);
                    $notificationsArray = $this->_addNotificationsToArray($notificationsResultFactory, $notificationsArray);
                }
            }
            $notificationsResultFactory = null;
        }

        return $notificationsArray;
    }

    /**
     * Add notifications from a result factory to an array of
     * existing notifications.
     *
     * @param $resultFactory DAOResultFactory
     * @param $notificationArray Array
     */
    public function _addNotificationsToArray($resultFactory, $notificationArray)
    {
        return array_merge($notificationArray, $resultFactory->toArray());
    }

    /**
     * Override setupTemplate() so we can load other locale components.
     *
     * @copydoc PKPHandler::setupTemplate()
     */
    public function setupTemplate($request)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_SUBMISSION);
        parent::setupTemplate($request);
    }
}
