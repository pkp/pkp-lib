<?php
/**
 * @file classes/submission/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of submissions
 */

namespace PKP\submission;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Collector as AppCollector;
use APP\submission\Submission;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\core\interfaces\CollectorInterface;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\plugins\Hook;
use PKP\search\SubmissionSearch;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;

/**
 * @template T of Submission
 */
abstract class Collector implements CollectorInterface
{
    public const ORDERBY_DATE_PUBLISHED = 'datePublished';
    public const ORDERBY_DATE_SUBMITTED = 'dateSubmitted';
    public const ORDERBY_LAST_ACTIVITY = 'lastActivity';
    public const ORDERBY_LAST_MODIFIED = 'lastModified';
    public const ORDERBY_SEQUENCE = 'sequence';
    public const ORDERBY_TITLE = 'title';
    public const ORDERBY_SEARCH_RANKING = 'ranking';
    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';

    public const UNASSIGNED = -1;

    public DAO $dao;
    public ?array $categoryIds = null;
    public ?array $contextIds = null;
    public ?int $count = null;
    public ?int $daysInactive = null;
    public bool $isIncomplete = false;
    public bool $isOverdue = false;
    public ?int $offset = null;
    public string $orderBy = self::ORDERBY_DATE_SUBMITTED;
    public string $orderDirection = 'DESC';
    public ?string $searchPhrase = null;
    public ?int $maxSearchKeywords = null;
    public ?array $statuses = null;
    public ?array $stageIds = null;
    public ?array $doiStatuses = null;
    public ?bool $hasDois = null;
    public ?array $excludeIds = null;

    /** @var array Which DOI types should be considered when checking if a submission has DOIs set */
    public array $enabledDoiTypes = [];

    /** @var array|int */
    public $assignedTo = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /**
     * @copydoc DAO::getMany()
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Limit results to submissions in these contexts
     */
    public function filterByContextIds(?array $contextIds): AppCollector
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Limit results by submissions assigned to these categories
     */
    public function filterByCategoryIds(?array $categoryIds): AppCollector
    {
        $this->categoryIds = $categoryIds;
        return $this;
    }

    /**
     * Limit results to submissions that contain any pub objects (e.g. publication and galley) with these statuses
     *
     * @param array|null $statuses One or more of DOI::STATUS_* constants
     *
     */
    public function filterByDoiStatuses(?array $statuses): AppCollector
    {
        $this->doiStatuses = $statuses;

        return $this;
    }

    /**
     * Limit results to submissions that do/don't have any DOIs assign to their sub objects
     *
     * @param array|null $enabledDoiTypes TYPE_* constants to consider when checking submission has DOIs
     */
    public function filterByHasDois(?bool $hasDois, ?array $enabledDoiTypes = null): AppCollector
    {
        $this->hasDois = $hasDois;
        $this->enabledDoiTypes = $enabledDoiTypes === null ? [Repo::doi()::TYPE_PUBLICATION] : $enabledDoiTypes;
        return $this;
    }

    /**
     * Limit results by submissions with these statuses
     *
     * @see \PKP\submissions\PKPSubmission::STATUS_
     */
    public function filterByStatus(?array $statuses): AppCollector
    {
        $this->statuses = $statuses;
        return $this;
    }

    /**
     * Limit results by submissions in these workflow stage ids
     */
    public function filterByStageIds(?array $stageIds): AppCollector
    {
        $this->stageIds = $stageIds;
        return $this;
    }

    /**
     * Limit results to incomplete submissions
     *
     * Submissions are incomplete when the author has begun to enter
     * details about their submission but not yet submitted it.
     */
    public function filterByIncomplete(bool $isIncomplete): AppCollector
    {
        $this->isIncomplete = $isIncomplete;
        return $this;
    }

    /**
     * Limit results to submissions with overdue tasks
     */
    public function filterByOverdue(bool $isOverdue): AppCollector
    {
        $this->isOverdue = $isOverdue;
        return $this;
    }

    /**
     *  Limit results to submission with no activity for X days
     */
    public function filterByDaysInactive(?int $daysInactive): AppCollector
    {
        $this->daysInactive = $daysInactive;
        return $this;
    }

    /**
     * Limit results to submissions assigned to these users
     *
     * @param int|array $assignedTo An array of user IDs
     *  or self::UNASSIGNED to get unassigned submissions
     */
    public function assignedTo($assignedTo): AppCollector
    {
        $this->assignedTo = $assignedTo;
        return $this;
    }

