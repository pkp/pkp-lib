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
use APP\publication\Publication;
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
        'reviewerRecommendationId' => 'reviewer_recommendation_id',
        'dateAssigned' => 'date_assigned',
        'dateNotified' => 'date_notified',
        'dateConfirmed' => 'date_confirmed',
        'dateCompleted' => 'date_completed',
        'dateAcknowledged' => 'date_acknowledged',
        'dateDue' => 'date_due',
        'dateResponseDue' => 'date_response_due',
        'doiId' => 'doi_id',
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
        'dateConsidered' => 'date_considered',
        'requestResent' => 'request_resent',
        'isReviewPubliclyVisible' => 'is_review_publicly_visible',
    ];

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'review_assignment_settings';
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
        return LazyCollection::make(function () use ($query) {
            $rows = $query
                ->getQueryBuilder()
                ->get();

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
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId(), true);
        $reviewAssignment->setData(
            'reviewerFullName',
            $reviewer->getFullName()
        );
        $reviewAssignment->setData(
            'reviewerUserName',
            $reviewer->getUserName()
        );

        if (!empty($reviewAssignment->getData('doiId'))) {
            $reviewAssignment->setData(
                'doiObject',
                Repo::doi()->get($reviewAssignment->getData('doiId'))
            );
        }

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

    /**
     * Get all review IDs for which DOIs can be exported.
     * If the same DOI is used for all publication versions: the current publication of the submission the review was assigned to needs to have a DOI and is published.
     * If different DOIs are used for different publication versions, the publication the review is linked to must have a DOI and is published.
     * Additionally, a review must be publicly visible and completed to be eligible for DOI export.
     * @param int $contextId
     * @param bool $doiVersioning - whether different doi is used per publication version.
     * @param array|null $submissionIds - Optional submission IDs to limit the results to.
     * @return array
     */
    public function getExportableDOIsPeerReviewIds(int $contextId, bool $doiVersioning, ?array $submissionIds = null): array
    {
        return DB::table($this->table)
            ->join('submissions', 'submissions.submission_id', '=', 'review_assignments.submission_id')
            ->when($submissionIds, fn(Builder $q) => $q->whereIn('submissions.submission_id', $submissionIds))
            ->whereNotNull('review_assignments.doi_id')
            ->whereNotNull('review_assignments.date_completed')
            ->where('submissions.context_id', $contextId)
            ->where('is_review_publicly_visible', true)
            ->when(
                // When single DOI is used for all publication versions then ensure the current version is published and has a DOI.
                // When depositing the review, it will be linked to that DOI that is used for all versions instead of a version-specific DOI.
                !$doiVersioning,
                fn(Builder $q) => $q
                    ->join('publications', 'publications.publication_id', '=', 'submissions.current_publication_id')
                    ->whereNotNull('publications.doi_id'),
                // When not using single DOI for all publication versions, ensure that the publication that the review is linked with has been published
                fn(Builder $q) => $q->whereNotNull('publications.doi_id')
                    ->join('review_rounds', 'review_rounds.review_round_id', '=', 'review_assignments.review_round_id')
                    ->join('publications', 'publications.publication_id', '=', 'review_rounds.publication_id')
            )
            ->where('publications.status', '=', Publication::STATUS_PUBLISHED)
            ->pluck('review_assignments.review_id')
            ->toArray();
    }
}
