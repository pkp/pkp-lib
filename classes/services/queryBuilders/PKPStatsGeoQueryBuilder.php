<?php

/**
 * @file classes/services/queryBuilders/PKPStatsGeoQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoQueryBuilder
 *
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch geographic stats records from the
 *  metrics_submission_geo_monthly table.
 */

namespace PKP\services\queryBuilders;

use APP\core\Application;
use APP\publication\Publication;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\plugins\Hook;

abstract class PKPStatsGeoQueryBuilder extends PKPStatsQueryBuilder
{
    /** Include records for these sections/series */
    protected array $pkpSectionIds = [];

    /** Include records for these submissions */
    protected array $submissionIds = [];

    /** Include records for these countries */
    protected array $countries = [];

    /** Include records for these regions */
    protected array $regions = [];

    /** Include records for these cities */
    protected array $cities = [];

    /** Application specific name of the section column  */
    abstract public function getSectionColumn(): string;

    /**
     * Set the sections/series to get records for
     */
    public function filterByPKPSections(array $pkpSectionIds): self
    {
        $this->pkpSectionIds = $pkpSectionIds;
        return $this;
    }

    /**
     * Set the submission to get records for
     */
    public function filterBySubmissions(array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Set the countries to get records for
     */
    public function filterByCountries(array $countries): self
    {
        $this->countries = $countries;
        return $this;
    }

    /**
     * Set the regions to get records for
     */
    public function filterByRegions(array $regions): self
    {
        $this->regions = $regions;
        return $this;
    }

    /**
     * Set the cities to get records for
     */
    public function filterByCities(array $cities): self
    {
        $this->cities = $cities;
        return $this;
    }

    /**
     * Get Geo data
     */
    public function getGeoData(array $groupBy): Builder
    {
        return $this->_getObject()
            ->select($groupBy)
            ->groupBy($groupBy);
    }

    /**
     * @copydoc PKPStatsQueryBuilder::getSum()
     */
    public function getSum(array $groupBy = []): Builder
    {
        $q = $this->_getObject();
        // Build the select and group by clauses.
        if (!empty($groupBy)) {
            $q->select($groupBy);
            $q->groupBy($groupBy);
        }
        $q->addSelect(DB::raw('SUM(metric) AS metric'));
        $q->addSelect(DB::raw('SUM(metric_unique) AS metric_unique'));
        return $q;
    }

    /**
     * Consider/add application specific queries
     */
    protected function _getAppSpecificQuery(Builder &$q): void
    {
    }

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     *
     * @hook StatsGeo::queryObject [[&$q, $this]]
     */
    protected function _getObject(): Builder
    {
        // consider only monthly DB table
        $q = DB::table('metrics_submission_geo_monthly');

        if (empty($this->contextIds)) {
            throw new Exception('Statistics cannot be retrieved without a context id. Pass the Application::SITE_CONTEXT_ID_ALL wildcard to get statistics from all contexts.');
        }

        if (!in_array(Application::SITE_CONTEXT_ID_ALL, $this->contextIds)) {
            $q->whereIn(StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        if (!empty($this->submissionIds)) {
            $q->whereIn(StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, $this->submissionIds);
        }

        if (!empty($this->countries)) {
            $q->whereIn(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $this->countries);
        }

        if (!empty($this->regions)) {
            // get first region (so that we can use where and then orWhere query)
            $fistCountryRegionCode = array_shift($this->regions);
            // regions must be in a form countryCode-regionCode
            [$country, $region] = explode('-', $fistCountryRegionCode);
            $q->where(function ($q) use ($country, $region) {
                $q->where(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $country)
                    ->where(StatisticsHelper::STATISTICS_DIMENSION_REGION, $region);
            });
            foreach ($this->regions as $countryRegioncode) {
                // regions must be in a form countryCode-regionCode
                [$country, $region] = explode('-', $countryRegioncode);
                $q->orWhere(function ($q) use ($country, $region) {
                    $q->where(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $country)
                        ->where(StatisticsHelper::STATISTICS_DIMENSION_REGION, $region);
                });
            }
        }

        if (!empty($this->cities)) {
            // get first city (so that we can use where and then orWhere query)
            $fistCountryRegionCity = array_shift($this->cities);
            // cities must be in a form countryCode-regionCode-cityName
            [$country, $region, $city] = explode('-', $fistCountryRegionCity);
            $q->where(function ($q) use ($country, $region, $city) {
                $q->where(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $country)
                    ->where(StatisticsHelper::STATISTICS_DIMENSION_REGION, $region)
                    ->where(StatisticsHelper::STATISTICS_DIMENSION_CITY, 'like', $city . '%');
            });
            foreach ($this->cities as $countryRegionCity) {
                // cities must be in a form countryCode-regionCode-cityName
                [$country, $region, $city] = explode('-', $countryRegionCity);
                $q->orWhere(function ($q) use ($country, $region, $city) {
                    $q->where(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $country)
                        ->where(StatisticsHelper::STATISTICS_DIMENSION_REGION, $region)
                        ->where(StatisticsHelper::STATISTICS_DIMENSION_CITY, 'like', $city . '%');
                });
            }
        }

        $q->whereBetween(StatisticsHelper::STATISTICS_DIMENSION_MONTH, [date_format(date_create($this->dateStart), 'Ym'), date_format(date_create($this->dateEnd), 'Ym')]);

        if (!empty($this->pkpSectionIds)) {
            $sectionColumn = 'p.' . $this->getSectionColumn();
            $sectionSubmissionIds = DB::table('publications as p')->select('p.submission_id')->distinct()
                ->from('publications as p')
                ->where('p.status', Publication::STATUS_PUBLISHED)
                ->whereIn($sectionColumn, $this->pkpSectionIds);
            $q->joinSub($sectionSubmissionIds, 'ss', function ($join) {
                $join->on('metrics_submission_geo_monthly.' . StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, '=', 'ss.submission_id');
            });
        }

        $this->_getAppSpecificQuery($q);

        if ($this->limit > 0) {
            $q->limit($this->limit);
            if ($this->offset > 0) {
                $q->offset($this->offset);
            }
        }

        Hook::call('StatsGeo::queryObject', [&$q, $this]);

        return $q;
    }

    /**
     * Do usage stats data already exist for the given month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function monthExists(string $month): bool
    {
        return DB::table('metrics_submission_geo_monthly')
            ->where(StatisticsHelper::STATISTICS_DIMENSION_MONTH, $month)->exists();
    }

    /**
     * Delete daily usage metrics for a month
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
        DB::table('metrics_submission_geo_daily')->where(DB::raw($monthFormatSql), '=', $month)->delete();
    }

    /**
     * Delete monthly usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        DB::table('metrics_submission_geo_monthly')->where('month', $month)->delete();
    }

    /**
     * Aggregate daily usage metrics by a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function addMonthlyMetrics(string $month): void
    {
        // Construct the SQL part depending on the DB
        $monthFormatSql = "CAST(DATE_FORMAT(gd.date, '%Y%m') AS UNSIGNED)";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(gd.date, 'YYYYMM')::integer";
        }
        $selectSubmissionGeoDaily = DB::table('metrics_submission_geo_daily as gd')
            ->select(DB::raw("gd.context_id, gd.submission_id, COALESCE(gd.country, ''), COALESCE(gd.region, ''), COALESCE(gd.city, ''), {$monthFormatSql} as gdmonth, SUM(gd.metric), SUM(gd.metric_unique)"))
            ->whereRaw("{$monthFormatSql} = ?", [$month])
            ->groupBy(DB::raw('gd.context_id, gd.submission_id, gd.country, gd.region, gd.city, gdmonth'));
        DB::table('metrics_submission_geo_monthly')->insertUsing(['context_id', 'submission_id', 'country', 'region', 'city', 'month', 'metric', 'metric_unique'], $selectSubmissionGeoDaily);
    }
}
