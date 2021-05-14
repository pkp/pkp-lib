<?php

/**
 * @file classes/statistics/MetricsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetricsDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding statistics data.
 */

namespace APP\statistics;

use PKP\statistics\PKPMetricsDAO;

class MetricsDAO extends PKPMetricsDAO
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\statistics\MetricsDAO', '\MetricsDAO');
}
