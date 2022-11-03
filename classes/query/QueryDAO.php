<?php

/**
 * @file classes/query/QueryDAO.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryDAO
 * @ingroup query
 *
 * @see Query
 *
 * @brief Operations for retrieving and modifying Query objects.
 */

namespace PKP\query;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\mail\Mailable;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\plugins\Hook;

class QueryDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a submission query by ID.
     *
     * @param int $queryId Query ID
     * @param int $assocType Optional ASSOC_TYPE_...
     * @param int $assocId Optional assoc ID per assocType
     *
     * @return Query
     */
    public function getById($queryId, $assocType = null, $assocId = null)
    {
        $params = [(int) $queryId];
        if ($assocType) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }
        $result = $this->retrieve(
            'SELECT *
			FROM	queries
			WHERE	query_id = ?'
                . ($assocType ? ' AND assoc_type = ? AND assoc_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve all queries by association
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId Assoc ID
     * @param int $stageId Optional stage ID
     * @param int $userId Optional user ID; when set, show only assigned queries
     *
     * @return array Query
     */
    public function getByAssoc($assocType, $assocId, $stageId = null, $userId = null)
    {
        $params = [];
        $params[] = (int) ASSOC_TYPE_QUERY;
        if ($userId) {
            $params[] = (int) $userId;
        }
        $params[] = (int) $assocType;
        $params[] = (int) $assocId;
        if ($stageId) {
            $params[] = (int) $stageId;
        }
        if ($userId) {
            $params[] = (int) $userId;
        }

        return new DAOResultFactory(
            $this->retrieve(
                'SELECT	DISTINCT q.*
				FROM	queries q
				LEFT JOIN notes n ON n.assoc_type = ? AND n.assoc_id = q.query_id
				' . ($userId ? 'INNER JOIN query_participants qp ON (q.query_id = qp.query_id AND qp.user_id = ?)' : '') . '
				WHERE	q.assoc_type = ? AND q.assoc_id = ?
				' . ($stageId ? ' AND q.stage_id = ?' : '') .
                ($userId ? '
				AND (n.user_id = ? OR n.title IS NOT NULL
				OR n.contents IS NOT NULL)' : '') . '
				ORDER BY q.seq',
                $params
            ),
            $this,
            '_fromRow'
        );
    }

    /**
     * Internal function to return a submission query object from a row.
     *
     * @param array $row
     *
     * @return Query
     */
    public function _fromRow($row)
    {
        $query = $this->newDataObject();
        $query->setId($row['query_id']);
        $query->setAssocType($row['assoc_type']);
        $query->setAssocId($row['assoc_id']);
        $query->setStageId($row['stage_id']);
        $query->setIsClosed($row['closed']);
        $query->setSequence($row['seq']);

        Hook::call('QueryDAO::_fromRow', [&$query, &$row]);
        return $query;
    }

    /**
     * Get a new data object
     *
     * @return DataObject
     */
    public function newDataObject()
    {
        return new Query();
    }

    /**
     * Insert a new Query.
     *
     * @param Query $query
     *
     * @return int New query ID
     */
    public function insertObject($query)
    {
        $this->update(
            'INSERT INTO queries (assoc_type, assoc_id, stage_id, closed, seq)
			VALUES (?, ?, ?, ?, ?)',
            [
                (int) $query->getAssocType(),
                (int) $query->getAssocId(),
                (int) $query->getStageId(),
                (int) $query->getIsClosed(),
                (float) $query->getSequence(),
            ]
        );
        $query->setId($this->getInsertId());
        return $query->getId();
    }

    /**
     * Adds a participant to a query.
     *
     * @param int $queryId Query ID
     * @param int $userId User ID
     */
    public function insertParticipant($queryId, $userId)
    {
        $this->update(
            'INSERT INTO query_participants
			(query_id, user_id)
			VALUES
			(?, ?)',
            [(int) $queryId, (int) $userId]
        );
    }

    /**
     * Removes a participant from a query.
     *
     * @param int $queryId Query ID
     * @param int $userId User ID
     */
    public function removeParticipant($queryId, $userId)
    {
        $this->update(
            'DELETE FROM query_participants WHERE query_id = ? AND user_id = ?',
            [(int) $queryId, (int) $userId]
        );
    }

    /**
     * Removes all participants from a query.
     *
     * @param int $queryId Query ID
     */
    public function removeAllParticipants($queryId)
    {
        $this->update(
            'DELETE FROM query_participants WHERE query_id = ?',
            [(int) $queryId]
        );
    }

    /**
     * Retrieve all participant user IDs for a query.
     *
     * @param int $queryId Query ID
     * @param int $userId User ID to restrict results to
     *
     * @return array
     */
    public function getParticipantIds($queryId, $userId = null)
    {
        $params = [(int) $queryId];
        if ($userId) {
            $params[] = (int) $userId;
        }
        $result = $this->retrieve(
            'SELECT	user_id
			FROM	query_participants
			WHERE	query_id = ?' .
            ($userId ? ' AND user_id = ?' : ''),
            $params
        );
        $userIds = [];
        foreach ($result as $row) {
            $userIds[] = (int) $row->user_id;
        }
        return $userIds;
    }

    /**
     * Update an existing Query.
     *
     * @param Query $query
     */
    public function updateObject($query)
    {
        $this->update(
            'UPDATE	queries
			SET	assoc_type = ?,
				assoc_id = ?,
				stage_id = ?,
				closed = ?,
				seq = ?
			WHERE	query_id = ?',
            [
                (int) $query->getAssocType(),
                (int) $query->getAssocId(),
                (int) $query->getStageId(),
                (int) $query->getIsClosed(),
                (float) $query->getSequence(),
                (int) $query->getId()
            ]
        );
    }

    /**
     * Delete a submission query.
     *
     * @param Query $query
     */
    public function deleteObject($query)
    {
        $this->deleteById($query->getId());
    }

    /**
     * Delete a submission query by ID.
     *
     * @param int $queryId Query ID
     * @param int $assocType Optional ASSOC_TYPE_...
     * @param int $assocId Optional assoc ID per assocType
     */
    public function deleteById($queryId, $assocType = null, $assocId = null)
    {
        $queryQuery = DB::table('queries')
            ->where('query_id', '=', $queryId);

        if ($assocType) {
            $queryQuery->where('assoc_type', '=', $assocType)
                ->where('assoc_id', '=', $assocId);
        }

        if ($queryQuery->count()) {
            // Remove associated notes
            $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
            $noteDao->deleteByAssoc(Application::ASSOC_TYPE_QUERY, $queryId);

            // Remove associated participants: will be removed by onDelete CASCADE on foreign key query_id column

            // Remove associated notifications
            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $notifications = $notificationDao->getByAssoc(Application::ASSOC_TYPE_QUERY, $queryId);
            while ($notification = $notifications->next()) {
                $notificationDao->deleteObject($notification);
            }

            $queryQuery->delete();
        }
    }

    /**
     * Sequentially renumber queries in their sequence order.
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId Assoc ID per assocType
     */
    public function resequence($assocType, $assocId)
    {
        $result = $this->retrieve(
            'SELECT query_id FROM queries WHERE assoc_type = ? AND assoc_id = ? ORDER BY seq',
            [(int) $assocType, (int) $assocId]
        );

        for ($i = 1; $row = $result->current(); $i++) {
            $this->update('UPDATE queries SET seq = ? WHERE query_id = ?', [$i, $row->query_id]);
            $result->next();
        }
    }

    /**
     * Delete queries by assoc info.
     *
     * @param int $assocType ASSOC_TYPE_...
     * @param int $assocId Assoc ID per assocType
     */
    public function deleteByAssoc($assocType, $assocId)
    {
        $queries = $this->getByAssoc($assocType, $assocId);
        while ($query = $queries->next()) {
            $this->deleteObject($query);
        }
    }

    /**
     * Add a query when a recommendation (editor decision type) is made
     */
    public function addRecommendationQuery(int $recommenderUserId, int $submissionId, int $stageId, string $title, string $content): int
    {
        $query = $this->newDataObject();
        $query->setAssocType(Application::ASSOC_TYPE_SUBMISSION);
        $query->setAssocId($submissionId);
        $query->setStageId($stageId);
        $query->setSequence(REALLY_BIG_NUMBER);
        $this->insertObject($query);
        $this->resequence(Application::ASSOC_TYPE_SUBMISSION, $submissionId);

        // Add the decision making editors as discussion participants
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $discussionParticipantsIds = [];
        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $stageId);
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if (!$editorsStageAssignment->getRecommendOnly()) {
                if (!in_array($editorsStageAssignment->getUserId(), $discussionParticipantsIds)) {
                    $discussionParticipantsIds[] = $editorsStageAssignment->getUserId();
                    $this->insertParticipant($query->getId(), $editorsStageAssignment->getUserId());
                }
            }
        }

        // Add the message
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->newDataObject();
        $note->setAssocType(Application::ASSOC_TYPE_QUERY);
        $note->setAssocId($query->getId());
        $note->setContents($content);
        $note->setTitle($title);
        $note->setDateCreated(Core::getCurrentDate());
        $note->setDateModified(Core::getCurrentDate());
        $note->setUserId($recommenderUserId);
        $noteDao->insertObject($note);

        // Add task for assigned participants
        $notificationMgr = new NotificationManager();
        $recommenderUser = Repo::user()->get($recommenderUserId);
        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDAO */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        $contextId = Application::get()->getRequest()->getContext()->getId();
        foreach ($discussionParticipantsIds as $discussionParticipantsId) {
            $notificationMgr->createNotification(
                Application::get()->getRequest(),
                $discussionParticipantsId,
                Notification::NOTIFICATION_TYPE_NEW_QUERY,
                $contextId,
                Application::ASSOC_TYPE_QUERY,
                $query->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if the user is unsubscribed
            $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $discussionParticipantsId,
                $contextId
            );
            if (in_array(PKPNotification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                continue;
            }
            $recipient = Repo::user()->get($discussionParticipantsId);
            $mailable = new Mailable();
            $mailable->to($recipient->getEmail(), $recipient->getFullName());
            $mailable->from($recommenderUser->getEmail(), $recommenderUser->getFullName());
            $mailable->subject($title);
            $mailable->body($content);
            Mail::send($mailable);
        }

        return $query->getId();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\query\QueryDAO', '\QueryDAO');
}
