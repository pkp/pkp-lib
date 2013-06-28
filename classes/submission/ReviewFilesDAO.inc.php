<?php

/**
 * @file classes/submission/ReviewFilesDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFilesDAO
 * @ingroup submission
 *
 * @brief Operations for managing review round / submission file associations.
 * These control which files are available for download by reviewers during review.
 */

class ReviewFilesDAO extends DAO {
	/**
	 * Constructor
	 */
	function ReviewFilesDAO() {
		parent::DAO();
	}

	/**
	 * Grant a review file to a review.
	 * @param $reviewId int Review assignment ID
	 * @param $fileId int Review file ID
	 */
	function grant($reviewId, $fileId) {
		$this->update(
			'INSERT INTO review_files
			(review_id, file_id)
			VALUES
			(?, ?)',
			array(
				(int) $reviewId,
				(int) $fileId
			)
		);
	}

	/**
	 * Revoke a review's association with a review file.
	 * @param $reviewId int Review assignment ID.
	 * @param $fileId int Review file ID.
	 */
	function revoke($reviewId, $fileId) {
		$this->update(
			'DELETE FROM review_files WHERE review_id = ? AND file_id = ?',
			array(
				(int) $reviewId,
				(int) $fileId
			)
		);
	}

	/**
	 * Revoke a review's association with all submission files.
	 * @param $reviewId int Review assignment ID.
	 */
	function revokeByReviewId($reviewId) {
		$this->update(
			'DELETE FROM review_files WHERE review_id = ?',
			(int) $reviewId
		);
	}

	/**
	 * Retrieve a review round
	 * @param $submissionId integer
	 * @param $stageId int One of the Stage_id_* constants.
	 * @param $round int The review round to be retrieved.
	 */
	function check($reviewId, $fileId) {
		$result = $this->retrieve(
			'SELECT * FROM review_files WHERE review_id = ? AND file_id = ?',
			array((int) $reviewId, (int) $fileId)
		);

		$returner = $result->RecordCount();
		$result->Close();
		return $returner;
	}
}

?>
