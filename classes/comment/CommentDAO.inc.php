<?php

/**
 * @file classes/comment/CommentDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CommentDAO
 * @ingroup comment
 * @see Comment
 *
 * @brief Operations for retrieving and modifying Comment objects.
 */


import('lib.pkp.classes.comment.Comment');

define ('SUBMISSION_COMMENT_RECURSE_ALL', -1);

// Comment system configuration constants
define ('COMMENTS_DISABLED', 0);	// All comments disabled
define ('COMMENTS_AUTHENTICATED', 1);	// Can be posted by authenticated users
define ('COMMENTS_ANONYMOUS', 2);	// Can be posted anonymously by authenticated users
define ('COMMENTS_UNAUTHENTICATED', 3);	// Can be posted anonymously by anyone

class CommentDAO extends DAO {
	/**
	 * Constructor
	 */
	function CommentDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Comments by submission id
	 * @param $submissionId int
	 * @param $childLevels int optional
	 * @return Comment objects array
	 */
	function &getRootCommentsBySubmissionId($submissionId, $childLevels = 0) {
		$comments = array();

		$result =& $this->retrieve(
			'SELECT	*
			FROM	comments
			WHERE	submission_id = ? AND
				parent_comment_id IS NULL
			ORDER BY date_posted',
			(int) $submissionId
		);

		while (!$result->EOF) {
			$comments[] =& $this->_returnCommentFromRow($result->GetRowAssoc(false), $childLevels);
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $comments;
	}

	/**
	 * Retrieve Comments by parent comment id
	 * @param $parentId int
	 * @return Comment objects array
	 */
	function &getCommentsByParentId($parentId, $childLevels = 0) {
		$comments = array();

		$result =& $this->retrieve('SELECT * FROM comments WHERE parent_comment_id = ? ORDER BY date_posted', (int) $parentId);

		while (!$result->EOF) {
			$comments[] =& $this->_returnCommentFromRow($result->GetRowAssoc(false), $childLevels);
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $comments;
	}

	/**
	 * Retrieve comments by user id
	 * @param $userId int
	 * @return Comment objects array
	 */
	function &getByUserId($userId) {
		$comments = array();

		$result =& $this->retrieve('SELECT * FROM comments WHERE user_id = ?', (int) $userId);

		while (!$result->EOF) {
			$comments[] =& $this->_returnCommentFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $comments;
	}

	/**
	 * Check whether any reader comments are attributed to the user.
	 * @param $userId int The ID of the user to check
	 * @return boolean
	 */
	function attributedCommentsExistForUser($userId) {
		$result =& $this->retrieve('SELECT count(*) FROM comments WHERE user_id = ?', (int) $userId);
		$returner = $result->fields[0]?true:false;
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve Comment by comment id
	 * @param $commentId int
	 * @param $submissionId int optional
	 * @param $childLevels int optional
	 * @return Comment object
	 */
	function &getById($commentId, $submissionId, $childLevels = 0) {
		$result =& $this->retrieve(
			'SELECT * FROM comments WHERE comment_id = ? and submission_id = ?',
			array((int) $commentId, (int) $submissionId)
		);

		$comment = null;
		if ($result->RecordCount() != 0) {
			$comment =& $this->_returnCommentFromRow($result->GetRowAssoc(false), $childLevels);
		}

		$result->Close();
		unset($result);

		return $comment;
	}

	/**
	 * Instantiate and return a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new Comment();
	}

	/**
	 * Creates and returns a submission comment object from a row
	 * @param $row array
	 * @return Comment object
	 */
	function &_returnCommentFromRow($row, $childLevels = 0) {
		$userDao =& DAORegistry::getDAO('UserDAO');

		$comment = $this->newDataObject();
		$comment->setId($row['comment_id']);
		$comment->setSubmissionId($row['submission_id']);
		$comment->setUser($userDao->getById($row['user_id']), true);
		$comment->setPosterIP($row['poster_ip']);
		$comment->setPosterName($row['poster_name']);
		$comment->setPosterEmail($row['poster_email']);
		$comment->setTitle($row['title']);
		$comment->setBody($row['body']);
		$comment->setDatePosted($this->datetimeFromDB($row['date_posted']));
		$comment->setDateModified($this->datetimeFromDB($row['date_modified']));
		$comment->setParentCommentId($row['parent_comment_id']);
		$comment->setChildCommentCount($row['num_children']);

		if (!HookRegistry::call('CommentDAO::_returnCommentFromRow', array(&$comment, &$row, &$childLevels))) {
			if ($childLevels>0) $comment->setChildren($this->getCommentsByParentId($row['comment_id'], $childLevels-1));
			else if ($childLevels==SUBMISSION_COMMENT_RECURSE_ALL) $comment->setChildren($this->getCommentsByParentId($row['comment_id'], SUBMISSION_COMMENT_RECURSE_ALL));
		}

		return $comment;
	}

	/**
	 * inserts a new submission comment into comments table
	 * @param Comment object
	 * @return int ID of new comment
	 */
	function insertComment(&$comment) {
		$comment->setDatePosted(Core::getCurrentDate());
		$comment->setDateModified($comment->getDatePosted());
		$user = $comment->getUser();
		$this->update(
			sprintf('INSERT INTO comments
				(submission_id, num_children, parent_comment_id, user_id, poster_ip, date_posted, date_modified, title, body, poster_name, poster_email)
				VALUES
				(?, ?, ?, ?, ?, %s, %s, ?, ?, ?, ?)',
				$this->datetimeToDB($comment->getDatePosted()), $this->datetimeToDB($comment->getDateModified())),
			array(
				$comment->getSubmissionId(),
				$comment->getChildCommentCount(),
				$comment->getParentCommentId(),
				(isset($user)?$user->getId():null),
				$comment->getPosterIP(),
				String::substr($comment->getTitle(), 0, 255),
				$comment->getBody(),
				String::substr($comment->getPosterName(), 0, 90),
				String::substr($comment->getPosterEmail(), 0, 90)
			)
		);

		$comment->setId($this->getInsertCommentId());

		if ($comment->getParentCommentId()) $this->incrementChildCount($comment->getParentCommentId());

		return $comment->getId();
	}

	/**
	 * Get the ID of the last inserted submission comment.
	 * @return int
	 */
	function getInsertCommentId() {
		return $this->getInsertId('comments', 'comment_id');
	}

	/**
	 * Increase the current count of child comments for the specified comment.
	 * @param commentId int
	 */
	function incrementChildCount($commentId) {
		$this->update('UPDATE comments SET num_children=num_children+1 WHERE comment_id = ?', $commentId);
	}

	/**
	 * Decrease the current count of child comments for the specified comment.
	 * @param commentId int
	 */
	function decrementChildCount($commentId) {
		$this->update('UPDATE comments SET num_children=num_children-1 WHERE comment_id = ?', $commentId);
	}

	/**
	 * Removes a submission comment from comments table
	 * @param Comment object
	 */
	function deleteComment(&$comment, $isRecursing = false) {
		$result = $this->update('DELETE FROM comments WHERE comment_id = ?', $comment->getId());
		if (!$isRecursing) $this->decrementChildCount($comment->getParentCommentId());
		foreach ($comment->getChildren() as $child) {
			$this->deleteComment($child, true);
		}
	}

	/**
	 * Removes submission comments by submission ID
	 * @param $submissionId int
	 */
	function deleteBySubmissionId($submissionId) {
		return $this->update(
			'DELETE FROM comments WHERE submission_id = ?',
			(int) $submissionId
		);
	}

	/**
	 * updates a comment
	 * @param Comment object
	 */
	function updateComment(&$comment) {
		$comment->setDateModified(Core::getCurrentDate());
		$user = $comment->getUser();
		$this->update(
			sprintf('UPDATE comments
				SET
					submission_id = ?,
					num_children = ?,
					parent_comment_id = ?,
					user_id = ?,
					poster_ip = ?,
					date_posted = %s,
					date_modified = %s,
					title = ?,
					body = ?,
					poster_name = ?,
					poster_email = ?
				WHERE	comment_id = ?',
				$this->datetimeToDB($comment->getDatePosted()), $this->datetimeToDB($comment->getDateModified())),
			array(
				$comment->getSubmissionId(),
				$comment->getChildCommentCount(),
				$comment->getParentCommentId(),
				(isset($user)?$user->getId():null),
				$comment->getPosterIP(),
				String::substr($comment->getTitle(), 0, 255),
				$comment->getBody(),
				String::substr($comment->getPosterName(), 0, 90),
				String::substr($comment->getPosterEmail(), 0, 90),
				$comment->getId()
			)
		);
	}
}

?>
