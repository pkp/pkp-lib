<?php

/**
 * @file classes/submission/reviewAssignment/PKPReviewAssignmentDAO.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewAssignmentDAO
 * @ingroup submission
 * @see PKPReviewAssignment
 *
 * @brief Class for DAO relating reviewers to submissions.
 */

// $Id$


import('lib.pkp.classes.submission.reviewAssignment.PKPReviewAssignment');

class PKPReviewAssignmentDAO extends DAO {
	var $userDao;

	/**
	 * Constructor.
	 */
	function PKPReviewAssignmentDAO() {
		parent::DAO();
		$this->userDao =& DAORegistry::getDAO('UserDAO');
	}

	/**
	 * Retrieve a review assignment by reviewer and submission.
	 * @param $submissionId int
	 * @param $reviewerId int
	 * @param $round int
	 * @param $reviewType int
	 * @return ReviewAssignment
	 */
	function &getReviewAssignment($submissionId, $reviewerId, $round, $reviewType = 0) {

		$result =& $this->retrieve(
			'SELECT r.*, r2.review_revision, u.first_name, u.last_name
			FROM    review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE   r.submission_id = ? AND
				r.reviewer_id = ? AND
				r.cancelled <> 1 AND
				r.review_type = ? AND
				r.round = ?',
			array((int) $submissionId, (int) $reviewerId, (int) $reviewType, (int) $round)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Retrieve a review assignment by review assignment id.
	 * @param $reviewId int
	 * @return ReviewAssignment
	 */
	function &getById($reviewId) {
		$result =& $this->retrieve(
			'SELECT	r.*, r2.review_revision, u.first_name, u.last_name
			FROM    review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE   r.review_id = ?',
			(int) $reviewId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Get all incomplete review assignments for all journals/conferences/presses
	 * @param $articleId int
	 * @return array ReviewAssignments
	 */
	function &getIncompleteReviewAssignments() {
		$reviewAssignments = array();

		$result =& $this->retrieve(
			'SELECT	r.*, r2.review_revision, u.first_name, u.last_name
			FROM	review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE	(r.cancelled IS NULL OR r.cancelled = 0) AND
				r.date_notified IS NOT NULL AND
				r.date_completed IS NULL AND
				r.declined <> 1
			ORDER BY r.submission_id'
		);

		while (!$result->EOF) {
			$reviewAssignments[] =& $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewAssignments;
	}

	/**
	 * Get all review assignments for a submission.
	 * @param $submissionId int optional
	 * @param $reviewType int optional
	 * @return array ReviewAssignments
	 */
	function &getBySubmissionId($submissionId, $round = null, $reviewType = null) {
		$reviewAssignments = array();

		$query = 'SELECT r.*, r2.review_revision, u.first_name, u.last_name
			FROM	review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round AND r.review_type = r2.review_type)
			WHERE	r.submission_id = ?';

		$orderBy = ' ORDER BY review_id';

		$queryParams[] = (int) $submissionId;

		if ($round != null) {
			$query .= ' AND r.round = ?';
			$queryParams[] = (int) $round;
		} else {
			$orderBy .= ', r.round';
		}

		if ($reviewType != null) {
			$query .= ' AND r.review_type = ?';
			$queryParams[] = (int) $reviewType;
		} else {
			$orderBy .= ', r.review_type';
		}

		$query .= $orderBy;

		$result =& $this->retrieve($query, $queryParams);

		while (!$result->EOF) {
			$reviewAssignments[$result->fields['review_id']] =& $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewAssignments;
	}

	/**
	 * Get all review assignments for a reviewer.
	 * @param $userId int
	 * @return array ReviewAssignments
	 */
	function &getByUserId($userId) {
		$reviewAssignments = array();

		$result =& $this->retrieve(
			'SELECT	r.*, r2.review_revision, u.first_name, u.last_name
			FROM	review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE	r.reviewer_id = ?
			ORDER BY round, review_id',
			(int) $userId
		);

		while (!$result->EOF) {
			$reviewAssignments[] =& $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewAssignments;
	}

	/**
	 * Get all review assignments for a review form.
	 * @param $reviewFormId int
	 * @return array ReviewAssignments
	 */
	function &getByReviewFormId($reviewFormId) {
		$reviewAssignments = array();

		$result =& $this->retrieve(
			'SELECT	r.*, r2.review_revision, u.first_name, u.last_name
			FROM	review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE	r.review_form_id = ?
			ORDER BY round, review_id',
			(int) $reviewFormId
		);

		while (!$result->EOF) {
			$reviewAssignments[] =& $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewAssignments;
	}

	/**
	 * Get all cancelled/declined review assignments for a submission.
	 * @param $submissionId int
	 * @return array ReviewAssignments
	 */
	function &getCancelsAndRegrets($submissionId) {
		$reviewAssignments = array();

		$result =& $this->retrieve(
			'SELECT	r.*, r2.review_revision, u.first_name, u.last_name
			FROM	review_assignments r
				LEFT JOIN users u ON (r.reviewer_id = u.user_id)
				LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round)
			WHERE	r.submission_id = ? AND
				(r.cancelled = 1 OR r.declined = 1)
			ORDER BY round, review_id',
			(int) $submissionId
		);

		while (!$result->EOF) {
			$reviewAssignments[] =& $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewAssignments;
	}

	/**
	 * Determine the order of active reviews for the given round of the given submission
	 * @param $submissionId int
	 * @param $round int
	 * @return array associating review ID with number; ie if review ID 26 is first, returned['26']=0
	 */
	function &getReviewIndexesForRound($submissionId, $round) {
		$returner = array();
		$index = 0;
		$result =& $this->retrieve(
			'SELECT	review_id
			FROM	review_assignments
			WHERE	submission_id = ? AND
				round = ? AND
				(cancelled = 0 OR cancelled IS NULL)
			ORDER BY review_id',
			array((int) $submissionId, (int) $round)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[$row['review_id']] = $index++;
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Get the most recent last modified date for all review assignments for each round of a submission.
	 * @param $submissionId int
	 * @param $round int
	 * @return array associating round with most recent last modified date
	 */
	function &getLastModifiedByRound($submissionId) {
		$returner = array();

		$result =& $this->retrieve(
			'SELECT	round, MAX(last_modified) as last_modified
			FROM	review_assignments
			WHERE	submission_id = ?
			GROUP BY round',
			(int) $submissionId
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[$row['round']] = $this->datetimeFromDB($row['last_modified']);
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Get the first notified date from all review assignments for a round of a submission.
	 * @param $submissionId int
	 * @param $round int
	 * @return array Associative array of ($round_num => $earliest_date_of_notification)*
	 */
	function &getEarliestNotificationByRound($submissionId) {
		$returner = array();

		$result =& $this->retrieve(
			'SELECT	round, MIN(date_notified) as earliest_date
			FROM	review_assignments
			WHERE	submission_id = ?
			GROUP BY round',
			(int) $submissionId
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[$row['round']] = $this->datetimeFromDB($row['earliest_date']);
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	function insertReviewAssignment(&$reviewAssignment) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->insertObject($reviewAssignment);
	}

	/**
	 * Insert a new Review Assignment.
	 * @param $reviewAssignment ReviewAssignment
	 */
	function insertObject(&$reviewAssignment) {
		$this->update(
			sprintf('INSERT INTO review_assignments (
				submission_id,
				reviewer_id,
				review_type,
				review_method,
				regret_message,
				round,
				competing_interests,
				recommendation,
				declined, replaced, cancelled,
				date_assigned, date_notified, date_confirmed,
				date_completed, date_acknowledged, date_due, date_response_due,
				reviewer_file_id,
				quality, date_rated,
				last_modified,
				date_reminded, reminder_was_automatic,
				review_form_id
				) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, %s, %s, %s, %s, %s, %s, %s, ?, ?, %s, %s, %s, ?, ?
				)',
				$this->datetimeToDB($reviewAssignment->getDateAssigned()), $this->datetimeToDB($reviewAssignment->getDateNotified()), $this->datetimeToDB($reviewAssignment->getDateConfirmed()), $this->datetimeToDB($reviewAssignment->getDateCompleted()), $this->datetimeToDB($reviewAssignment->getDateAcknowledged()), $this->datetimeToDB($reviewAssignment->getDateDue()), $this->datetimeToDB($reviewAssignment->getDateResponseDue()), $this->datetimeToDB($reviewAssignment->getDateRated()), $this->datetimeToDB($reviewAssignment->getLastModified()), $this->datetimeToDB($reviewAssignment->getDateReminded())),
			array(
				(int) $reviewAssignment->getSubmissionId(),
				(int) $reviewAssignment->getReviewerId(),
				(int) $reviewAssignment->getReviewType(),
				(int) $reviewAssignment->getReviewMethod(),
				$reviewAssignment->getRegretMessage(),
				max((int) $reviewAssignment->getRound(), 1),
				$reviewAssignment->getCompetingInterests(),
				$reviewAssignment->getRecommendation(),
				(int) $reviewAssignment->getDeclined(),
				(int) $reviewAssignment->getReplaced(),
				(int) $reviewAssignment->getCancelled(),
				$reviewAssignment->getReviewerFileId(),
				$reviewAssignment->getQuality(),
				$reviewAssignment->getReminderWasAutomatic(),
				$reviewAssignment->getReviewFormId()
			)
		);

		$reviewAssignment->setId($this->getInsertReviewId());
		return $reviewAssignment->getId();
	}

	/**
	 * Update an existing review assignment.
	 * @param $reviewAssignment object
	 */
	function updateReviewAssignment(&$reviewAssignment) {
		return $this->update(
			sprintf('UPDATE review_assignments
				SET	submission_id = ?,
					reviewer_id = ?,
					review_type = ?,
					review_method = ?,
					regret_message = ?,
					round = ?,
					competing_interests = ?,
					recommendation = ?,
					declined = ?,
					replaced = ?,
					cancelled = ?,
					date_assigned = %s,
					date_notified = %s,
					date_confirmed = %s,
					date_completed = %s,
					date_acknowledged = %s,
					date_due = %s,
					date_response_due = %s,
					reviewer_file_id = ?,
					quality = ?,
					date_rated = %s,
					last_modified = %s,
					date_reminded = %s,
					reminder_was_automatic = ?,
					review_form_id = ?
				WHERE review_id = ?',
				$this->datetimeToDB($reviewAssignment->getDateAssigned()), $this->datetimeToDB($reviewAssignment->getDateNotified()), $this->datetimeToDB($reviewAssignment->getDateConfirmed()), $this->datetimeToDB($reviewAssignment->getDateCompleted()), $this->datetimeToDB($reviewAssignment->getDateAcknowledged()), $this->datetimeToDB($reviewAssignment->getDateDue()), $this->datetimeToDB($reviewAssignment->getDateResponseDue()), $this->datetimeToDB($reviewAssignment->getDateRated()), $this->datetimeToDB($reviewAssignment->getLastModified()), $this->datetimeToDB($reviewAssignment->getDateReminded())),
			array(
				(int) $reviewAssignment->getSubmissionId(),
				(int) $reviewAssignment->getReviewerId(),
				(int) $reviewAssignment->getReviewType(),
				(int) $reviewAssignment->getReviewMethod(),
				$reviewAssignment->getRegretMessage(),
				(int) $reviewAssignment->getRound(),
				$reviewAssignment->getCompetingInterests(),
				$reviewAssignment->getRecommendation(),
				(int) $reviewAssignment->getDeclined(),
				(int) $reviewAssignment->getReplaced(),
				(int) $reviewAssignment->getCancelled(),
				$reviewAssignment->getReviewerFileId(),
				$reviewAssignment->getQuality(),
				$reviewAssignment->getReminderWasAutomatic(),
				$reviewAssignment->getReviewFormId(),
				(int) $reviewAssignment->getId()
			)
		);
	}

	/**
	 * Internal function to return a review assignment object from a row.
	 * @param $row array
	 * @return ReviewAssignment
	 */
	function &_fromRow(&$row) {
		$reviewAssignment = $this->newDataObject();

		$reviewAssignment->setReviewId($row['review_id']);
		$reviewAssignment->setSubmissionId($row['submission_id']);
		$reviewAssignment->setReviewerId($row['reviewer_id']);
		$reviewAssignment->setReviewerFullName($row['first_name'].' '.$row['last_name']);
		$reviewAssignment->setCompetingInterests($row['competing_interests']);
		$reviewAssignment->setRegretMessage($row['regret_message']);
		$reviewAssignment->setRecommendation($row['recommendation']);
		$reviewAssignment->setDateAssigned($this->datetimeFromDB($row['date_assigned']));
		$reviewAssignment->setDateNotified($this->datetimeFromDB($row['date_notified']));
		$reviewAssignment->setDateConfirmed($this->datetimeFromDB($row['date_confirmed']));
		$reviewAssignment->setDateCompleted($this->datetimeFromDB($row['date_completed']));
		$reviewAssignment->setDateAcknowledged($this->datetimeFromDB($row['date_acknowledged']));
		$reviewAssignment->setDateDue($this->datetimeFromDB($row['date_due']));
		$reviewAssignment->setDateResponseDue($this->datetimeFromDB($row['date_response_due']));
		$reviewAssignment->setLastModified($this->datetimeFromDB($row['last_modified']));
		$reviewAssignment->setDeclined($row['declined']);
		$reviewAssignment->setReplaced($row['replaced']);
		$reviewAssignment->setCancelled($row['cancelled']);
		$reviewAssignment->setReviewerFileId($row['reviewer_file_id']);
		$reviewAssignment->setQuality($row['quality']);
		$reviewAssignment->setDateRated($this->datetimeFromDB($row['date_rated']));
		$reviewAssignment->setDateReminded($this->datetimeFromDB($row['date_reminded']));
		$reviewAssignment->setReminderWasAutomatic($row['reminder_was_automatic']);
		$reviewAssignment->setRound($row['round']);
		$reviewAssignment->setReviewRevision($row['review_revision']);
		$reviewAssignment->setReviewFormId($row['review_form_id']);
		$reviewAssignment->setReviewType($row['review_type']);

		return $reviewAssignment;
	}

	/**
	 * Return a new review assignment data object.
	 * @return DataObject
	 */
	function newDataObject() {
		assert(false); // Should be implemented by subclasses
	}

	function deleteReviewAssignmentById($reviewId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteById($reviewId);
	}

	/**
	 * Delete review assignment.
	 * @param $reviewId int
	 */
	function deleteById($reviewId) {
		$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
		$reviewFormResponseDao->deleteByReviewId($reviewId);

		return $this->update(
			'DELETE FROM review_assignments WHERE review_id = ?',
			(int) $reviewId
		);
	}

	/**
	 * Delete review assignments by submission ID.
	 * @param $submissionId int
	 * @return boolean
	 */
	function deleteBySubmissionId($submissionId) {
		$returner = false;
		$result =& $this->retrieve(
			'SELECT review_id FROM review_assignments WHERE submission_id = ?',
			array((int) $submissionId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$reviewId = $row['review_id'];

			$this->update('DELETE FROM review_form_responses WHERE review_id = ?', $reviewId);
			$this->update('DELETE FROM review_assignments WHERE review_id = ?', $reviewId);

			$result->MoveNext();
			$returner = true;
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted review assignment.
	 * @return int
	 */
	function getInsertReviewId() {
		return $this->getInsertId('review_assignments', 'review_id');
	}
}

?>
