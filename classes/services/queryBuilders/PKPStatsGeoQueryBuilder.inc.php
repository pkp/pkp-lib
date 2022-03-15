<?php

/**
 * @file classes/services/queryBuilders/PKPStatsGeoQueryBuilder.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch geographic stats records from the
 *  metrics_submission_geo_monthly table.
 */

namespace PKP\services\queryBuilders;

use APP\statistics\StatisticsHelper;
use Illuminate\Support\Facades\DB;
use PKP\plugins\HookRegistry;

class PKPStatsGeoQueryBuilder extends PKPStatsQueryBuilder
{
    /** Include records for these submissions */
    protected array $submissionIds = [];

    /** Include records for these countries */
    protected array $countries = [];

    /** Include records for these regions */
    protected array $regions = [];

    /** Include records for these cities */
    protected array $cities = [];

    /**
     * Set the submission to get records for
     */
    public function filterBySubmissions(array|int $submissionIds): self
    {
        $this->submissionIds = is_array($submissionIds) ? $submissionIds : [$submissionIds];
        return $this;
    }

    /**
     * Set the countries to get records for
     */
    public function filterByCountries(array|string $countries): self
    {
        $this->countries = is_array($countries) ? $countries : [$countries];
        return $this;
    }

    /**
     * Set the regions to get records for
     */
    public function filterByRegions(array|string $regions): self
    {
        $this->regions = is_array($regions) ? $regions : [$regions];
        return $this;
    }

    /**
     * Set the cities to get records for
     */
    public function filterByCities(array|string $cities): self
    {
        $this->cities = is_array($cities) ? $cities : [$cities];
        return $this;
    }

    /**
     * @copydoc PKPStatsQueryBuilder::getSum()
     */
    public function getSum(array $groupBy = []): \Illuminate\Database\Query\Builder
    {
        $selectColumns = $groupBy;
        $q = $this->_getObject();
        // Build the select and group by clauses.
        if (!empty($selectColumns)) {
            $q->select($selectColumns);
            if (!empty($groupBy)) {
                $q->groupBy($groupBy);
            }
        }
        $q->addSelect(DB::raw('SUM(metric) AS metric'));
        $q->addSelect(DB::raw('SUM(metric_unique) AS metric_unique'));
        return $q;
    }

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     */
    protected function _getObject(): \Illuminate\Database\Query\Builder
    {
        // consider only monthly DB table
        $q = DB::table('metrics_submission_geo_monthly');

        if (!empty($this->contextIds)) {
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
                    ->where(StatisticsHelper::STATISTICS_DIMENSION_CITY, $city);
            });
            foreach ($this->cities as $countryRegionCity) {
                // cities must be in a form countryCode-regionCode-cityName
                [$country, $region, $city] = explode('-', $countryRegionCity);
                $q->orWhere(function ($q) use ($country, $region, $city) {
                    $q->where(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, $country)
                        ->where(StatisticsHelper::STATISTICS_DIMENSION_REGION, $region)
                        ->where(StatisticsHelper::STATISTICS_DIMENSION_CITY, $city);
                });
            }
        }

        $q->whereBetween(StatisticsHelper::STATISTICS_DIMENSION_MONTH, [date_format(date_create($this->dateStart), 'Ym'), date_format(date_create($this->dateEnd), 'Ym')]);

        HookRegistry::call('StatsGeo::queryObject', [&$q, $this]);

        return $q;
    }
}
