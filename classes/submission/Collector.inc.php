<?php
/**
 * @file classes/submission/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A helper class to configure a Query Builder to get a collection of submissions
 */

namespace PKP\submission;

use APP\core\Application;
use APP\i18n\AppLocale;
use APP\submission\Collector as AppCollector;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use PKP\core\Core;
use PKP\core\interfaces\CollectorInterface;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;

abstract class Collector implements CollectorInterface
{
    public const ORDERBY_DATE_PUBLISHED = 'datePublished';
    public const ORDERBY_DATE_SUBMITTED = 'dateSubmitted';
    public const ORDERBY_LAST_ACTIVITY = 'lastActivity';
    public const ORDERBY_LAST_MODIFIED = 'lastModified';
    public const ORDERBY_SEQUENCE = 'sequence';
    public const ORDERBY_TITLE = 'title';
    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';

    /** @var DAO */
    public $dao;

    /** @var array|int */
    public $assignedTo = null;

    /** @var array|null */
    public $categoryIds = null;

    /** @var array|null */
    public $contextIds = null;

    /** @var int */
    public $count = 30;

    /** @var int */
    public $daysInactive;

    /** @var bool */
    public $isIncomplete = false;

    /** @var bool */
    public $isOverdue = false;

    /** @var int */
    public $offset = 0;

    /** @var string */
    public $orderBy = self::ORDERBY_DATE_SUBMITTED;

    /** @var string */
    public $orderDirection = 'DESC';

    /** @var string */
    public $searchPhrase = '';

    /** @var array|null */
    public $statuses = null;

    /** @var array|null */
    public $stageIds = null;

    /** @var array list of columns to select with query */
    protected $columns = [];

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Limit results to submissions in these contexts
     */
    public function filterByContextIds(array $contextIds): AppCollector
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Limit results by submissions assigned to these categories
     */
    public function filterByCategoryIds(array $categoryIds): AppCollector
    {
        $this->categoryIds = $categoryIds;
        return $this;
    }

    /**
     * Limit results by submissions with these statuses
     *
     * @see \PKP\submissions\PKPSubmission::STATUS_
     */
    public function filterByStatus(array $statuses): AppCollector
    {
        $this->statuses = $statuses;
        return $this;
    }

    /**
     * Limit results by submissions in these workflow stage ids
     */
    public function filterByStageIds(array $stageIds): AppCollector
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
    public function filterByDaysInactive(int $daysInactive): AppCollector
    {
        $this->daysInactive = $daysInactive;
        return $this;
    }

    /**
     * Limit results to submissions assigned to these users
     *
     * @param int|array $assignedTo An array of user IDs
     *  or -1 to get unassigned submissions
     */
    public function assignedTo($assignedTo): AppCollector
    {
        $this->assignedTo = $assignedTo;
        return $this;
    }

