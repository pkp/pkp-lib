<?php
/**
 * @file classes/submission/reviewAssignment/DAO.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write review assignments to the database.
 */

namespace PKP\submission\reviewAssignment;

use APP\facades\Repo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;

/**
 * @template T of ReviewAssignment
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_REVIEW_ASSIGNMENT;

    /** @copydoc EntityDAO::$table */
    public $table = 'review_assignments';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'review_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'review_id',
        'submissionId' => 'submission_id',
        'reviewerId' => 'reviewer_id',
        'competingInterests' => 'competing_interests',
        'recommendation' => 'recommendation',
        'dateAssigned' => 'date_assigned',
        'dateNotified' => 'date_notified',
        'dateConfirmed' => 'date_confirmed',
        'dateCompleted' => 'date_completed',
        'dateAcknowledged' => 'date_acknowledged',
        'dateDue' => 'date_due',
        'dateResponseDue' => 'date_response_due',
        'lastModified' => 'last_modified',
        'reminderWasAutomatic' => 'reminder_was_automatic',
        'declined' => 'declined',
        'cancelled' => 'cancelled',
        'dateCancelled' => 'date_cancelled',
        'dateRated' => 'date_rated',
        'dateReminded' => 'date_reminded',
        'quality' => 'quality',
        'reviewRoundId' => 'review_round_id',
        'stageId' => 'stage_id',
        'reviewMethod' => 'review_method',
        'round' => 'round',
        'step' => 'step',
        'reviewFormId' => 'review_form_id',
        'considered' => 'considered',
        'requestResent' => 'request_resent',
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): ReviewAssignment
    {
        return app(ReviewAssignment::class);
    }

    /**
     * Check if a review assignment exists
     */
    public function exists(int $id, ?int $submissionId): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->when($submissionId !== null, fn (Builder $query) => $query->where('submission_id', $submissionId))
            ->exists();
    }

    /**
     * Get a review assignment
     */
    public function get(int $id, ?int $submissionId = null): ?ReviewAssignment
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->when($submissionId !== null, fn (Builder $query) => $query->where('submission_id', $submissionId))
            ->first();

        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of review assignments matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->get('ra.' . $this->primaryKeyColumn)
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->pluck('ra.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of review assignments matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->review_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): ReviewAssignment
    {
        $reviewAssignment = parent::fromRow($row);
        $reviewAssignment->setData(
            'reviewerFullName',
            Repo::user()->get($reviewAssignment->getReviewerId())->getFullName()
        );

        return $reviewAssignment;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(ReviewAssignment $reviewAssignment): int
    {
        return parent::_insert($reviewAssignment);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(ReviewAssignment $reviewAssignment)
    {
        parent::_update($reviewAssignment);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(ReviewAssignment $reviewAssignment)
    {
        parent::_delete($reviewAssignment);
    }

    /**
     * Get IDs of the external reviewers that have completed a reivew for the given context in the given year.
     *
     * @return Collection<int,int>
     */
    public function getExternalReviewerIdsByCompletedYear(int $contextId, string $year): Collection
    {
        return DB::table($this->table)
            ->whereIn(
                'submission_id',
                fn (Builder $q) => $q
                    ->select('s.submission_id')
                    ->from('submissions as s')
                    ->where('s.context_id', $contextId)
            )
            ->whereYear('date_completed', $year)
            ->where('stage_id', '=', WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
            ->pluck('reviewer_id');
    }
}
