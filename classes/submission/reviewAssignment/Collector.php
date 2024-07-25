<?php
/**
 * @file classes/submission/reviewAssignment/Collector.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of review assignments
 */

namespace PKP\submission\reviewAssignment;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\core\interfaces\CollectorInterface;
use PKP\submission\ViewsCount;

/**
 * @template T of ReviewAssignment
 */
class Collector implements CollectorInterface, ViewsCount
{
    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';

    public DAO $dao;
    public ?array $contextIds = null;
    public ?array $submissionIds = null;
    public bool $isIncomplete = false;
    public bool $isArchived = false;
    public bool $isOverdue = false;
    public ?array $reviewRoundIds = null;
    public ?array $reviewerIds = null;
    public bool $isLastReviewRound = false;
    public ?int $count = null;
    public ?int $offset = null;
    public ?array $reviewMethods = null;
    public ?int $stageId = null;
    public ?array $reviewFormIds = null;
    public bool $orderByContextId = false;
    public ?string $orderByContextIdDirection = null;
    public bool $orderBySubmissionId = false;
    public ?string $orderBySubmissionIdDirection = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::getCount() */
    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @copydoc DAO::getIds()
     *
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /**
     * @copydoc DAO::getMany()
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter review assignments by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): static
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter review assignments by associated submissions
     */
    public function filterBySubmissionIds(?array $submissionIds): static
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Filter by active assignments only:
     * is not completed or declines or cancelled and associated submission is in the review stage
     *
     */
    public function filterByIsIncomplete(?bool $isIncomplete): static
    {
        $this->isIncomplete = $isIncomplete;
        return $this;
    }

    /**
     * Filter by completed or declined assignments
     */
    public function filterByIsArchived(?bool $isArchived): static
    {
        $this->isArchived = $isArchived;
        return $this;
    }

    /**
     * Filter by overdue assignments
     */
    public function filterByIsOverdue(?bool $isOverdue): static
    {
        $this->isOverdue = $isOverdue;
        return $this;
    }

    /**
     * Filter by review round ids
     */
    public function filterByReviewRoundIds(?array $reviewRoundIds): static
    {
        $this->reviewRoundIds = $reviewRoundIds;
        return $this;
    }

    public function filterByReviewerIds(?array $reviewerIds): static
    {
        $this->reviewerIds = $reviewerIds;
        return $this;
    }

    public function filterByLastReviewRound(bool $isLastReviewRound): static
    {
        $this->isLastReviewRound = $isLastReviewRound;
        return $this;
    }

    /**
     * Filter by review method, one or more of the ReviewAssignment::SUBMISSION_REVIEW_METHOD_ constants
     */
    public function filterByReviewMethods(?array $reviewMethods): static
    {
        $this->reviewMethods = $reviewMethods;
        return $this;
    }

    /**
     * Filter by WORKFLOW_STAGE_ID_EXTERNAL_REVIEW or WORKFLOW_STAGE_ID_INTERNAL_REVIEW
     */
    public function filterByStageId(?int $stageId): static
    {
        $this->stageId = $stageId;
        return $this;
    }

