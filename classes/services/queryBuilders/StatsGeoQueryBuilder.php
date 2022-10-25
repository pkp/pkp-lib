<?php

/**
 * @file classes/services/queryBuilders/StatsGeoQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatsGeoQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch geographic stats records from the
 *  metrics_submission_geo_monthly table.
 */

namespace APP\services\queryBuilders;

use PKP\services\queryBuilders\PKPStatsGeoQueryBuilder;

class StatsGeoQueryBuilder extends PKPStatsGeoQueryBuilder
{
    /** The name of the section column */
    public string $sectionColumn = 'section_id';
}
