<?php

/**
 * @file classes/submission/reviewRound/ReviewRoundDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundDAO
 * @ingroup submission_reviewRound
 * @see ReviewRound
 *
 * @brief Operations for retrieving and modifying ReviewRound objects.
 */

import('lib.pkp.classes.submission.reviewRound.ReviewRound');

class ReviewRoundDAO extends DAO {

	//
	// Public methods
	//
	/**
	 * Fetch a review round, creating it if needed.
	 * @param $submissionId integer
	 * @param $stageId integer One of the WORKFLOW_*_REVIEW_STAGE_ID constants.
	 * @param $round integer
	 * @param $status integer One of the REVIEW_ROUND_STATUS_* constants.
	 * @return ReviewRound
	 */
	function build($submissionId, $stageId, $round, $status = null) {
		// If one exists, fetch and return.
		$reviewRound = $this->getReviewRound($submissionId, $stageId, $round);
		if ($reviewRound) return $reviewRound;

		// Otherwise, check the args to build one.
		if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ||
			$stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW &&
			$round > 0
		) {
			unset($reviewRound);
			$reviewRound = $this->newDataObject();
			$reviewRound->setSubmissionId($submissionId);
			$reviewRound->setRound($round);
			$reviewRound->setStageId($stageId);
			$reviewRound->setStatus($status);
			$this->insertObject($reviewRound);
			$reviewRound->setId($this->getInsertId());

			return $reviewRound;
		} else {
			assert(false);
			return null;
		}
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return ReviewRound
	 */
	function newDataObject() {
		return new ReviewRound();
	}

	/**
	 * Insert a new review round.
	 * @param $reviewRound ReviewRound
	 * @return int
	 */
	function insertObject($reviewRound) {
		$this->update(
				'INSERT INTO review_rounds
				(submission_id, stage_id, round, status)
				VALUES
				(?, ?, ?, ?)',
				array(
					(int)$reviewRound->getSubmissionId(),
					(int)$reviewRound->getStageId(),
					(int)$reviewRound->getRound(),
					(int)$reviewRound->getStatus()
				)
		);
		return $reviewRound;
	}

	/**
	 * Update an existing review round.
	 * @param $reviewRound ReviewRound
	 * @return boolean
	 */
	function updateObject($reviewRound) {
		$returner = $this->update(
			'UPDATE	review_rounds
			SET	status = ?
			WHERE	submission_id = ? AND
				stage_id = ? AND
				round = ?',
			array(
				(int)$reviewRound->getStatus(),
				(int)$reviewRound->getSubmissionId(),
				(int)$reviewRound->getStageId(),
				(int)$reviewRound->getRound()
			)
		);
		return $returner;
	}

