<?php

/**
 * @file classes/submission/ReviewFilesDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
	 * Check review file availability
	 * @param $reviewId integer
	 * @param $fileId int
	 * @return boolean
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

	/**
	 * Select Review file ids by reviewId
	 * @param $reviewId integer
	 * @return array
	 */
	function getByReviewId($reviewId) {
		$result = $this->retrieve(
			'SELECT review_id, file_id FROM review_files WHERE review_id = ?',
			array((int) $reviewId)
		);

		$fileIds = array();
		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$fileIds[] = $row['file_id'];
			$result->MoveNext();
		}
		$result->Close();
		return $fileIds;
	}
}

?>
