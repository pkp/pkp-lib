<?php

/**
 * @file classes/services/queryBuilders/PKPStatsSushiQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch COUNTER stats records from the
 *  metrics_counter_submission_monthly or metrics_counter_submission_institution_monthly table.
 */

namespace PKP\services\queryBuilders;

use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\plugins\Hook;

class PKPStatsSushiQueryBuilder extends PKPStatsQueryBuilder
{
    /** Include records for the submissions that have these years of publications (YOP) */
    protected array $yearsOfPublication = [];

    /**Include records for these submissions */
    protected array $submissionIds = [];

    /**Include records for this institution */
    protected int $institutionId = 0;

    /**
     * Set the year of publication (YOP) of submissions to get records for
     */
    public function filterByYOP(array $yearsOfPublication): self
    {
        $this->yearsOfPublication = $yearsOfPublication;
        return $this;
    }

    /**
     * Set the submissions to get records for
     */
    public function filterBySubmissions(array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Set the institution to get records for
     */
    public function filterByInstitution(int $institutionId): self
    {
        $this->institutionId = $institutionId;
        return $this;
    }

    /**
     * @copydoc PKPStatsQueryBuilder::getSum()
     */
    public function getSum(array $groupBy = []): Builder
    {
        $selectColumns = $groupBy;
        $q = $this->_getObject();
        // consider YOP
        if (in_array('YOP', $selectColumns)) {
            // left join the table publications, if the filter is not set i.e. the left join is not considered yet in _getObject()
            if (empty($this->yearsOfPublication)) {
                $q->leftJoin('publications as p', function ($q) {
                    $q->on('p.submission_id', '=', 'm.submission_id')
                        ->whereIn('p.publication_id', function ($q) {
                            $q->selectRaw('MIN(p2.publication_id)')->from('publications as p2')->where('p2.status', Submission::STATUS_PUBLISHED);
                        });
                });
            }
            foreach ($selectColumns as $i => $selectColumn) {
                if ($selectColumn == 'YOP') {
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        $selectColumns[$i] = DB::raw('EXTRACT(YEAR FROM p.date_published) as "YOP"');
                    } else {
                        $selectColumns[$i] = DB::raw('YEAR(STR_TO_DATE(p.date_published, "%Y-%m-%d")) as YOP');
                    }
                    break;
                }
            }
        }

        // Build the select and group by clauses.
        if (!empty($selectColumns)) {
            $q->select($selectColumns);
            if (!empty($groupBy)) {
                $q->groupBy($groupBy);
            }
        }
        $counterMetricsColumns = StatisticsHelper::getCounterMetricsColumns();
        foreach ($counterMetricsColumns as $counterMetricsColumn) {
            $q->addSelect(DB::raw("SUM({$counterMetricsColumn}) AS {$counterMetricsColumn}"));
        }
        return $q;
    }

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     */
    protected function _getObject(): Builder
    {
        if ($this->institutionId === 0) {
            $q = DB::table('metrics_counter_submission_monthly as m');
        } else {
            $q = DB::table('metrics_counter_submission_institution_monthly as m');
        }

        if (!empty($this->yearsOfPublication)) {
            $q->leftJoin('publications as p', function ($q) {
                $q->on('p.submission_id', '=', 'm.submission_id')
                    ->whereIn('p.publication_id', function ($q) {
                        $q->selectRaw('MIN(p2.publication_id)')->from('publications as p2')->where('p2.status', Submission::STATUS_PUBLISHED);
                    });
            });
            foreach ($this->yearsOfPublication as $yop) {
                if (preg_match('/\d{4}/', $yop)) {
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        $q->where(DB::raw('EXTRACT(YEAR FROM p.date_published)'), '=', $yop);
                    } else {
                        $q->where(DB::raw('YEAR(STR_TO_DATE(p.date_published, "%Y-%m-%d"))'), '=', $yop);
                    }
                } elseif (preg_match('/\d{4}-\d{4}/', $yop)) {
                    $years = explode('-', $yop);
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        $q->whereBetween(DB::raw('EXTRACT(YEAR FROM p.date_published)'), $years);
                    } else {
                        $q->whereBetween(DB::raw('YEAR(STR_TO_DATE(p.date_published, "%Y-%m-%d"))'), $years);
                    }
                }
            }
        }

        if (!empty($this->contextIds)) {
            $q->whereIn('m.' . StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        if (!empty($this->submissionIds)) {
            $q->whereIn('m.' . StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, $this->submissionIds);
        }

        $q->whereBetween('m.' . StatisticsHelper::STATISTICS_DIMENSION_MONTH, [date_format(date_create($this->dateStart), 'Ym'), date_format(date_create($this->dateEnd), 'Ym')]);

        Hook::call('StatsSushi::queryObject', [&$q, $this]);

        return $q;
    }

    /**
     * Do usage stats data already exist for the given month
     * Consider only the table metrics_counter_submission_monthly, because
     * it always contains data, while metrics_counter_submission_institution_monthly
     * could not contain data.
     *
     * @param string $month Month in the form YYYYMM
     */
    public function monthExists(string $month): bool
    {
        return DB::table('metrics_counter_submission_monthly as m')
            ->where(StatisticsHelper::STATISTICS_DIMENSION_MONTH, $month)->exists();
    }

    /**
     * Delete daily usage stats for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteDailyMetrics(string $month): void
    {
        // Construct the SQL part depending on the DB
        $monthFormatSql = "DATE_FORMAT(date, '%Y%m')";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(date, 'YYYYMM')";
        }
        DB::table('metrics_counter_submission_daily')->where(DB::raw($monthFormatSql), '=', $month)->delete();
        DB::table('metrics_counter_submission_institution_daily')->where(DB::raw($monthFormatSql), '=', $month)->delete();
    }

    /**
     * Delete monthly usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        DB::table('metrics_counter_submission_monthly')->where('month', $month)->delete();
        DB::table('metrics_counter_submission_institution_monthly')->where('month', $month)->delete();
    }

    /**
     * Aggregate daily usage metrics by a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function addMonthlyMetrics(string $month): void
    {
        // Construct the SQL part depending on the DB
        $monthFormatSql = "CAST(DATE_FORMAT(csd.date, '%Y%m') AS UNSIGNED)";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(csd.date, 'YYYYMM')::integer";
        }
        // Get the application specific metrics columns
        $counterMetricsColumns = StatisticsHelper::getCounterMetricsColumns();
        // SQL part for the select sub-statement creates the SUM for each metrics column, and then connects them with ','
        $selectSql = implode(', ', array_map(fn ($value): string => 'SUM(csd.' . $value . ')', $counterMetricsColumns));

        $selectSubmissionDaily = DB::table('metrics_counter_submission_daily as csd')
            ->select(DB::raw("csd.context_id, csd.submission_id, {$monthFormatSql} as csdmonth, {$selectSql}"))
            ->whereRaw("{$monthFormatSql} = ?", [$month])
            ->groupBy(DB::raw('csd.context_id, csd.submission_id, csdmonth'));
        DB::table('metrics_counter_submission_monthly')->insertUsing(array_merge(['context_id', 'submission_id', 'month'], $counterMetricsColumns), $selectSubmissionDaily);

        $selectSubmissionInstitutionDaily = DB::table('metrics_counter_submission_institution_daily as csd')
            ->select(DB::raw("csd.context_id, csd.submission_id, csd.institution_id, {$monthFormatSql} as csdmonth, {$selectSql}"))
            ->whereRaw("{$monthFormatSql} = ?", [$month])
            ->groupBy(DB::raw('csd.context_id, csd.submission_id, csd.institution_id, csdmonth'));
        DB::table('metrics_counter_submission_institution_monthly')->insertUsing(array_merge(['context_id', 'submission_id', 'institution_id', 'month'], $counterMetricsColumns), $selectSubmissionInstitutionDaily);
    }
}
