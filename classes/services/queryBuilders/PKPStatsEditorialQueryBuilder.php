<?php

/**
 * @file classes/services/QueryBuilders/PKPStatsEditorialQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialQueryBuilder
 *
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics table.
 */

namespace PKP\services\queryBuilders;

use APP\facades\Repo;
use PKP\publication\PKPPublication;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\decision\DecisionType;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

abstract class PKPStatsEditorialQueryBuilder
{
    /** @var array Return stats for activity in these contexts */
    protected $contextIds = [];

    /** @var string Return stats for activity before this date */
    protected $dateEnd;

    /** @var string Return stats for activity after this date */
    protected $dateStart;

    /** @var array Return stats for activity in these sections (series in OMP) */
    protected $sectionIds = [];

    /** @var string The table column name for section IDs (OJS) or series IDs (OMP) */
    public $sectionIdsColumn;

    /**
     * Set the contexts to return activity for
     *
     * @param array|int $contextIds
     *
     * @return \PKP\services\queryBuilders\PKPStatsEditorialQueryBuilder
     */
    public function filterByContexts($contextIds)
    {
        $this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
        return $this;
    }

    /**
     * Set the section ids to include activity for. This is stored under
     * the section_id db column but in OMP refers to seriesIds.
     *
     * @param array|int $sectionIds
     *
     * @return \PKP\services\queryBuilders\PKPStatsEditorialQueryBuilder
     */
    public function filterBySections($sectionIds)
    {
        $this->sectionIds = is_array($sectionIds) ? $sectionIds : [$sectionIds];
        return $this;
    }

    /**
     * Set the date to get activity before
     *
     * @param string $dateEnd YYYY-MM-DD
     *
     * @return \PKP\services\queryBuilders\PKPStatsEditorialQueryBuilder
     */
    public function before($dateEnd)
    {
        $this->dateEnd = $dateEnd;
        return $this;
    }

    /**
     * Set the date to get activity after
     *
     * @param string $dateStart YYYY-MM-DD
     *
     * @return \PKP\services\queryBuilders\PKPStatsEditorialQueryBuilder
     */
    public function after($dateStart)
    {
        $this->dateStart = $dateStart;
        return $this;
    }

    /**
     * Get the count of submissions received
     *
     * @return int
     */
    public function countSubmissionsReceived()
    {
        $q = $this->_getObject();
        if ($this->dateStart) {
            $q->where('s.date_submitted', '>=', $this->dateStart);
        }
        if ($this->dateEnd) {
            $q->where('s.date_submitted', '<=', $this->dateEnd);
        }

        return $q->getCountForPagination();
    }

    /**
     * Get the count of submissions that have received one or more
     * editor decisions
     *
     * @param array $decisions One or more Decision::*
     * @param bool $forSubmittedDate How date restrictions should be applied.
     *  A false value will count the number of submissions with an editorial
     * 	decision within the date range. A true value will count the number of
     *  submissions received within the date range which eventually received
     *  an editorial decision.
     *
     * @return int
     */
    public function countByDecisions($decisions, $forSubmittedDate = false)
    {
        $q = $this->_getObject();
        $q->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
            ->whereIn('ed.decision', $decisions);

        if ($forSubmittedDate) {
            if ($this->dateStart) {
                $q->where('s.date_submitted', '>=', $this->dateStart);
            }
            if ($this->dateEnd) {
                // Include date time values up to the end of the day
                $dateTime = new \DateTime($this->dateEnd);
                $dateTime->add(new \DateInterval('P1D'));
                $q->where('s.date_submitted', '<', $dateTime->format('Y-m-d'));
            }
        } else {
            if ($this->dateStart) {
                $q->where('ed.date_decided', '>=', $this->dateStart);
            }
            if ($this->dateEnd) {
                // Include date time values up to the end of the day
                $dateTime = new \DateTime($this->dateEnd);
                $dateTime->add(new \DateInterval('P1D'));
                $q->where('ed.date_decided', '<', $dateTime->format('Y-m-d'));
            }
        }

        // Ensure that the decisions being counted have not been
        // reversed. For example, a submission may have been accepted
        // and then later declined. We check the current status to
        // exclude submissions where the status doesn't match the
        // decisions we are looking for.
        $declineDecisions = array_map(function (DecisionType $decisionType) {
            return $decisionType->getDecision();
        }, Repo::decision()->getDeclineDecisionTypes());
        if (count(array_intersect($declineDecisions, $decisions))) {
            $q->where('s.status', '=', PKPSubmission::STATUS_DECLINED);
        } else {
            $q->where('s.status', '!=', PKPSubmission::STATUS_DECLINED);
        }

        $q->select(DB::raw('COUNT(DISTINCT s.submission_id) as count'));

        return $q->get()->first()->count;
    }

    /**
     * Get the count of submissions by one or more status
     *
     * @param int|array $status One or more of PKPSubmission::STATUS_*
     *
     * @return int
     */
    public function countByStatus($status)
    {
        return $this->_getObject()
            ->whereIn('s.status', (array) $status)
            ->getCountForPagination();
    }

