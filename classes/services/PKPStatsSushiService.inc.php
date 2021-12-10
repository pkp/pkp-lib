<?php

/**
 * @file classes/services/PKPStatsSushiService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiService
 * @ingroup services
 *
 * @brief Helper class that encapsulates COUNTER R5 SUSHI statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\plugins\HookRegistry;

class PKPStatsSushiService
{
    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder(array $args = []): \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->filterByInstitution((int) $args['institutionId'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty($args['yearsOfPublication'])) {
            $statsQB->filterByYOP($args['yearsOfPublication']);
        }
        if (!empty($args['submissionIds'])) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }

        HookRegistry::call('StatsSushi::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }

    /**
     * Do usage stats data already exist for the given month
     */
    public function monthExists(string $month): bool
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder();
        return $statsQB->monthExists($month);
    }

    /**
     * Get earliest date, the COUNTER R5 started at
     * R5 is introduced in the release 3.4.0.0, so get the date installed of the release 3.4.0.0 or first next used release
     */
    public function getEarliestDate(): string
    {
        $product = Application::get()->getName();
        $dateInstalledArray = DB::select("
            SELECT date_installed
                FROM versions
                WHERE major*1000+minor*100+revision*10+build IN
                    (SELECT MIN(major*1000+minor*100+revision*10+build)
                    FROM versions vt
                    WHERE vt.product_type = 'core' AND vt.product = ? AND vt.major*1000+vt.minor*100+vt.revision*10+vt.build >= 3400)
                AND product_type = 'core' AND product = ?
        ", [$product, $product]);
        return current($dateInstalledArray)->date_installed;
    }

    /**
     * Delete daily usage metrics for a month
     */
    public function deleteDailyMetrics(string $month): void
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder();
        $statsQB->deleteDailyMetrics($month);
    }

    /**
     * Delete monthly usage metrics for a month
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder();
        $statsQB->deleteDailyMetrics($month);
    }

    /**
     * Aggregate daily usage metrics by a month
     */
    public function aggregateMetrics(string $month): void
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsSushiQueryBuilder();
        $statsQB->aggregateMetrics($month);
    }
}
