<?php

/**
 * @file classes/services/queryBuilders/StatsPublicationQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatsPublicationQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics_submission table.
 */

namespace APP\services\queryBuilders;

use PKP\services\queryBuilders\PKPStatsPublicationQueryBuilder;

class StatsPublicationQueryBuilder extends PKPStatsPublicationQueryBuilder
{
    public function getSectionColumn(): string
    {
        return 'section_id';
    }
}