    /**
     * Limit results to submissions matching this search query
     */
    public function searchPhrase(string $phrase): AppCollector
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): AppCollector
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): AppCollector
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
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $this->columns[] = 's.*';
        $q = DB::table('submissions as s')
            ->groupBy('s.submission_id');

        // Never permit a query without a context_id unless the CONTEXT_ID_ALL wildcard
        // has been set explicitly.
        if (!is_array($this->contextIds)) {
            throw new Exception('Submissions can not be retrieved without a context id. Pass the CONTEXT_ID_ALL wildcard to get submissions from any context.');
        } elseif (!in_array(Application::CONTEXT_ID_ALL, $this->contextIds)) {
            $q->whereIn('s.context_id', $this->contextIds);
        }

        switch ($this->orderBy) {
            case self::ORDERBY_DATE_PUBLISHED:
                $this->columns[] = 'po.date_published';
                $q->leftJoin('publications as po', 's.current_publication_id', '=', 'po.publication_id');
                $q->orderBy('po.date_published', $this->orderDirection);
                $q->groupBy('po.date_published');
                break;
            case self::ORDERBY_LAST_ACTIVITY:
                $q->orderBy('s.date_last_activity', $this->orderDirection);
                break;
            case self::ORDERBY_LAST_MODIFIED:
                $q->orderBy('s.last_modified', $this->orderDirection);
                break;
            case self::ORDERBY_SEQUENCE:
                $this->columns[] = 'po.seq';
                $q->leftJoin('publications as po', 's.current_publication_id', '=', 'po.publication_id');
                $q->orderBy('po.seq', $this->orderDirection);
                $q->groupBy('po.seq');
                break;
            case self::ORDERBY_TITLE:
                $locale = AppLocale::getLocale();
                $q->leftJoin('publications as publication_tlp', 's.current_publication_id', '=', 'publication_tlp.publication_id')
                    ->leftJoin('publication_settings as publication_tlps', 'publication_tlp.publication_id', '=', 'publication_tlps.publication_id')
                    ->where('publication_tlps.setting_name', '=', 'title')
                    ->where('publication_tlps.locale', '=', $locale);
                $q->leftJoin('publications as publication_tlpl', 's.current_publication_id', '=', 'publication_tlpl.publication_id')
                    ->leftJoin('publication_settings as publication_tlpsl', 'publication_tlp.publication_id', '=', 'publication_tlpsl.publication_id')
                    ->where('publication_tlpsl.setting_name', '=', 'title')
                    ->where('publication_tlpsl.locale', '=', DB::raw('s.locale'));

                $coalesceTitles = 'COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)';
                $this->columns[] = DB::raw($coalesceTitles);
                $q->orderBy(DB::raw($coalesceTitles), $this->orderDirection);
                $q->groupBy(DB::raw($coalesceTitles));
                break;
            case self::ORDERBY_DATE_SUBMITTED:
            default:
                $q->orderBy('s.date_submitted', $this->orderDirection);
                break;
        }

        if (is_array($this->statuses)) {
            $q->whereIn('s.status', $this->statuses);
        }

        if (is_array($this->stageIds)) {
            $q->whereIn('s.stage_id', $this->stageIds);
        }

        if (!empty($this->isIncomplete)) {
            $q->where('s.submission_progress', '>', 0);
        }

        if (!empty($this->daysInactive)) {
            $q->where('s.date_last_activity', '<', Core::getCurrentDate(strtotime('-' . $this->daysInactive . ' days')));
        }

        if (!empty($this->isOverdue)) {
            $q->leftJoin('review_assignments as raod', 'raod.submission_id', '=', 's.submission_id')
                ->leftJoin('review_rounds as rr', function ($table) {
                    $table->on('rr.submission_id', '=', 's.submission_id');
                    $table->on('raod.review_round_id', '=', 'rr.review_round_id');
                });
            // Only get overdue assignments on active review rounds
            import('lib.pkp.classes.submission.reviewRound.ReviewRound');
            $q->where('rr.status', '!=', REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW);
            $q->where('rr.status', '!=', REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL);
            $q->where('rr.status', '!=', REVIEW_ROUND_STATUS_ACCEPTED);
            $q->where('rr.status', '!=', REVIEW_ROUND_STATUS_DECLINED);
            $q->where(function ($q) {
                $q->where('raod.declined', '<>', 1);
                $q->where('raod.cancelled', '<>', 1);
                $q->where(function ($q) {
                    $q->where('raod.date_due', '<', \Core::getCurrentDate(strtotime('tomorrow')));
                    $q->whereNull('raod.date_completed');
                });
                $q->orWhere(function ($q) {
                    $q->where('raod.date_response_due', '<', \Core::getCurrentDate(strtotime('tomorrow')));
                    $q->whereNull('raod.date_confirmed');
                });
            });
        }

        $isAssignedOnly = is_array($this->assignedTo);
        if ($isAssignedOnly) {
            $q->leftJoin('stage_assignments as sa', function ($table) {
                $table->on('s.submission_id', '=', 'sa.submission_id');
                $table->whereIn('sa.user_id', $this->assignedTo);
            });

            $q->leftJoin('review_assignments as ra', function ($table) {
                $table->on('s.submission_id', '=', 'ra.submission_id');
                $table->on('ra.declined', '=', DB::raw((int) 0));
                $table->on('ra.cancelled', '=', DB::raw((int) 0));
                $table->whereIn('ra.reviewer_id', $this->assignedTo);
            });

            $q->where(function ($q) {
                $q->whereNotNull('sa.stage_assignment_id');
                $q->orWhereNotNull('ra.review_id');
            });
        } elseif ($this->assignedTo === -1) {
            $sub = DB::table('stage_assignments')
                ->select(DB::raw('count(stage_assignments.stage_assignment_id)'))
                ->leftJoin('user_groups', 'stage_assignments.user_group_id', '=', 'user_groups.user_group_id')
                ->where('stage_assignments.submission_id', '=', DB::raw('s.submission_id'))
                ->whereIn('user_groups.role_id', [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]);

            $q->whereNotNull('s.date_submitted')
                ->mergeBindings($sub)
                ->where(DB::raw('(' . $sub->toSql() . ')'), '=', '0')
                ->groupBy('s.date_submitted'); // postgres compatibility
        }

        // search phrase
        if (!empty($this->searchPhrase)) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                $q->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
                    ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
                    ->leftJoin('authors as au', 'p.publication_id', '=', 'au.publication_id')
                    ->leftJoin('author_settings as aus', 'aus.author_id', '=', 'au.author_id');

                foreach ($words as $word) {
                    $word = strtolower(addcslashes($word, '%_'));
                    $q->where(function ($q) use ($word, $isAssignedOnly) {
                        $q->where(function ($q) use ($word) {
                            $q->where('ps.setting_name', 'title');
                            $q->where(DB::raw('lower(ps.setting_value)'), 'LIKE', "%{$word}%");
                        })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('aus.setting_name', Identity::IDENTITY_SETTING_GIVENNAME);
                                $q->where(DB::raw('lower(aus.setting_value)'), 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('aus.setting_name', Identity::IDENTITY_SETTING_FAMILYNAME);
                                $q->where(DB::raw('lower(aus.setting_value)'), 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('aus.setting_name', 'orcid');
                                $q->where(DB::raw('lower(aus.setting_value)'), '=', "{$word}");
                            });
                        // Prevent reviewers from matching searches by author name
                        if ($isAssignedOnly) {
                            $q->whereNull('ra.reviewer_id');
                        }
                        if (ctype_digit((string) $word)) {
                            $q->orWhere('s.submission_id', '=', $word);
                        }
                    });
                }
            }
        }

        if (is_array($this->categoryIds)) {
            $q->leftJoin('publication_categories as pc', 's.current_publication_id', '=', 'pc.publication_id')
                ->whereIn('pc.category_id', $this->categoryIds);
        }

        // Limit and offset results for pagination
        if (!is_null($this->count)) {
            $q->limit($this->count);
        }
        if (!is_null($this->offset)) {
            $q->offset($this->offset);
        }

        // Add app-specific query statements
        HookRegistry::call('Submission::getMany::queryObject', [&$q, $this]);

        $q->select($this->columns);

        return $q;
    }
}
