<?php

/**
 * @file classes/submission/SubmissionCommentDAO.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionCommentDAO
 * @ingroup submission
 * @see SubmissionComment
 *
 * @brief Operations for retrieving and modifying SubmissionComment objects.
 */

import('lib.pkp.classes.submission.SubmissionComment');

class SubmissionCommentDAO extends DAO {
	/**
	 * Constructor
	 */
	function SubmissionCommentDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve SubmissionComments by submission id
	 * @param $submissionId int
	 * @param $commentType int
	 * @param $assocId int
	 * @return DAOResultFactory
	 */
	function getSubmissionComments($submissionId, $commentType = null, $assocId = null) {
		if ($commentType == null) {
			$result = $this->retrieve(
				'SELECT a.* FROM submission_comments a WHERE submission_id = ? ORDER BY date_posted', (int) $submissionId
			);
		} else {
			if ($assocId == null) {
				$result = $this->retrieve(
					'SELECT a.* FROM submission_comments a WHERE submission_id = ? AND comment_type = ? ORDER BY date_posted',
					array((int) $submissionId, (int) $commentType)
				);
			} else {
				$result = $this->retrieve(
					'SELECT a.* FROM submission_comments a WHERE submission_id = ? AND comment_type = ? AND assoc_id = ? ORDER BY date_posted',
					array((int) $submissionId, (int) $commentType, (int) $assocId)
				);
			}
		}

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve SubmissionComments by user id
	 * @param $userId int
	 * @return DAOResultFactory
	 */
	function getByUserId($userId) {
		$result = $this->retrieve(
			'SELECT a.* FROM submission_comments a WHERE author_id = ? ORDER BY date_posted', (int) $userId
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve SubmissionComments made my reviewers on a submission
	 * @param $reviewerId int The user id of the reviewer.
	 * @param $submissionId int The submission Id that was reviewered/commented on.
	 * @param $reviewId int (optional) The review assignment ID the comment pertains to.
	 * @return DAOResultFactory
	 */
	function getReviewerCommentsByReviewerId($reviewerId, $submissionId, $reviewId = null) {
		$params = array((int) $reviewerId, (int) $submissionId);
		if (isset($reviewId)) {
			$params[] = (int) $reviewId;
		}
		$result = $this->retrieve(
			'SELECT a.* FROM submission_comments a WHERE author_id = ? AND submission_id = ?' . (isset($reviewId) ? ' AND assoc_id = ?' : '') . ' ORDER BY date_posted DESC',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve most recent SubmissionComment
	 * @param $submissionId int
	 * @param $commentType int
	 * @return SubmissionComment
	 */
	function getMostRecentSubmissionComment($submissionId, $commentType = null, $assocId = null) {
		if ($commentType == null) {
			$result = $this->retrieveLimit(
				'SELECT a.* FROM submission_comments a WHERE submission_id = ? ORDER BY date_posted DESC',
				(int) $submissionId,
				1
			);
		} else {
			if ($assocId == null) {
				$result = $this->retrieveLimit(
					'SELECT a.* FROM submission_comments a WHERE submission_id = ? AND comment_type = ? ORDER BY date_posted DESC',
					array((int) $submissionId, (int) $commentType),
					1
				);
			} else {
				$result = $this->retrieveLimit(
					'SELECT a.* FROM submission_comments a WHERE submission_id = ? AND comment_type = ? AND assoc_id = ? ORDER BY date_posted DESC',
					array((int) $submissionId, (int) $commentType, (int) $assocId),
					1
				);
			}
		}

		$returner = null;
		if (isset($result) && $result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve submission comment by id
	 * @param $commentId int
	 * @return SubmissionComment object
	 */
	function getById($commentId) {
		$result = $this->retrieve(
			'SELECT * FROM submission_comments WHERE comment_id = ?', (int) $commentId
		);

		$submissionComment = $this->_fromRow($result->GetRowAssoc(false));

		$result->Close();
		return $submissionComment;
	}

	/**
	 * Construct a new DataObject.
	 * @return DataObject
	 */
	function newDataObject() {
		return new SubmissionComment();
	}

	/**
	 * Creates and returns a submission comment object from a row
	 * @param $row array
	 * @return SubmissionComment object
	 */
	function _fromRow($row) {
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

		HookRegistry::call('SubmissionCommentDAO::_fromRow', array(&$submissionComment, &$row));

		return $submissionComment;
	}

	/**
	 * inserts a new submission comment into the submission_comments table
	 * @param SubmissionNote object
	 * @return Submission note ID int
	 */
	function insertObject($submissionComment) {
		$this->update(
			sprintf('INSERT INTO submission_comments
				(comment_type, role_id, submission_id, assoc_id, author_id, date_posted, date_modified, comment_title, comments, viewable)
				VALUES
				(?, ?, ?, ?, ?, %s, %s, ?, ?, ?)',
				$this->datetimeToDB($submissionComment->getDatePosted()), $this->datetimeToDB($submissionComment->getDateModified())),
			array(
				(int) $submissionComment->getCommentType(),
				(int) $submissionComment->getRoleId(),
				(int) $submissionComment->getSubmissionId(),
				(int) $submissionComment->getAssocId(),
				(int) $submissionComment->getAuthorId(),
				$submissionComment->getCommentTitle(),
				$submissionComment->getComments(),
				(int) $submissionComment->getViewable()
			)
		);

		$submissionComment->setId($this->getInsertId());
		return $submissionComment->getId();
	}

	/**
	 * Get the ID of the last inserted submission comment.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('submission_comments', 'comment_id');
	}

	/**
	 * Removes a submission comment from the submission_comments table
	 * @param SubmissionComment object
	 */
	function deleteObject($submissionComment) {
		$this->deleteById($submissionComment->getId());
	}

	/**
	 * Removes a submission note by id
	 * @param noteId int
	 */
	function deleteById($commentId) {
		$this->update(
			'DELETE FROM submission_comments WHERE comment_id = ?',
			(int) $commentId
		);
	}

	/**
	 * Delete all comments for a submission.
	 * @param $submissionId int
	 */
	function deleteBySubmissionId($submissionId) {
		return $this->update(
			'DELETE FROM submission_comments WHERE submission_id = ?',
			(int) $submissionId
		);
	}

	/**
	 * Updates a submission comment
	 * @param SubmissionComment object
	 */
	function updateObject($submissionComment) {
		$this->update(
			sprintf('UPDATE submission_comments
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
				$this->datetimeToDB($submissionComment->getDatePosted()), $this->datetimeToDB($submissionComment->getDateModified())),
			array(
				(int) $submissionComment->getCommentType(),
				(int) $submissionComment->getRoleId(),
				(int) $submissionComment->getSubmissionId(),
				(int) $submissionComment->getAssocId(),
				(int) $submissionComment->getAuthorId(),
				$submissionComment->getCommentTitle(),
				$submissionComment->getComments(),
				$submissionComment->getViewable() === null ? 1 : (int) $submissionComment->getViewable(),
				(int) $submissionComment->getId()
			)
		);
	}
}

?>
