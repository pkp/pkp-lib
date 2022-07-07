<?php

/**
 * @file classes/notification/NotificationDAO.inc.php
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
use PKP\db\DAORegistry;

use PKP\db\DAOResultFactory;

use PKP\plugins\HookRegistry;

class NotificationDAO extends \PKP\db\DAO
{
    /**
     * Retrieve Notification by notification id
     *
     * @param int $notificationId
     * @param int $userId optional
     *
     * @return object Notification
     */
    public function getById($notificationId, $userId = null)
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
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve Notifications by user id
     * Note that this method will not return fully-fledged notification objects.  Use
     *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
     *
     * @param int $userId
     * @param int $level
     * @param int $type
     * @param int $contextId
     *
     * @return object DAOResultFactory containing matching Notification objects
     */
    public function getByUserId($userId, $level = Notification::NOTIFICATION_LEVEL_NORMAL, $type = null, $contextId = null)
    {
        $result = DB::table('notifications')
            ->where('user_id', '=', (int) $userId)
            ->where('level', '=', (int) $level)
            ->orderBy('date_created', 'desc')
            ->get();
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve Notifications by assoc.
     * Note that this method will not return fully-fledged notification objects.  Use
     *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId
     * @param int $userId User ID (optional)
     * @param int $type
     * @param int $contextId Context (journal/press/etc.) ID (optional)
     *
     * @return object DAOResultFactory containing matching Notification objects
     */
    public function getByAssoc($assocType, $assocId, $userId = null, $type = null, $contextId = null)
    {
        $params = [(int) $assocType, (int) $assocId];
        if ($userId) {
            $params[] = (int) $userId;
        }
        if ($contextId) {
            $params[] = (int) $contextId;
        }
        if ($type) {
            $params[] = (int) $type;
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
     * Retrieve Notifications by notification id
     *
     * @param int $notificationId
     * @param date $dateRead
     *
     * @return bool
     */
    public function setDateRead($notificationId, $dateRead)
    {
        $this->update(
            sprintf(
                'UPDATE notifications
				SET date_read = %s
				WHERE notification_id = ?',
                $this->datetimeToDB($dateRead)
            ),
            [(int) $notificationId]
        );

        return $dateRead;
    }

    /**
     * Instantiate and return a new data object.
     *
     * @return Notification
     */
    public function newDataObject()
    {
        return new Notification();
    }

    /**
     * Inserts a new notification into notifications table
     *
     * @param object $notification
     *
     * @return int Notification Id
     */
    public function insertObject($notification)
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
                (int) $notification->getUserId(),
                (int) $notification->getLevel(),
                (int) $notification->getContextId(),
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
     * @param int $level
     * @param int $type
     * @param int $assocType
     * @param int $assocId
     * @param int $userId (optional)
     * @param int $contextId (optional)
     *
     * @return mixed Notification or null
     */
    public function build($contextId, $level, $type, $assocType, $assocId, $userId = null)
    {
        $params = [
            (int) $contextId,
            (int) $level,
            (int) $type,
            (int) $assocType,
            (int) $assocId
        ];

        if ($userId) {
            $params[] = (int) $userId;
        }

        $this->update(
            'DELETE FROM notifications
			WHERE context_id = ? AND level = ? AND type = ? AND assoc_type = ? AND assoc_id = ?'
            . ($userId ? ' AND user_id = ?' : ''),
            $params
        );

        $notification = $this->newDataObject();
        $notification->setContextId($contextId);
        $notification->setLevel($level);
        $notification->setType($type);
        $notification->setAssocType($assocType);
        $notification->setAssocId($assocId);
        $notification->setUserId($userId);

        $notificationId = $this->insertObject($notification);
        if ($notificationId) {
            $notification->setId($notificationId);
            return $notification;
        } else {
            return null;
        }
    }

    /**
     * Delete Notification by notification id
     *
     * @param int $notificationId
     * @param int $userId
     *
     */
    public function deleteById($notificationId, $userId = null)
    {
        try {
            DB::beginTransaction();

            $notificationSettingsDao = DAORegistry::getDAO('NotificationSettingsDAO'); /** @var NotificationSettingsDAO $notificationSettingsDaoDao */
            $notificationSettingsDao->deleteSettingsByNotificationId($notificationId);

            $query = DB::table('notifications')
                ->where('notification_id', '=', $notificationId);
            
            if ($userId) {
                $query->where('user_id', '=', $userId);
            }

            $query->delete();

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    /**
     * Delete Notification
     *
     * @param Notification $notification
     *
     * @return bool
     */
    public function deleteObject($notification)
    {
        return $this->deleteById($notification->getId());
    }

    /**
     * Delete notification(s) by association
     *
     * @param int $assocType
     * @param int $assocId
     * @param int $userId optional
     * @param int $type optional
     * @param int $contextId optional
     *
     * @return bool
     */
    public function deleteByAssoc($assocType, $assocId, $userId = null, $type = null, $contextId = null)
    {
        $notificationsFactory = $this->getByAssoc($assocType, $assocId, $userId, $type, $contextId);
        while ($notification = $notificationsFactory->next()) {
            $this->deleteObject($notification);
        }
    }

    /**
     * Get the ID of the last inserted notification
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('notifications', 'notification_id');
    }

    /**
     * Get the number of unread messages for a user
     *
     * @param bool $read Whether to check for read (true) or unread (false) notifications
     * @param int $contextId
     * @param int $userId
     * @param int $level
     *
     * @return int
     */
    public function getNotificationCount($read = true, $userId = null, $contextId = null, $level = Notification::NOTIFICATION_LEVEL_NORMAL)
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
     *
     * @param int $oldUserId
     * @param int $newUserId
     */
    public function transferNotifications($oldUserId, $newUserId)
    {
        $this->update(
            'UPDATE notifications SET user_id = ? WHERE user_id = ?',
            [(int) $newUserId, (int) $oldUserId]
        );
    }

    /**
     * Creates and returns an notification object from a row
     *
     * @param array $row
     *
     * @return Notification object
     */
    public function _fromRow($row)
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

        HookRegistry::call('NotificationDAO::_fromRow', [&$notification, &$row]);

        return $notification;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationDAO', '\NotificationDAO');
}
