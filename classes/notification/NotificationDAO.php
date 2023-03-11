<?php

/**
 * @file classes/notification/NotificationDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationDAO
 * @ingroup notification
 *
 * @see Notification
 *
 * @brief Operations for retrieving and modifying Notification objects.
 */

namespace PKP\notification;

use APP\notification\Notification;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAOResultFactory;

use PKP\plugins\Hook;

class NotificationDAO extends \PKP\db\DAO
{
    /**
     * Retrieve Notification by notification id
     */
    public function getById(int $notificationId, ?int $userId = null): ?Notification
    {
        $params = [(int) $notificationId];
        if ($userId) {
            $params[] = (int) $userId;
        }

        $result = $this->retrieve(
            'SELECT	*
			FROM	notifications
			WHERE	notification_id = ?
			' . ($userId ? ' AND user_id = ?' : ''),
            $params
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve Notifications by user id
     * Note that this method will not return fully-fledged notification objects.  Use
     *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
     *
     * @return object DAOResultFactory containing matching Notification objects
     */
    public function getByUserId(?int $userId, int $level = Notification::NOTIFICATION_LEVEL_NORMAL, ?int $type = null, ?int $contextId = null): DAOResultFactory
    {
        $result = DB::table('notifications')
            ->where('user_id', '=', $userId)
            ->where('level', '=', $level)
            ->when($type !== null, fn ($query) => $query->where('type', '=', $type))
            ->when($contextId === PKPApplication::CONTEXT_SITE, fn ($query) => $query->whereNull('context_id'))
            ->when($contextId, fn ($query) => $query->where('context_id', '=', $contextId))
            ->orderBy('date_created', 'desc')
            ->get();
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve Notifications by assoc.
     * Note that this method will not return fully-fledged notification objects.  Use
     *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     */
    public function getByAssoc(int $assocType, int $assocId, ?int $userId = null, ?int $type = null, ?int $contextId = null): DAOResultFactory
    {
        $params = [$assocType, $assocId];
        if ($userId) {
            $params[] = $userId;
        }
        if ($contextId) {
            $params[] = $contextId;
        }
        if ($type) {
            $params[] = $type;
        }

        $result = $this->retrieveRange(
            'SELECT * FROM notifications WHERE assoc_type = ? AND assoc_id = ?' .
            ($userId ? ' AND user_id = ?' : '') .
            ($contextId ? ' AND context_id = ?' : '') .
            ($type ? ' AND type = ?' : '') .
            ' ORDER BY date_created DESC',
            $params
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Set the date read for a notification
     */
    public function setDateRead(int $notificationId, string $dateRead): string
    {
        $this->update(
            sprintf(
                'UPDATE notifications
				SET date_read = %s
				WHERE notification_id = ?',
                $this->datetimeToDB($dateRead)
            ),
            [$notificationId]
        );

        return $dateRead;
    }

    /**
     * Instantiate and return a new data object.
     */
    public function newDataObject(): Notification
    {
        return new Notification();
    }

    /**
     * Inserts a new notification into notifications table
     */
    public function insertObject(Notification $notification): int
    {
        $this->update(
            sprintf(
                'INSERT INTO notifications
					(user_id, level, date_created, context_id, type, assoc_type, assoc_id)
				VALUES
					(?, ?, %s, ?, ?, ?, ?)',
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [
                $notification->getUserId() ? (int) $notification->getUserId() : null,
                (int) $notification->getLevel(),
                $notification->getContextId() ? (int) $notification->getContextId() : null,
                (int) $notification->getType(),
                (int) $notification->getAssocType(),
                (int) $notification->getAssocId()
            ]
        );
        $notification->setId($this->getInsertId());

        return $notification->getId();
    }

    /**
     * Inserts or update a notification into notifications table.
     *
     * @param int $userId (optional)
     * @param int $contextId (optional)
     *
     * @return mixed Notification or null
     */
    public function build(int $contextId, int $level, int $type, int $assocType, int $assocId, ?int $userId = null): Notification
    {
        DB::table('notifications')
            ->when(
                $contextId === PKPApplication::SITE_CONTEXT,
                fn ($query) => $query->whereNull('context_id'),
                fn ($query) => $query->where('context_id', '=', $contextId)
            )
            ->where('level', '=', $level)
            ->where('assoc_type', '=', $assocType)
            ->where('assoc_id', '=', $assocId)
            ->when($userId !== null, fn ($query, $userId) => $query->where('user_id', '=', $userId))
            ->delete();

        $notification = $this->newDataObject();
        $notification->setContextId($contextId);
        $notification->setLevel($level);
        $notification->setType($type);
        $notification->setAssocType($assocType);
        $notification->setAssocId($assocId);
        $notification->setUserId($userId);

        $notificationId = $this->insertObject($notification);
        $notification->setId($notificationId);
        return $notification;
    }

    /**
     * Delete Notification by notification id
     *
     * @param int $userId
     *
     */
    public function deleteById(int $notificationId, ?int $userId = null)
    {
        $query = DB::table('notifications')
            ->where('notification_id', '=', $notificationId)
            ->when($userId, fn ($query, $userId) => $query->where('user_id', '=', $userId))
            ->delete();
    }

    /**
     * Delete Notification
     */
    public function deleteObject(Notification $notification)
    {
        $this->deleteById($notification->getId());
    }

    /**
     * Delete notification(s) by association
     */
    public function deleteByAssoc(int $assocType, int $assocId, ?int $userId = null, ?int $type = null, ?int $contextId = null)
    {
        $notificationsFactory = $this->getByAssoc($assocType, $assocId, $userId, $type, $contextId);
        while ($notification = $notificationsFactory->next()) {
            $this->deleteObject($notification);
        }
    }

    /**
     * Get the number of unread messages for a user
     *
     * @param bool $read Whether to check for read (true) or unread (false) notifications
     */
    public function getNotificationCount(bool $read = true, ?int $userId = null, ?int $contextId = null, ?int $level = Notification::NOTIFICATION_LEVEL_NORMAL): int
    {
        $params = [(int) $userId, (int) $level];
        if ($contextId) {
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve(
            'SELECT count(*) AS row_count FROM notifications WHERE user_id = ? AND date_read IS' . ($read ? ' NOT' : '') . ' NULL AND level = ?'
            . (isset($contextId) ? ' AND context_id = ?' : ''),
            $params
        );

        $row = (array) $result->current();
        return $row ? $row['row_count'] : 0;
    }

    /**
     * Transfer the notifications for a user.
     */
    public function transferNotifications(int $oldUserId, int $newUserId)
    {
        $this->update(
            'UPDATE notifications SET user_id = ? WHERE user_id = ?',
            [$newUserId, $oldUserId]
        );
    }

    /**
     * Creates and returns an notification object from a row
     */
    public function _fromRow(array $row): Notification
    {
        $notification = $this->newDataObject();
        $notification->setId($row['notification_id']);
        $notification->setUserId($row['user_id']);
        $notification->setLevel($row['level']);
        $notification->setDateCreated($this->datetimeFromDB($row['date_created']));
        $notification->setDateRead($this->datetimeFromDB($row['date_read']));
        $notification->setContextId($row['context_id']);
        $notification->setType($row['type']);
        $notification->setAssocType($row['assoc_type']);
        $notification->setAssocId($row['assoc_id']);

        Hook::call('NotificationDAO::_fromRow', [&$notification, &$row]);

        return $notification;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationDAO', '\NotificationDAO');
}