    public function filterByReviewFormIds(?array $reviewFormIds): static
    {
        $this->reviewFormIds = $reviewFormIds;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Order/Sort the review assignments by associated context id
     */
    public function orderByContextId(string $direction = self::ORDER_DIR_ASC): static
    {
        $this->orderByContextId = true;
        $this->orderByContextIdDirection = $direction;
        return $this;
    }

    /**
     * Order/Sort the review assignments by associated submission id
     */
    public function orderBySubmissionId(string $direction = self::ORDER_DIR_ASC): static
    {
        $this->orderBySubmissionId = true;
        $this->orderBySubmissionIdDirection = $direction;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table($this->dao->table . ' as ra')
            // Aggregate the latest review round number for the given assignment
            ->leftJoinSub(
                DB::table('review_rounds', 'rr')
                    ->select('rr.submission_id')
                    ->selectRaw('MAX(rr.round) as current_round')
                    ->selectRaw('MAX(rr.stage_id) as current_stage')
                    ->groupBy('rr.submission_id'),
                'agrr',
                fn (JoinClause $join) => $join->on('ra.submission_id', '=', 'agrr.submission_id')
            )
            ->select(['ra.*']);

        $q->when(
            $this->contextIds !== null,
            fn (Builder $q) =>
            $q->whereIn(
                'ra.submission_id',
                fn (Builder $q) => $q
                    ->select('s.submission_id')
                    ->from('submissions as s')
                    ->whereIn('s.context_id', $this->contextIds)
            )
        );

        $q->when(
            $this->submissionIds !== null,
            fn (Builder $q) =>
            $q->whereIn('ra.submission_id', $this->submissionIds)
        );

        $q->when($this->isLastReviewRound || $this->isIncomplete, function (Builder $q) {
            $q
                ->whereColumn('ra.round', '=', 'agrr.current_round') // assignments from the last review round only
                ->whereColumn('ra.stage_id', '=', 'agrr.current_stage') // assignments for the current review stage only (for OMP)
                ->when(
                    $this->isIncomplete,
                    fn (Builder $q) => $q
                        ->where(
                            fn (Builder $q) => $q
                                ->whereNotNull('ra.date_notified')
                                ->whereNull('ra.date_completed')
                                ->where('ra.declined', '<>', 1)
                                ->where('ra.cancelled', '<>', 1)
                                ->whereIn(
                                    'ra.submission_id',
                                    fn (Builder $q) => $q
                                        ->select('s.submission_id')
                                        ->from('submissions AS s')
                                        ->whereColumn('s.stage_id', '=', 'ra.stage_id')
                                )
                        )
                );
        });

        $q->when(
            $this->isArchived,
            fn (Builder $q) =>
            $q->where(
                fn (Builder $q) => $q
                    ->whereNotNull('ra.date_completed')
                    ->orWhere('declined', 1)
            )
        );

        $q->when(
            $this->isOverdue,
            fn (Builder $q) => $q
                ->where(
                    fn (Builder $q) => $q
                        ->whereNull('ra.date_completed')
                        ->where('raod.declined', '<>', 1)
                        ->where('raod.cancelled', '<>', 1)
                        ->where(
                            fn (Builder $q) =>
                        $q->where('raod.date_due', '<', Core::getCurrentDate(strtotime('tomorrow')))
                            ->whereNull('raod.date_completed')
                        )
                        ->orWhere(
                            fn (Builder $q) =>
                        $q->where('raod.date_response_due', '<', Core::getCurrentDate(strtotime('tomorrow')))
                            ->whereNull('raod.date_confirmed')
                        )
                )
        );

        $q->when(
            $this->reviewRoundIds !== null,
            fn (Builder $q) =>
            $q->whereIn('ra.review_round_id', $this->reviewRoundIds)
        );

        $q->when(
            $this->reviewerIds !== null,
            fn (Builder $q) =>
            $q->whereIn('ra.reviewer_id', $this->reviewerIds)
        );

        $q->when(
            $this->reviewMethods !== null,
            fn (Builder $q) =>
            $q->whereIn('ra.review_method', $this->reviewMethods)
        );

        $q->when(
            $this->stageId !== null,
            fn (Builder $q) =>
            $q->where('ra.stage_id', $this->stageId)
        );

        $q->when(
            $this->reviewFormIds !== null,
            fn (Builder $q) =>
            $q->whereIn('ra.review_form_id', $this->reviewFormIds)
        );

        $q->when(
            $this->count !== null,
            fn (Builder $q) =>
            $q->limit($this->count)
        );

        $q->when(
            $this->offset !== null,
            fn (Builder $q) =>
            $q->offset($this->offset)
        );

        $q->when(
            $this->orderByContextId,
            fn (Builder $q) =>
            $q->orderBy(
                DB::table('submissions')
                    ->select('context_id')
                    ->whereColumn('submission_id', 'ra.submission_id'),
                $this->orderByContextIdDirection
            )
        );

        $q->when(
            $this->orderBySubmissionId,
            fn (Builder $q) =>
            $q->orderBy(
                'ra.submission_id',
                $this->orderBySubmissionIdDirection
            )
        );

        return $q;
    }

    public static function getViewsCountBuilder(Collection $keyCollectorPair): Builder
    {
        $q = DB::query();
        $keyCollectorPair->each(function (Collector $collector, string $key) use ($q) {
            // Get query builder from a collector instance, override a select statement to retrieve submissions count instead of submissions data
            $subQuery = $collector->getQueryBuilder()->select([])->selectRaw(
                'COUNT(ra.review_id)'
            );
            $q->selectSub($subQuery, $key);
        });
        return $q;
    }
}
