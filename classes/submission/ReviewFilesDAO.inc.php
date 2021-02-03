<?php

/**
 * @file classes/submission/ReviewFilesDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFilesDAO
 * @ingroup submission
 *
 * @brief Operations for managing review round / submission file associations.
 * These control which files are available for download by reviewers during review.
 */
use Illuminate\Database\Capsule\Manager as Capsule;

class ReviewFilesDAO extends DAO {

	/**
	 * Grant a review file to a review.
	 * @param $reviewId int Review assignment ID
	 * @param $submissionFileId int Submission file ID
	 */
	function grant($reviewId, $submissionFileId) {
		$this->update(
			'INSERT INTO review_files
			(review_id, submission_file_id)
			VALUES
			(?, ?)',
			[(int) $reviewId, (int) $submissionFileId]
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
			[(int) $reviewId, (int) $fileId]
		);
	}

	/**
	 * Revoke a review's association with all submission files.
	 * @param $reviewId int Review assignment ID.
	 */
	function revokeByReviewId($reviewId) {
		$this->update(
			'DELETE FROM review_files WHERE review_id = ?',
			[(int) $reviewId]
		);
	}

	/**
	 * Check review file availability
	 * @param $reviewId integer
	 * @param $submissionFileId int
	 * @return boolean
	 */
	function check($reviewId, $submissionFileId) {
		return Capsule::table('review_files')
			->where('review_id', (int) $reviewId)
			->where('submission_file_id', (int) $submissionFileId)
			->exists();
	}
}


