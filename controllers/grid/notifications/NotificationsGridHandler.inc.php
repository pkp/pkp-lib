<?php

/**
 * @file controllers/grid/notifications/NotificationsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationsGridHandler
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of notifications for a given user
 */

// Other classes associated with this grid
import('lib.pkp.controllers.grid.notifications.NotificationsGridCellProvider');

use APP\notification\NotificationManager;
use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;

use PKP\linkAction\request\NullAction;

class NotificationsGridHandler extends GridHandler
{
    /** @var array $_selectedNotificationIds Set of selected IDs */
    public $_selectedNotificationIds;


    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->_selectedNotificationIds = (array) $request->getUserVar('selectedNotificationIds');

        $cellProvider = new NotificationsGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'task',
                $this->getNotificationsColumnTitle(),
                null,
                null,
                $cellProvider,
                ['anyhtml' => true,
                    'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT]
            )
        );

        // Set the no items row text
        $this->setEmptyRowText('grid.noItems');

        $this->addAction(
            new LinkAction(
                'markNew',
                new NullAction(),
                __('grid.action.markNew'),
                'edit' // FIXME: Icon
            ),
            GridHandler::GRID_ACTION_POSITION_BELOW
        );
        $this->addAction(
            new LinkAction(
                'markRead',
                new NullAction(),
                __('grid.action.markRead'),
                'edit' // FIXME: Icon
            ),
            GridHandler::GRID_ACTION_POSITION_BELOW
        );

        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'deleteNotification',
                new NullAction(),
                __('grid.action.delete'),
                'delete'
            ),
            GridHandler::GRID_ACTION_POSITION_BELOW
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::getJSHandler()
     */
    public function getJSHandler()
    {
        return '$.pkp.controllers.grid.notifications.NotificationsGridHandler';
    }

    /**
     * @see GridHandler::setUrls()
     */
    public function setUrls($request, $extraUrls = [])
    {
        $router = $request->getRouter();
        parent::setUrls(
            $request,
            array_merge(
                $extraUrls,
                [
                    'markNewUrl' => $router->url($request, null, null, 'markNew', null, $this->getRequestArgs()),
                    'markReadUrl' => $router->url($request, null, null, 'markRead', null, $this->getRequestArgs()),
                    'deleteUrl' => $router->url($request, null, null, 'deleteNotifications', null, $this->getRequestArgs()),
                ]
            )
        );
    }

    /**
     * Get the list of "publish data changed" events.
     * Used to update the site context switcher upon create/delete.
     *
     * @return array
     */
    public function getPublishChangeEvents()
    {
        return ['updateUnreadNotificationsCount'];
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature(), new PagingFeature()];
    }

    /**
     * @copydoc GridHandler::getSelectName()
     */
    public function getSelectName()
    {
        return 'selectedNotifications';
    }

    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        return in_array($gridDataElement->getId(), $this->_selectedNotificationIds);
    }


    //
    // Protected methods.
    //
    /**
     * Get the notifications column title.
     *
     * @return string Locale key.
     */
    protected function getNotificationsColumnTitle()
    {
        return 'common.tasks';
    }


    //
    // Public methods
    //
    /**
     * Mark notifications unread
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function markNew($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $user = $request->getUser();

        $selectedElements = (array) $request->getUserVar('selectedElements');
        foreach ($selectedElements as $notificationId) {
            if ($notificationDao->getById($notificationId, $user->getId())) {
                $notificationDao->setDateRead($notificationId, null);
            }
        }

        $json = \PKP\db\DAO::getDataChangedEvent(null, null, $selectedElements);
        $json->setGlobalEvent('update:unread-tasks-count', ['count' => $this->getUnreadNotificationsCount($user)]);
        return $json;
    }

    /**
     * Mark notifications unread
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function markRead($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $user = $request->getUser();

        $selectedElements = (array) $request->getUserVar('selectedElements');
        foreach ($selectedElements as $notificationId) {
            if ($notification = $notificationDao->getById($notificationId, $user->getId())) {
                $notificationDao->setDateRead($notificationId, Core::getCurrentDate());
            }
        }
        if ($request->getUserVar('redirect')) {
            // In this case, the user has clicked on a notification
            // and wants to view it. Mark it read first and redirect
            $notificationMgr = new NotificationManager();
            return $request->redirectUrlJson($notificationMgr->getNotificationUrl($request, $notification));
        } else {
            // The notification has been marked read explicitly.
            // Update its status in the grid.
            $json = \PKP\db\DAO::getDataChangedEvent(null, null, $selectedElements);
            $json->setGlobalEvent('update:unread-tasks-count', ['count' => $this->getUnreadNotificationsCount($user)]);
            return $json;
        }
    }

    /**
     * Delete notifications
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteNotifications($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $user = $request->getUser();

        $selectedElements = (array) $request->getUserVar('selectedElements');
        foreach ($selectedElements as $notificationId) {
            if ($notification = $notificationDao->getById($notificationId, $user->getId())) {
                $notificationDao->deleteObject($notification);
            }
        }
        $json = \PKP\db\DAO::getDataChangedEvent(null, null, $selectedElements);
        $json->setGlobalEvent('update:unread-tasks-count', ['count' => $this->getUnreadNotificationsCount($user)]);
        return $json;
    }

    /**
     * Get unread notifications count
     *
     * @return JSONMessage JSON object
     */
    public function getUnreadNotificationsCount($user)
    {
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        return (int) $notificationDao->getNotificationCount(false, $user->getId(), null, Notification::NOTIFICATION_LEVEL_TASK);
    }
}