    /**
     * Limit results to submissions matching this search query
     */
    public function searchPhrase(?string $phrase, ?int $maxSearchKeywords = null): AppCollector
    {
        $this->searchPhrase = $phrase;
        $this->maxSearchKeywords = $maxSearchKeywords;
        return $this;
    }

    /**
     * Ensure the given submission IDs are not included
     */
    public function excludeIds(?array $ids): AppCollector
    {
        $this->excludeIds = $ids;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): AppCollector
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): AppCollector
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Order the results
     *
     * The following column values are supported:
     *
     * - lastModified
     * - dateLastActivity
     * - title
     * - seq (sequence)
     * - DAO::ORDERBY_DATE_PUBLISHED
     *
     * Results are ordered by the date submitted by default.
     *
     * @param string $sorter One of the self::ORDERBY_ constants
     * @param string $direction One of the self::ORDER_DIR_ constants
     */
    public function orderBy(string $sorter, string $direction = self::ORDER_DIR_DESC): AppCollector
    {
        $this->orderBy = $sorter;
        $this->orderDirection = $direction;
        return $this;
    }

    /**
     * Add APP-specific filtering methods for submission sub objects DOI statuses
     *
     */
    abstract protected function addDoiStatusFilterToQuery(Builder $q);

    /**
     * Add APP-specific filtering methods for checking if submission sub objects have DOIs assigned
     */
    abstract protected function addHasDoisFilterToQuery(Builder $q);

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table('submissions AS s')
            ->leftJoin('publications AS po', 's.current_publication_id', '=', 'po.publication_id')
            ->select(['s.*']);

        // Never permit a query without a context_id unless the CONTEXT_ID_ALL wildcard
        // has been set explicitly.
        if (!isset($this->contextIds)) {
            throw new Exception('Submissions can not be retrieved without a context id. Pass the CONTEXT_ID_ALL wildcard to get submissions from any context.');
        } elseif (!in_array(Application::CONTEXT_ID_ALL, $this->contextIds)) {
            $q->whereIn('s.context_id', $this->contextIds);
        }

        // Prepare keywords (allows short and numeric words)
        $keywords = collect(Application::getSubmissionSearchIndex()->filterKeywords($this->searchPhrase, false, true, true))
            ->unique()
            ->take($this->maxSearchKeywords ?? PHP_INT_MAX);

        // Setup the order by
        switch ($this->orderBy) {
            case self::ORDERBY_DATE_PUBLISHED:
                $q->addSelect(['po.date_published']);
                $q->orderBy('po.date_published', $this->orderDirection);
                break;
            case self::ORDERBY_LAST_ACTIVITY:
                $q->orderBy('s.date_last_activity', $this->orderDirection);
                break;
            case self::ORDERBY_LAST_MODIFIED:
                $q->orderBy('s.last_modified', $this->orderDirection);
                break;
            case self::ORDERBY_SEQUENCE:
                $q->addSelect(['po.seq']);
                $q->orderBy('po.seq', $this->orderDirection);
                break;
            case self::ORDERBY_TITLE:
                $locale = Locale::getLocale();
                $q->leftJoin('publications as publication_tlp', 's.current_publication_id', '=', 'publication_tlp.publication_id')
                    ->leftJoin('publication_settings as publication_tlps', fn (JoinClause $join) =>
                        $join->on('publication_tlp.publication_id', '=', 'publication_tlps.publication_id')
                            ->where('publication_tlps.setting_name', '=', 'title')
                            ->where('publication_tlps.setting_value', '!=', '')
                            ->where('publication_tlps.locale', '=', $locale)
                    );
                $q->leftJoin('publications as publication_tlpl', 's.current_publication_id', '=', 'publication_tlpl.publication_id')
                    ->leftJoin('publication_settings as publication_tlpsl', fn (JoinClause $join) =>
                        $join->on('publication_tlp.publication_id', '=', 'publication_tlpsl.publication_id')
                            ->on('publication_tlpsl.locale', '=', 's.locale')
                            ->where('publication_tlpsl.setting_name', '=', 'title')
                    );
                $coalesceTitles = 'COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)';
                $q->addSelect([DB::raw($coalesceTitles)]);
                $q->orderBy(DB::raw($coalesceTitles), $this->orderDirection);
                break;
            case self::ORDERBY_SEARCH_RANKING:
                if (!$keywords->count()) {
                    $q->orderBy('s.date_submitted', $this->orderDirection);
                    break;
                }
                // Retrieves the number of matches for all keywords
                $orderByMatchCount = DB::table('submission_search_objects', 'sso')
                    ->join('submission_search_object_keywords AS ssok', 'ssok.object_id', '=', 'sso.object_id')
                    ->join('submission_search_keyword_list AS sskl', 'sskl.keyword_id', '=', 'ssok.keyword_id')
                    ->where(fn (Builder $q) =>
                        $keywords->map(fn (string $keyword) => $q
                            ->orWhere('sskl.keyword_text', '=', DB::raw('LOWER(?)'))
                            ->addBinding($keyword)
                        )
                    )
                    ->whereColumn('s.submission_id', '=', 'sso.submission_id')
                    ->selectRaw('COUNT(0)');
                // Retrieves the number of distinct matched keywords
                $orderByDistinctKeyword = (clone $orderByMatchCount)->select(DB::raw('COUNT(DISTINCT sskl.keyword_id)'));
                $q->orderBy($orderByDistinctKeyword, $this->orderDirection)
                    ->orderBy($orderByMatchCount, $this->orderDirection);
                break;
            case self::ORDERBY_DATE_SUBMITTED:
            default:
                $q->orderBy('s.date_submitted', $this->orderDirection);
                break;
        }

        if (isset($this->statuses)) {
            $q->whereIn('s.status', $this->statuses);
        }

        if (isset($this->stageIds)) {
            $q->whereIn('s.stage_id', $this->stageIds);
        }

        if ($this->isIncomplete) {
            $q->where('s.submission_progress', '<>', '');
        }

        if (isset($this->daysInactive)) {
            $q->where('s.date_last_activity', '<', Core::getCurrentDate(strtotime('-' . $this->daysInactive . ' days')));
        }

        if ($this->isOverdue) {
            $q->leftJoin('review_assignments as raod', 'raod.submission_id', '=', 's.submission_id')
                ->leftJoin('review_rounds as rr', fn (Builder $table) =>
                    $table->on('rr.submission_id', '=', 's.submission_id')
                        ->on('raod.review_round_id', '=', 'rr.review_round_id')
                );
            // Only get overdue assignments on active review rounds
            $q->whereNotIn('rr.status', [
                ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW,
                ReviewRound::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL,
                ReviewRound::REVIEW_ROUND_STATUS_ACCEPTED,
                ReviewRound::REVIEW_ROUND_STATUS_DECLINED,
            ]);
            $q->where(fn (Builder $q) =>
                $q->where('raod.declined', '<>', 1)
                    ->where('raod.cancelled', '<>', 1)
                    ->where(fn (Builder $q) =>
                        $q->where('raod.date_due', '<', Core::getCurrentDate(strtotime('tomorrow')))
                            ->whereNull('raod.date_completed')
                    )
                    ->orWhere(fn (Builder $q) =>
                        $q->where('raod.date_response_due', '<', Core::getCurrentDate(strtotime('tomorrow')))
                        ->whereNull('raod.date_confirmed')
                    )
            );
        }

        if (is_array($this->assignedTo)) {
            $q->whereIn('s.submission_id', fn (Builder $q) =>
                $q->select('s.submission_id')
                    ->from('submissions AS s')
                    ->leftJoin('stage_assignments as sa', fn (Builder $q) =>
                        $q->on('s.submission_id', '=', 'sa.submission_id')
                            ->whereIn('sa.user_id', $this->assignedTo)
                    )
                    ->leftJoin('review_assignments as ra', fn (Builder $table) =>
                        $table->on('s.submission_id', '=', 'ra.submission_id')
                            ->where('ra.declined', '=', (int) 0)
                            ->where('ra.cancelled', '=', (int) 0)
                            ->whereIn('ra.reviewer_id', $this->assignedTo)
                    )
                    ->whereNotNull('sa.stage_assignment_id')
                    ->orWhereNotNull('ra.review_id')
            );
        } elseif ($this->assignedTo === self::UNASSIGNED) {
            $sub = DB::table('stage_assignments')
                ->select(DB::raw('count(stage_assignments.stage_assignment_id)'))
                ->leftJoin('user_groups', 'stage_assignments.user_group_id', '=', 'user_groups.user_group_id')
                ->where('stage_assignments.submission_id', '=', DB::raw('s.submission_id'))
                ->whereIn('user_groups.role_id', [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

            $q->whereNotNull('s.date_submitted')
                ->mergeBindings($sub)
                ->where(DB::raw('(' . $sub->toSql() . ')'), '=', '0');
        }

        // Search phrase
        if ($keywords->count()) {
            $likePattern = DB::raw("CONCAT('%', LOWER(?), '%')");
            if(!empty($this->assignedTo)) {
                // Holds a single random row to check whether we have any assignment
                $q->leftJoinSub(fn (Builder $q) => $q
                    ->from('review_assignments', 'ra')
                    ->whereIn('ra.reviewer_id', $this->assignedTo == self::UNASSIGNED ? [] : (array) $this->assignedTo)
                    ->select(DB::raw('1 AS value'))
                    ->limit(1),
                    'any_assignment', 'any_assignment.value', '=', DB::raw('1')
                );
            }
            // Builds the filters
            $q->where(fn (Builder $q) => $keywords
                ->map(fn (string $keyword) => $q
                    // Look for matches on the indexed data
                    ->orWhereExists(fn (Builder $query) => $query
                        ->from('submission_search_objects', 'sso')
                        ->join('submission_search_object_keywords AS ssok', 'sso.object_id', '=', 'ssok.object_id')
                        ->join('submission_search_keyword_list AS sskl', 'sskl.keyword_id', '=', 'ssok.keyword_id')
                        ->where('sskl.keyword_text', '=', DB::raw('LOWER(?)'))->addBinding($keyword)
                        ->whereColumn('s.submission_id', '=', 'sso.submission_id')
                        // Don't permit reviewers to search on author names
                        ->when(!empty($this->assignedTo), fn (Builder $q) => $q
                            ->where(fn (Builder $q) => $q
                                ->whereNull('any_assignment.value')
                                ->orWhere('sso.type', '!=', SubmissionSearch::SUBMISSION_SEARCH_AUTHOR)
                            )
                        )
                    )
                    // Search on the publication title
                    ->orWhereIn('s.submission_id', fn (Builder $query) => $query
                        ->select('p.submission_id')->from('publications AS p')
                        ->join('publication_settings AS ps', 'p.publication_id', '=', 'ps.publication_id')
                        ->where('ps.setting_name', '=', 'title')
                        ->where(DB::raw('LOWER(ps.setting_value)'), 'LIKE', $likePattern)
                            ->addBinding($keyword)
                    )
                    // Search on the author name and ORCID
                    ->orWhereIn('s.submission_id', fn (Builder $query) => $query
                        ->select('p.submission_id')
                        ->from('publications AS p')
                        ->join('authors AS au', 'au.publication_id', '=', 'p.publication_id')
                        ->join('author_settings AS aus', 'aus.author_id', '=', 'au.author_id')
                        ->whereIn('aus.setting_name', [
                            Identity::IDENTITY_SETTING_GIVENNAME,
                            Identity::IDENTITY_SETTING_FAMILYNAME,
                            'orcid'
                        ])
                        // Don't permit reviewers to search on author names
                        ->when(!empty($this->assignedTo), fn (Builder $q) => $q
                            ->where(fn (Builder $q) => $q
                                ->whereNull('any_assignment.value')
                                ->orWhereNotIn('aus.setting_name', [
                                    Identity::IDENTITY_SETTING_GIVENNAME,
                                    Identity::IDENTITY_SETTING_FAMILYNAME
                                ])
                            )
                        )
                        ->where(DB::raw('LOWER(aus.setting_value)'), 'LIKE', $likePattern)
                            ->addBinding($keyword)
                    )
                    // Search for the exact submission ID
                    ->when(
                        ($numericWords = $keywords->filter(fn (string $keyword) => ctype_digit($keyword)))->count(),
                        fn (Builder $query) => $query->orWhereIn('s.submission_id', $numericWords)
                    )
                )
            );
        } elseif (strlen($this->searchPhrase ?? '')) {
            // If there's search text, but no keywords could be extracted from it, force the query to return nothing
            $q->whereRaw('1 = 0');
        }

        if (isset($this->categoryIds)) {
            $q->join('publication_categories as pc', 's.current_publication_id', '=', 'pc.publication_id')
                ->whereIn('pc.category_id', $this->categoryIds);
        }

        // Filter by any child pub object's DOI status
        $q->when($this->doiStatuses !== null, fn (Builder $q) => $this->addDoiStatusFilterToQuery($q));

        // Filter by whether any child pub objects have DOIs assigned
        $q->when($this->hasDois !== null, fn (Builder $q) => $this->addHasDoisFilterToQuery($q));

        // Filter out excluded submission IDs
        $q->when($this->excludeIds !== null, fn (Builder $q) => $q->whereNotIn('s.submission_id', $this->excludeIds));

        // Limit and offset results for pagination
        if (isset($this->count)) {
            $q->limit($this->count);
        }
        if (isset($this->offset)) {
            $q->offset($this->offset);
        }

        // Add app-specific query statements
        Hook::call('Submission::Collector', [&$q, $this]);

        return $q;
    }
}