    /**
     * Get the count of active submissions by one or more stages
     *
     * @param array $stages One or more of WORKFLOW_STAGE_ID_*
     *
     * @return int
     */
    public function countActiveByStages($stages)
    {
        return $this->_getObject()
            ->where('s.status', '=', PKPSubmission::STATUS_QUEUED)
            ->whereIn('s.stage_id', $stages)
            ->getCountForPagination();
    }

    /**
     * Get the count of published submissions
     *
     * @return int
     */
    public function countPublished()
    {
        $q = $this->_getObject()
            ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED);

        // Only match against the publication date of a
        // submission's first published publication so
        // that updated versions are excluded.
        if ($this->dateStart || $this->dateEnd) {
            $q->leftJoin('publications as p', function ($q) {
                $q->where('p.publication_id', function ($q) {
                    $q->from('publications as p2')
                        ->where('p2.submission_id', '=', DB::raw('s.submission_id'))
                        ->where('p2.status', '=', PKPPublication::STATUS_PUBLISHED)
                        ->orderBy('p2.date_published', 'ASC')
                        ->limit(1)
                        ->select('p2.publication_id');
                });
            });
            if ($this->dateStart) {
                $q->where('p.date_published', '>=', $this->dateStart);
            }
            if ($this->dateEnd) {
                $q->where('p.date_published', '<=', $this->dateEnd);
            }
        }

