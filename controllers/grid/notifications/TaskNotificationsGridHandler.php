<?php

/**
 * @file controllers/grid/notifications/TaskNotificationsGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskNotificationsGridHandler
 *
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of task notifications for a given user
 */

namespace PKP\controllers\grid\notifications;

use PKP\notification\Notification;

class TaskNotificationsGridHandler extends NotificationsGridHandler
{
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration.
        $this->setTitle('common.tasks');
    }

    /**
     * @see GridHandler::loadData()
     *
     * @return array Grid data.
     */
    protected function loadData($request, $filter)
    {
        $user = $request->getUser();

        // Get all level task notifications.
        $notifications = Notification::withUserId($user->getId())
            ->withLevel(Notification::NOTIFICATION_LEVEL_TASK)
            ->get();

        // Checkbox selection requires the array keys match the notification id
        $notificationsForRow = [];
        foreach ($notifications as $notification) {
            $notificationsForRow[$notification->id] = $notification;
        }
        return $notificationsForRow;
    }
}
