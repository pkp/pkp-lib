<?php

/**
 * @file classes/log/EmailLogDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailLogDAO
 *
 * @ingroup log
 *
 * @see EmailLogEntry, Log
 *
 * @brief Class for inserting/accessing email log entries.
 */

namespace PKP\log;

use APP\facades\Repo;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PKP\db\DAOResultFactory;
use PKP\plugins\Hook;

class EmailLogDAO extends \PKP\db\DAO
{
    /**
     * The maximum length for the email subject.
     *
     * This value should match the length of the `subject` column in the `email_log` table, defined in LogMigration.php.
     */
    private const MAX_SUBJECT_LENGTH = 255;

    /**
     * Retrieve a log entry by ID.
     *
     * @param int $logId
     * @param int $assocType optional
     * @param int $assocId optional
     *
     * @return EmailLogEntry
     */
    public function getById($logId, $assocType = null, $assocId = null)
    {
        $params = [(int) $logId];
        if (isset($assocType)) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }

        $result = $this->retrieve(
            'SELECT * FROM email_log WHERE log_id = ?' .
            (isset($assocType) ? ' AND assoc_type = ? AND assoc_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->build((array) $row) : null;
    }

    /**
     * Retrieve a log entry by event type.
     *
     * @param int $assocType
     * @param int $assocId
     * @param int $eventType
     * @param int $userId optional
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<EmailLogEntry>
     */
    public function _getByEventType($assocType, $assocId, $eventType, $userId = null, $rangeInfo = null)
    {
        $q = DB::table('email_log', 'e')
            ->when(
                $userId,
                fn (Builder $q) => $q->join(
                    'email_log_users AS u',
                    fn (JoinClause $j) => $j->on('u.email_log_id', '=', 'e.log_id')
                        ->where('u.user_id', $userId)
                )
            )
            ->where('e.assoc_type', '=', (int) $assocType)
            ->where('e.assoc_id', '=', (int) $assocId)
            ->where('e.event_type', '=', (int) $eventType)
            ->orderBy('e.log_id')
            ->select('e.*');
        $result = $this->retrieveRange($q, [], $rangeInfo);
        return new DAOResultFactory($result, $this, 'build', [], $q, [], $rangeInfo); // Counted in submissionEmails.tpl
    }

    /**
     * Retrieve all log entries for an object matching the specified association.
     *
     * @param int $assocType
     * @param int $assocId
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<EmailLogEntry> containing matching EmailLogEntry ordered by sequence
     */
    public function getByAssoc($assocType = null, $assocId = null, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT	*
			FROM	email_log
			WHERE	assoc_type = ?
				AND assoc_id = ?
			ORDER BY log_id DESC',
            [(int) $assocType, (int) $assocId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, 'build');
    }

    /**
     * Internal function to return an EmailLogEntry object from a row.
     *
     * @param array $row
     *
     * @return EmailLogEntry
     */
    public function build($row)
    {
        $entry = $this->newDataObject();
        $entry->setId($row['log_id']);
        $entry->setAssocType($row['assoc_type']);
        $entry->setAssocId($row['assoc_id']);
        $entry->setSenderId($row['sender_id']);
        $entry->setDateSent($this->datetimeFromDB($row['date_sent']));
        $entry->setEventType($row['event_type']);
        $entry->setFrom($row['from_address']);
        $entry->setRecipients($row['recipients']);
        $entry->setCcs($row['cc_recipients']);
        $entry->setBccs($row['bcc_recipients']);
        $entry->setSubject($row['subject']);
        $entry->setBody($row['body']);

        Hook::call('EmailLogDAO::build', [&$entry, &$row]);

        return $entry;
    }

    /**
     * Insert a new log entry.
     *
     * @param EmailLogEntry $entry
     */
    public function insertObject($entry)
    {
        $this->update(
            sprintf(
                'INSERT INTO email_log
				(sender_id, date_sent, event_type, assoc_type, assoc_id, from_address, recipients, cc_recipients, bcc_recipients, subject, body)
				VALUES
				(?, %s, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $this->datetimeToDB($entry->getDateSent())
            ),
            [
                $entry->getSenderId(),
                $entry->getEventType(),
                $entry->getAssocType(),
                $entry->getAssocId(),
                $entry->getFrom(),
                $entry->getRecipients(),
                $entry->getCcs(),
                $entry->getBccs(),
                Str::limit($entry->getSubject(), self::MAX_SUBJECT_LENGTH - 3), // Subtract 3 to compensate for the '...' that gets added to the end of the string.
                $entry->getBody()
            ]
        );

        $entry->setId($this->getInsertId());
        $this->_insertLogUserIds($entry);

        return $entry->getId();
    }

    /**
     * Delete a single log entry for an object.
     *
     * @param int $logId
     * @param int $assocType optional
     * @param int $assocId optional
     */
    public function deleteObject($logId, $assocType = null, $assocId = null)
    {
        $params = [(int) $logId];
        if (isset($assocType)) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }
        return $this->update(
            'DELETE FROM email_log WHERE log_id = ?' .
            (isset($assocType) ? ' AND assoc_type = ? AND assoc_id = ?' : ''),
            $params
        );
    }

    /**
     * Delete all log entries for an object.
     *
     * @param int $assocType
     *
     * @praam $assocId int
     */
    public function deleteByAssoc($assocType, $assocId)
    {
        return $this->update(
            'DELETE FROM email_log WHERE assoc_type = ? AND assoc_id = ?',
            [(int) $assocType, (int) $assocId]
        );
    }

    /**
     * Transfer all log and log users entries to another user.
     *
     * @param int $oldUserId
     * @param int $newUserId
     */
    public function changeUser($oldUserId, $newUserId)
    {
        return [
            $this->update(
                'UPDATE email_log SET sender_id = ? WHERE sender_id = ?',
                [(int) $newUserId, (int) $oldUserId]
            ),
            $this->update(
                'UPDATE email_log_users
                SET user_id = ?
                WHERE user_id = ? AND email_log_id NOT IN (SELECT t1.email_log_id
                    FROM (SELECT email_log_id FROM email_log_users WHERE user_id = ?) AS t1
                    INNER JOIN
                    (SELECT email_log_id FROM email_log_users WHERE user_id = ?) AS t2
                    ON t1.email_log_id = t2.email_log_id);',
                [(int) $newUserId, (int) $oldUserId, (int) $newUserId, (int) $oldUserId]
            )
        ];
    }


    //
    // Private helper methods.
    //
    /**
     * Stores the correspondent user ids of the all recipient emails.
     *
     * @param EmailLogEntry $entry
     */
    public function _insertLogUserIds($entry)
    {
        $recipients = $entry->getRecipients();

        // We can use a simple regex to get emails, since we don't want to validate it.
        $pattern = '/(?<=\<)[^\>]*(?=\>)/';
        preg_match_all($pattern, $recipients, $matches);
        if (!isset($matches[0])) {
            return;
        }

        foreach ($matches[0] as $emailAddress) {
            $user = Repo::user()->getByEmail($emailAddress, true);
            if ($user instanceof \PKP\user\User) {
                // We use replace here to avoid inserting duplicated entries
                // in table (sometimes the recipients can have the same email twice).
                DB::table('email_log_users')->updateOrInsert(
                    ['email_log_id' => $entry->getId(), 'user_id' => $user->getId()]
                );
            }
        }
    }

    /**
     * Construct a new email log entry.
     *
     * @return EmailLogEntry
     */
    public function newDataObject()
    {
        return new EmailLogEntry();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\EmailLogDAO', '\EmailLogDAO');
}