	/**
	 * Retrieve a review round
	 * @param $submissionId integer
	 * @param $stageId int One of the Stage_id_* constants.
	 * @param $round int The review round to be retrieved.
	 */
	function getReviewRound($submissionId, $stageId, $round) {
		$result = $this->retrieve(
			'SELECT * FROM review_rounds WHERE submission_id = ? AND stage_id = ? AND round = ?',
			array((int) $submissionId, (int) $stageId, (int) $round)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a review round by its id.
	 * @param int $reviewRoundId
	 * @return ReviewRound
	 */
	function getById($reviewRoundId) {
		$result = $this->retrieve(
			'SELECT * FROM review_rounds WHERE review_round_id = ?',
			(int) $reviewRoundId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a review round by a submission file id.
	 * @param $submissionFileId int
	 * @return ReviewRound
	 */
	function getBySubmissionFileId($submissionFileId) {
		$result = $this->retrieve(
				'SELECT * FROM review_rounds rr
				INNER JOIN review_round_files rrf
				ON rr.review_round_id = rrf.review_round_id
				WHERE rrf.file_id = ?',
				array((int) $submissionFileId));

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get an iterator of review round objects associated with this submission
	 * @param $submissionId int
	 * @param $stageId int (optional)
	 * @param $round int (optional)
	 */
	function getBySubmissionId($submissionId, $stageId = null, $round = null) {
		$params = array($submissionId);
		if ($stageId) $params[] = $stageId;
		if ($round) $params[] = $round;

		$result = $this->retrieve(
			'SELECT * FROM review_rounds WHERE submission_id = ?' .
			($stageId ? ' AND stage_id = ?' : '') .
			($round ? ' AND round = ?' : '') .
			' ORDER BY stage_id ASC, round ASC',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get the current review round for a given stage (or for the latest stage)
	 * @param $submissionId int
	 * @param $stageId int
	 * @return int
	 */
	function getCurrentRoundBySubmissionId($submissionId, $stageId = null) {
		$params = array((int)$submissionId);
		if ($stageId) $params[] = (int) $stageId;
		$result = $this->retrieve(
			'SELECT MAX(stage_id) as stage_id, MAX(round) as round
			FROM review_rounds
			WHERE submission_id = ?' .
			($stageId ? ' AND stage_id = ?' : ''),
			$params
		);
		$returner = isset($result->fields['round']) ? (int)$result->fields['round'] : 1;
		$result->Close();
		return $returner;
	}

	/**
	 * Get the last review round for a give stage (or for the latest stage)
	 * @param $submissionId int
	 * @param $stageId int
	 * @return ReviewRound
	 */
	function getLastReviewRoundBySubmissionId($submissionId, $stageId = null) {
		$params = array((int)$submissionId);
		if ($stageId) $params[] = (int) $stageId;
		$result = $this->retrieveLimit(
			'SELECT	*
			FROM	review_rounds
			WHERE	submission_id = ?
			' . ($stageId ? ' AND stage_id = ?' : '') . '
			ORDER BY stage_id DESC, round DESC',
			$params,
			1
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted review round.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('review_rounds', 'review_round_id');
	}

	/**
	 * Update the review round status.
	 *
	 * @param $reviewRound ReviewRound
	 * @param $status int? Optionally pass a REVIEW_ROUND_STATUS_... to set a
	 *  specific status. If not included, will determine the appropriate status
	 *  based on ReviewRound::determineStatus().
	 */
	function updateStatus($reviewRound, $status = null) {
		assert(is_a($reviewRound, 'ReviewRound'));
		$currentStatus = $reviewRound->getStatus();

		if (is_null($status)) {
			$status = $reviewRound->determineStatus();
		}

		// Avoid unnecessary database access.
		if ($status != $currentStatus) {
			$this->update('UPDATE review_rounds SET status = ? WHERE review_round_id = ?',
				array((int)$status, (int)$reviewRound->getId())
			);
			// Update the data in object too.
			$reviewRound->setStatus($status);
		}
	}


	/**
	 * Delete review rounds by submission ID.
	 * @param $submissionId int
	 */
	function deleteBySubmissionId($submissionId) {
		$reviewRounds = $this->getBySubmissionId($submissionId);
		while ($reviewRound = $reviewRounds->next()) {
			$this->deleteObject($reviewRound);
		}
	}

	/**
	 * Delete a review round.
	 * @param $reviewRound ReviewRound
	 */
	function deleteObject($reviewRound) {
		$this->deleteById($reviewRound->getId());
	}

	/**
	 * Delete a review round by ID.
	 * @param $reviewRoundId int
	 * @return boolean
	 */
	function deleteById($reviewRoundId) {
		$this->update('DELETE FROM notifications WHERE assoc_type = ? AND assoc_id = ?', array((int) ASSOC_TYPE_REVIEW_ROUND, (int) $reviewRoundId));
		return $this->update('DELETE FROM review_rounds WHERE review_round_id = ?', array((int) $reviewRoundId));
	}

	//
	// Private methods
	//
	/**
	 * Internal function to return a review round object from a row.
	 * @param $row array
	 * @return ReviewRound
	 */
	function _fromRow($row) {
		$reviewRound = $this->newDataObject();

		$reviewRound->setId((int)$row['review_round_id']);
		$reviewRound->setSubmissionId((int)$row['submission_id']);
		$reviewRound->setStageId((int)$row['stage_id']);
		$reviewRound->setRound((int)$row['round']);
		$reviewRound->setStatus((int)$row['status']);

		return $reviewRound;
	}
}


