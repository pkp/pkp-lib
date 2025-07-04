<?php

/**
 * @file classes/submission/SubmissionCommentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionCommentDAO
 *
 * @ingroup submission
 *
 * @see SubmissionComment
 *
 * @brief Operations for retrieving and modifying SubmissionComment objects.
 */

namespace PKP\submission;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\db\DAOResultFactory;
use PKP\plugins\Hook;

class SubmissionCommentDAO extends \PKP\db\DAO
{
    /**
     * Retrieve SubmissionComments by submission id
     *
     * @param int $submissionId Submission ID
     * @param int $commentType Comment type
     * @param int $assocId Assoc ID
     *
     * @return DAOResultFactory<SubmissionComment>
     */
    public function getSubmissionComments($submissionId, $commentType = null, $assocId = null)
    {
        $params = [(int) $submissionId];
        if ($commentType) {
            $params[] = (int) $commentType;
        }
        if ($assocId) {
            $params[] = (int) $assocId;
        }
        return new DAOResultFactory(
            $this->retrieve(
                'SELECT	a.*
				FROM	submission_comments a
				WHERE	submission_id = ?'
                    . ($commentType ? ' AND comment_type = ?' : '')
                    . ($assocId ? ' AND assoc_id = ?' : '')
                    . ' ORDER BY date_posted',
                $params
            ),
            $this,
            '_fromRow'
        );
    }

    /**
     * Retrieve SubmissionComments by user id
     *
     * @param int $userId User ID.
     *
     * @return DAOResultFactory<SubmissionComment>
     */
    public function getByUserId($userId)
    {
        return new DAOResultFactory(
            $this->retrieve(
                'SELECT a.* FROM submission_comments a WHERE author_id = ? ORDER BY date_posted',
                [(int) $userId]
            ),
            $this,
            '_fromRow'
        );
    }

    /**
     * Retrieve SubmissionComments made my reviewers on a submission
     *
     * @param int $submissionId The submission Id that was reviewered/commented on.
     * @param int $reviewerId The user id of the reviewer.
     * @param int $reviewId (optional) The review assignment ID the comment pertains to.
     * @param bool $viewable True for only viewable comments; false for non-viewable; null for both
     *
     * @return DAOResultFactory<SubmissionComment>
     */
    public function getReviewerCommentsByReviewerId($submissionId, $reviewerId = null, $reviewId = null, $viewable = null)
    {
        $q = DB::table('submission_comments', 'a')
            ->where('a.submission_id', '=', $submissionId)
            ->when($reviewerId, fn (Builder $q) => $q->where('a.author_id', '=', $reviewerId))
            ->when($reviewId, fn (Builder $q) => $q->where('a.assoc_id', '=', $reviewId))
            ->when($viewable !== null, fn (Builder $q) => $q->where('a.viewable', '=', $viewable))
            ->orderByDesc('a.date_posted')
            ->select('a.*');

        return new DAOResultFactory($q->get(), $this, '_fromRow', [], $q); // Counted in readReview.tpl and authorReadReview.tpl
    }

    /**
     * Retrieve submission comment by id
     *
     * @param int $commentId Comment ID.
     *
     * @return SubmissionComment object
     */
    public function getById($commentId)
    {
        $result = $this->retrieve(
            'SELECT * FROM submission_comments WHERE comment_id = ?',
            [(int) $commentId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Construct a new \PKP\core\DataObject.
     *
     * @return SubmissionComment
     */
    public function newDataObject()
    {
        return new SubmissionComment();
    }

    /**
     * Creates and returns a submission comment object from a row
     *
     * @param array $row
     *
     * @return SubmissionComment object
     *
     * @hook SubmissionCommentDAO::_fromRow [[&$submissionComment, &$row]]
     */
    public function _fromRow($row)
    {
        $submissionComment = $this->newDataObject();
        $submissionComment->setId($row['comment_id']);
        $submissionComment->setCommentType($row['comment_type']);
        $submissionComment->setRoleId($row['role_id']);
        $submissionComment->setSubmissionId($row['submission_id']);
        $submissionComment->setAssocId($row['assoc_id']);
        $submissionComment->setAuthorId($row['author_id']);
        $submissionComment->setCommentTitle($row['comment_title']);
        $submissionComment->setComments($row['comments']);
        $submissionComment->setDatePosted($this->datetimeFromDB($row['date_posted']));
        $submissionComment->setDateModified($this->datetimeFromDB($row['date_modified']));
        $submissionComment->setViewable($row['viewable']);

        Hook::call('SubmissionCommentDAO::_fromRow', [&$submissionComment, &$row]);

        return $submissionComment;
    }

    /**
     * inserts a new submission comment into the submission_comments table
     *
     * @param SubmissionComment $submissionComment object
     *
     * @return int note ID int
     */
    public function insertObject($submissionComment)
    {
        $this->update(
            sprintf(
                'INSERT INTO submission_comments
				(comment_type, role_id, submission_id, assoc_id, author_id, date_posted, date_modified, comment_title, comments, viewable)
				VALUES
				(?, ?, ?, ?, ?, %s, %s, ?, ?, ?)',
                $this->datetimeToDB($submissionComment->getDatePosted()),
                $this->datetimeToDB($submissionComment->getDateModified())
            ),
            [
                (int) $submissionComment->getCommentType(),
                (int) $submissionComment->getRoleId(),
                (int) $submissionComment->getSubmissionId(),
                (int) $submissionComment->getAssocId(),
                (int) $submissionComment->getAuthorId(),
                $submissionComment->getCommentTitle(),
                $submissionComment->getComments(),
                (int) $submissionComment->getViewable()
            ]
        );

        $submissionComment->setId($this->getInsertId());
        return $submissionComment->getId();
    }

    /**
     * Removes a submission comment from the submission_comments table
     *
     * @param SubmissionComment $submissionComment object
     */
    public function deleteObject($submissionComment)
    {
        $this->deleteById($submissionComment->getId());
    }

    /**
     * Removes a submission note by id
     */
    public function deleteById(int $commentId): int
    {
        return DB::table('submission_comments')
            ->where('comment_id', '=', $commentId)
            ->delete();
    }

    /**
     * Delete all comments for a submission.
     *
     * @param int $submissionId
     */
    public function deleteBySubmissionId($submissionId)
    {
        $this->update(
            'DELETE FROM submission_comments WHERE submission_id = ?',
            [(int) $submissionId]
        );
    }

    /**
     * Updates a submission comment
     *
     * @param SubmissionComment $submissionComment object
     */
    public function updateObject($submissionComment)
    {
        $this->update(
            sprintf(
                'UPDATE submission_comments
				SET
					comment_type = ?,
					role_id = ?,
					submission_id = ?,
					assoc_id = ?,
					author_id = ?,
					date_posted = %s,
					date_modified = %s,
					comment_title = ?,
					comments = ?,
					viewable = ?
				WHERE comment_id = ?',
                $this->datetimeToDB($submissionComment->getDatePosted()),
                $this->datetimeToDB($submissionComment->getDateModified())
            ),
            [
                (int) $submissionComment->getCommentType(),
                (int) $submissionComment->getRoleId(),
                (int) $submissionComment->getSubmissionId(),
                (int) $submissionComment->getAssocId(),
                (int) $submissionComment->getAuthorId(),
                $submissionComment->getCommentTitle(),
                $submissionComment->getComments(),
                $submissionComment->getViewable() === null ? 1 : (int) $submissionComment->getViewable(),
                (int) $submissionComment->getId()
            ]
        );
    }
}
