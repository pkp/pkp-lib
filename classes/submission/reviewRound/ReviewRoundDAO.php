<?php

/**
 * @file classes/submission/reviewRound/ReviewRoundDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundDAO
 *
 * @ingroup submission_reviewRound
 *
 * @see ReviewRound
 *
 * @brief Operations for retrieving and modifying ReviewRound objects.
 */

namespace PKP\submission\reviewRound;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\db\DAOResultFactory;

class ReviewRoundDAO extends \PKP\db\DAO
{
    //
    // Public methods
    //
    /**
     * Fetch a review round, creating it if needed.
     *
     * @param int $submissionId
     * @param int $stageId One of the WORKFLOW_*_REVIEW_STAGE_ID constants.
     * @param int $round
     * @param int $status One of the ReviewRound::REVIEW_ROUND_STATUS_* constants.
     *
     * @return ?ReviewRound
     */
    public function build($submissionId, $stageId, $round, $status = null)
    {
        // If one exists, fetch and return.
        $reviewRound = $this->getReviewRound($submissionId, $stageId, $round);
        if ($reviewRound) {
            return $reviewRound;
        }

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
     *
     * @return ReviewRound
     */
    public function newDataObject()
    {
        return new ReviewRound();
    }

    /**
     * Insert a new review round.
     *
     * @param ReviewRound $reviewRound
     *
     * @return ReviewRound
     */
    public function insertObject($reviewRound)
    {
        $this->update(
            'INSERT INTO review_rounds
				(submission_id, stage_id, round, status)
				VALUES
				(?, ?, ?, ?)',
            [
                (int)$reviewRound->getSubmissionId(),
                (int)$reviewRound->getStageId(),
                (int)$reviewRound->getRound(),
                (int)$reviewRound->getStatus()
            ]
        );
        return $reviewRound;
    }

    /**
     * Update an existing review round.
     *
     * @param ReviewRound $reviewRound
     *
     * @return bool
     */
    public function updateObject($reviewRound)
    {
        $returner = $this->update(
            'UPDATE	review_rounds
			SET	status = ?
			WHERE	submission_id = ? AND
				stage_id = ? AND
				round = ?',
            [
                (int)$reviewRound->getStatus(),
                (int)$reviewRound->getSubmissionId(),
                (int)$reviewRound->getStageId(),
                (int)$reviewRound->getRound()
            ]
        );
        return $returner;
    }

    /**
     * Retrieve a review round
     *
     * @param int $submissionId
     * @param int $stageId One of the Stage_id_* constants.
     * @param int $round The review round to be retrieved.
     *
     * @return ?ReviewRound
     */
    public function getReviewRound($submissionId, $stageId, $round)
    {
        $result = $this->retrieve(
            'SELECT * FROM review_rounds WHERE submission_id = ? AND stage_id = ? AND round = ?',
            [(int) $submissionId, (int) $stageId, (int) $round]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a review round by its id.
     *
     * @param int $reviewRoundId
     *
     * @return ReviewRound
     */
    public function getById($reviewRoundId)
    {
        $result = $this->retrieve(
            'SELECT * FROM review_rounds WHERE review_round_id = ?',
            [(int) $reviewRoundId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a review round by a submission file id.
     *
     * @param int $submissionFileId
     *
     * @return ReviewRound
     */
    public function getBySubmissionFileId($submissionFileId)
    {
        $result = $this->retrieve(
            'SELECT * FROM review_rounds rr
				INNER JOIN review_round_files rrf
				ON rr.review_round_id = rrf.review_round_id
				WHERE rrf.submission_file_id = ?',
            [(int) $submissionFileId]
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Get an iterator of review round objects associated with this submission
     *
     * @param int $submissionId
     * @param int $stageId (optional)
     * @param int $round (optional)
     *
     * @return DAOResultFactory<ReviewRound>
     */
    public function getBySubmissionId($submissionId, $stageId = null, $round = null)
    {
        $q = DB::table('review_rounds', 'rr')
            ->where('rr.submission_id', '=', $submissionId)
            ->when($stageId, fn (Builder $q) => $q->where('rr.stage_id', '=', $stageId))
            ->when($round, fn (Builder $q) => $q->where('rr.round', '=', $round))
            ->orderBy('rr.stage_id')
            ->orderBy('rr.round');

        return new DAOResultFactory($q->get(), $this, '_fromRow', [], $q);
    }

    /**
     * Get the current review round for a given stage (or for the latest stage)
     *
     * @param int $submissionId
     * @param int $stageId
     *
     * @return int
     */
    public function getCurrentRoundBySubmissionId($submissionId, $stageId = null)
    {
        $params = [(int)$submissionId];
        if ($stageId) {
            $params[] = (int) $stageId;
        }
        $result = $this->retrieve(
            'SELECT MAX(stage_id) as stage_id, MAX(round) as round
			FROM review_rounds
			WHERE submission_id = ?' .
            ($stageId ? ' AND stage_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? (int) $row->round : 1;
    }

    /**
     * Get the last review round for a give stage (or for the latest stage)
     *
     * @param int $submissionId
     * @param int $stageId
     *
     * @return ?ReviewRound
     */
    public function getLastReviewRoundBySubmissionId($submissionId, $stageId = null)
    {
        $params = [(int)$submissionId];
        if ($stageId) {
            $params[] = (int) $stageId;
        }
        $result = $this->retrieve(
            'SELECT	*
			FROM	review_rounds
			WHERE	submission_id = ?
			' . ($stageId ? ' AND stage_id = ?' : '') . '
			ORDER BY stage_id DESC, round DESC',
            $params
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Check if submission has a review round (in the given stage id)
     */
    public function submissionHasReviewRound(int $submissionId, ?int $stageId = null): bool
    {
        $params = [(int)$submissionId];
        if ($stageId) {
            $params[] = (int) $stageId;
        }
        $result = $this->retrieve(
            'SELECT	review_round_id
			FROM	review_rounds
			WHERE	submission_id = ?
			' . ($stageId ? ' AND stage_id = ?' : ''),
            $params
        );
        return (bool) $result->current();
    }

    /**
     * Update the review round status.
     *
     * @param ReviewRound $reviewRound
     * @param ?int $status Optionally pass a ReviewRound::REVIEW_ROUND_STATUS_... to set a
     *  specific status. If not included, will determine the appropriate status
     *  based on ReviewRound::determineStatus().
     */
    public function updateStatus($reviewRound, $status = null)
    {
        assert($reviewRound instanceof ReviewRound);
        $currentStatus = $reviewRound->getStatus();

        if (is_null($status)) {
            $status = $reviewRound->determineStatus();
        }

        // Avoid unnecessary database access.
        if ($status != $currentStatus) {
            $this->update(
                'UPDATE review_rounds SET status = ? WHERE review_round_id = ?',
                [(int)$status, (int)$reviewRound->getId()]
            );
            // Update the data in object too.
            $reviewRound->setStatus($status);
        }
    }

    /**
     * Delete review rounds by submission ID.
     *
     * @param int $submissionId
     */
    public function deleteBySubmissionId($submissionId)
    {
        $reviewRounds = $this->getBySubmissionId($submissionId);
        while ($reviewRound = $reviewRounds->next()) {
            $this->deleteObject($reviewRound);
        }
    }

    /**
     * Delete all review rounds for submissions within a given context ID.
     *
     */
    public function deleteByContextId(int $contextId): void
    {
        DB::table('review_rounds')
            ->join('submissions', 'review_rounds.submission_id', '=', 'submissions.submission_id')
            ->where('submissions.context_id', $contextId)
            ->delete();
    }

    /**
     * Delete a review round.
     *
     * @param ReviewRound $reviewRound
     */
    public function deleteObject($reviewRound)
    {
        $this->deleteById($reviewRound->getId());
    }

    /**
     * Delete a review round by ID.
     */
    public function deleteById(int $reviewRoundId): int
    {
        DB::table('notifications')
            ->where('assoc_type', '=', Application::ASSOC_TYPE_REVIEW_ROUND)
            ->where('assoc_id', '=', $reviewRoundId)
            ->delete();

        return DB::table('review_rounds')
            ->where('review_round_id', '=', $reviewRoundId)
            ->delete();
    }

    //
    // Private methods
    //
    /**
     * Internal function to return a review round object from a row.
     *
     * @param array $row
     *
     * @return ReviewRound
     */
    public function _fromRow($row)
    {
        $reviewRound = $this->newDataObject();

        $reviewRound->setId((int)$row['review_round_id']);
        $reviewRound->setSubmissionId((int)$row['submission_id']);
        $reviewRound->setStageId((int)$row['stage_id']);
        $reviewRound->setRound((int)$row['round']);
        $reviewRound->setStatus((int)$row['status']);

        return $reviewRound;
    }

    /**
     * Get the number of review rounds in a submission
     *
     * @param  int $submissionId  Submission id for which review round count need to be determined
     * @param  int $stageId  Review stage id for which review round count need to be determined
     *
     * @throws \Exception
     *
     * @return int      Number of internal review round associated with this submission
     *
     */
    public function getReviewRoundCountBySubmissionId(int $submissionId, ?int $stageId = null)
    {
        if (!is_null($stageId) && !in_array($stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
            throw new \Exception('Not a valid review stage');
        }

        return DB::table('review_rounds')
            ->where('submission_id', $submissionId)
            ->when(!is_null($stageId), fn ($query) => $query->where('stage_id', $stageId))
            ->getCountForPagination();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewRound\ReviewRoundDAO', '\ReviewRoundDAO');
}
