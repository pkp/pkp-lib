<?php

/**
 * @file classes/submission/ReviewFilesDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFilesDAO
 *
 * @ingroup submission
 *
 * @brief Operations for managing review round / submission file associations.
 * These control which files are available for download by reviewers during review.
 */

namespace PKP\submission;

use Illuminate\Support\Facades\DB;

class ReviewFilesDAO extends \PKP\db\DAO
{
    /**
     * Grant a review file to a review.
     *
     * @param int $reviewId Review assignment ID
     * @param int $submissionFileId Submission file ID
     */
    public function grant($reviewId, $submissionFileId)
    {
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
     *
     * @param int $reviewId Review assignment ID.
     * @param int $fileId Review file ID.
     */
    public function revoke($reviewId, $fileId)
    {
        $this->update(
            'DELETE FROM review_files WHERE review_id = ? AND file_id = ?',
            [(int) $reviewId, (int) $fileId]
        );
    }

    /**
     * Revoke a review's association with all submission files.
     *
     * @param int $reviewId Review assignment ID.
     */
    public function revokeByReviewId($reviewId)
    {
        $this->update(
            'DELETE FROM review_files WHERE review_id = ?',
            [(int) $reviewId]
        );
    }

    /**
     * Revoke a review's association based on submission file id.
     */
    public function revokeBySubmissionFileId(int $submissionFileId)
    {
        $this->update(
            'DELETE FROM review_files WHERE submission_file_id = ?',
            [(int) $submissionFileId]
        );
    }

    /**
     * Check review file availability
     *
     * @param int $reviewId
     * @param int $submissionFileId
     *
     * @return bool
     */
    public function check($reviewId, $submissionFileId)
    {
        return DB::table('review_files')
            ->where('review_id', (int) $reviewId)
            ->where('submission_file_id', (int) $submissionFileId)
            ->exists();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\ReviewFilesDAO', '\ReviewFilesDAO');
}