        return $q->getCountForPagination();
    }

    /**
     * Get the number of days to reach a particular editor decision
     *
     * This list includes any completed submission which has received
     * one of the editor decisions.
     *
     * @param array $decisions One or more Decision::*
     *
     * @return array Days between submission and the first decision in
     *   the list of requested submissions
     */
    public function getDaysToDecisions($decisions)
    {
        $q = $this->_getDaysToDecisionsObject($decisions);
        $dateDiff = $this->_dateDiff('ed.date_decided', 's.date_submitted');
        $q->select(DB::raw($dateDiff . ' as time'));
        return $q->pluck('time')->toArray();
    }

    /**
     * Get the average number of days to reach a particular
     * editor decision
     *
     * This average includes any completed submission which has received
     * one of the editor decisions.
     *
     * @param array $decisions One or more Decision::*
     *
     * @return float Average days between submission and the first decision
     * 		in the list of requested submissions
     */
    public function getAverageDaysToDecisions($decisions)
    {
        $q = $this->_getDaysToDecisionsObject($decisions);
        $dateDiff = $this->_dateDiff('ed.date_decided', 's.date_submitted');
        $q->select(DB::raw('AVG(' . $dateDiff . ') as average'));
        return $q->get()->first()->average;
    }

    /**
     * Get the first and last date of submissions received
     *
     * @return array [min, max]
     */
    public function getSubmissionsReceivedDates()
    {
        $q = $this->_getObject();
        return [$q->min('s.date_submitted'), $q->max('s.date_submitted')];
    }

    /**
     * Get the first and last date of submissions published
     *
     * @return array [min, max]
     */
    public function getPublishedDates()
    {
        $q = $this->_getObject()
            ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED)
            // Only match against the publication date of a
            // submission's first published publication so
            // that updated versions are excluded.
            ->leftJoin('publications as p', function ($q) {
                $q->where('p.publication_id', function ($q) {
                    $q->from('publications as p2')
                        ->where('p2.submission_id', '=', DB::raw('s.submission_id'))
                        ->where('p2.status', '=', PKPPublication::STATUS_PUBLISHED)
                        ->orderBy('p2.date_published', 'ASC')
                        ->limit(1)
                        ->select('p2.publication_id');
                });
            });

        return [$q->min('p.date_published'), $q->max('p.date_published')];
    }

    /**
     * Get the first and last date that an editorial decision was made
     *
     * @param array $decisions One or more Decision::*
     *
     * @return array [min, max]
     */
    public function getDecisionsDates($decisions)
    {
        $q = $this->_getObject();
        $q->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
            ->whereIn('ed.decision', $decisions);

        // Ensure that the decisions being counted have not been
        // reversed. For example, a submission may have been accepted
        // and then later declined. We check the current status to
        // exclude submissions where the status doesn't match the
        // decisions we are looking for.
        $declineDecisions = array_map(function (DecisionType $decisionType) {
            return $decisionType->getDecision();
        }, Repo::decision()->getDeclineDecisionTypes());
        if (count(array_intersect($declineDecisions, $decisions))) {
            $q->where('s.status', '=', PKPSubmission::STATUS_DECLINED);
        } else {
            $q->where('s.status', '!=', PKPSubmission::STATUS_DECLINED);
        }

        return [$q->min('ed.date_decided'), $q->max('ed.date_decided')];
    }

    /**
     * Generate a base query object with context and section filters.
     */
    protected function _getBaseQuery(): Builder
    {
        $q = DB::table('submissions as s');
        if (!empty($this->contextIds)) {
            $q->whereIn('s.context_id', $this->contextIds);
        }
        if (!empty($this->sectionIds)) {
            $q->leftJoin('publications as ps', 's.current_publication_id', '=', 'ps.publication_id')
                ->whereIn("ps.{$this->sectionIdsColumn}", $this->sectionIds)
                ->whereNotNull('ps.publication_id');
        }

        // First publication included to flag imported submissions through heuristics
        $q->leftJoin(
            'publications as pi',
            fn (Builder $q) => $q->where(
                'pi.publication_id',
                fn (Builder $q) => $q->from('publications as pi2')
                    ->whereColumn('pi2.submission_id', '=', 's.submission_id')
                    ->where('pi2.status', '=', PKPPublication::STATUS_PUBLISHED)
                    ->orderBy('pi2.date_published', 'ASC')
                    ->limit(1)
                    ->select('pi2.publication_id')
            )
        );

        return $q;
    }

    /**
     * Generate a query object based on the configured conditions.
     * Incomplete and imported submissions are excluded.
     *
     * The dateStart and dateEnd filters are not handled here because
     * the dates must be applied differently for each set of data.
     *
     * @hook Stats::editorial::queryObject [[&$q, $this]]
     */
    protected function _getObject(): Builder
    {
        $q = $this->_getBaseQuery();

        // Exclude incomplete submissions
        $q->where('s.submission_progress', '=', '');

        // Exclude submissions when the date_submitted is later
        // than the first date_published. This prevents imported
        // submissions from being counted in editorial stats.
        $q->where(
            fn (Builder $q) => $q->whereNull('pi.date_published')
                ->orWhere(DB::raw('CAST(s.date_submitted AS DATE)'), '<=', DB::raw('pi.date_published'))
        );

        Hook::call('Stats::editorial::queryObject', [&$q, $this]);

        return $q;
    }

    /**
     * Generate a query object to get a submission's first
     * decision of the requested decision types
     *
     * Pass an empty $decisions array to return the number of days to
     * _any_ decision.
     *
     * @param array $decisions One or more Decision::*
     *
     * @return Builder
     */
    protected function _getDaysToDecisionsObject($decisions)
    {
        $q = $this->_getObject();

        $q->leftJoin('edit_decisions as ed', function ($q) use ($decisions) {
            $q->where('ed.edit_decision_id', function ($q) use ($decisions) {
                $q->from('edit_decisions as ed2')
                    ->where('ed2.submission_id', '=', DB::raw('s.submission_id'));
                if (!empty($decisions)) {
                    $q->whereIn('ed2.decision', $decisions);
                }
                $q->orderBy('ed2.date_decided', 'ASC')
                    ->limit(1)
                    ->select('ed2.edit_decision_id');
            });
        });

        $q->whereNotNull('ed.submission_id')
            ->whereNotNull('s.date_submitted');

        if ($this->dateStart) {
            $q->where('s.date_submitted', '>=', $this->dateStart);
        }
        if ($this->dateEnd) {
            // Include date time values up to the end of the day
            $dateTime = new \DateTime($this->dateEnd);
            $dateTime->add(new \DateInterval('P1D'));
            $q->where('s.date_submitted', '<=', $dateTime->format('Y-m-d'));
        }

        return $q;
    }

    /**
     * Get the count of imported submissions.
     * Not counted by static::countSubmissionsReceived().
     *
     * @return int
     */
    public function countImported()
    {
        return $this->_getBaseQuery()
            ->where(DB::raw('CAST(s.date_submitted AS DATE)'), '>', DB::raw('pi.date_published'))
            ->when($this->dateStart, fn (Builder $q) => $q->where('s.date_submitted', '>=', $this->dateStart))
            ->when($this->dateEnd, fn (Builder $q) => $q->where('s.date_submitted', '<=', $this->dateEnd))
            ->getCountForPagination();
    }

    /**
     * Get the count of incomplete submissions.
     * Not counted by static::countSubmissionsReceived().
     *
     * @return int
     */
    public function countInProgress()
    {
        return $this->_getBaseQuery()
            ->where('s.submission_progress', '<>', '')
            ->when($this->dateStart, fn (Builder $q) => $q->where('s.date_submitted', '>=', $this->dateStart))
            ->when($this->dateEnd, fn (Builder $q) => $q->where('s.date_submitted', '<=', $this->dateEnd))
            ->getCountForPagination();
    }

    /**
     * Get the count of submissions skipped by the other statistics
     */
    public function countSkipped(): int
    {
        return $this->countInProgress() + $this->countImported();
    }

    /**
     * Retrieves a suitable diff by days clause according to the active database driver
     *
     * @return string
     */
    private function _dateDiff(string $leftDate, string $rightDate)
    {
        return match (Config::getVar('database', 'driver')) {
            'mysql', 'mysqli', 'mariadb' => 'DATEDIFF(' . $leftDate . ',' . $rightDate . ')',
            default => "DATE_PART('day', " . $leftDate . ' - ' . $rightDate . ')'
        };
    }
}
