<?php

/**
* @file classes/statistics/PKPStatisticsHelper.inc.php
*
* Copyright (c) 2013-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class PKPStatisticsHelper
* @ingroup statistics
*
* @brief Statistics helper class.
*
*/

namespace PKP\statistics;

use PKP\file\PrivateFileManager;

abstract class PKPStatisticsHelper
{
    // Dimensions:
    // 1) publication object dimension:
    public const STATISTICS_DIMENSION_CONTEXT_ID = 'context_id';
    public const STATISTICS_DIMENSION_SUBMISSION_ID = 'submission_id';
    public const STATISTICS_DIMENSION_ASSOC_TYPE = 'assoc_type';
    public const STATISTICS_DIMENSION_FILE_TYPE = 'file_type';
    public const STATISTICS_DIMENSION_SUBMISSION_FILE_ID = 'submission_file_id';
    public const STATISTICS_DIMENSION_REPRESENTATION_ID = 'representation_id';

    // 2) time dimension:
    public const STATISTICS_DIMENSION_YEAR = 'year';
    public const STATISTICS_DIMENSION_MONTH = 'month';
    public const STATISTICS_DIMENSION_DAY = 'day'; // used as API parameter for timelines
    public const STATISTICS_DIMENSION_DATE = 'date';

    // 3) geography dimension:
    public const STATISTICS_DIMENSION_COUNTRY = 'country';
    public const STATISTICS_DIMENSION_REGION = 'region';
    public const STATISTICS_DIMENSION_CITY = 'city';

    // Metrics:
    public const STATISTICS_METRIC = 'metric';
    public const STATISTICS_METRIC_UNIQUE = 'metric_unique';

    // Ordering:
    public const STATISTICS_ORDER_ASC = 'ASC';
    public const STATISTICS_ORDER_DESC = 'DESC';

    // File type to be used in publication object dimension.
    public const STATISTICS_FILE_TYPE_HTML = 1;
    public const STATISTICS_FILE_TYPE_PDF = 2;
    public const STATISTICS_FILE_TYPE_OTHER = 3;
    public const STATISTICS_FILE_TYPE_DOC = 4;

    // Set the earliest date used
    public const STATISTICS_EARLIEST_DATE = '2001-01-01';

    /** These are rules defined by the COUNTER project.
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#doubleclick
     */
    public const COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS = 30;

    /**
     * Get the usage stats directory path.
     */
    public static function getUsageStatsDirPath(): string
    {
        $fileMgr = new PrivateFileManager();
        return realpath($fileMgr->getBasePath()) . '/usageStats';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\statistics\PKPStatisticsHelper', '\PKPStatisticsHelper');
    foreach ([
        'STATISTICS_DIMENSION_CONTEXT_ID',
        'STATISTICS_DIMENSION_SUBMISSION_ID',
        'STATISTICS_DIMENSION_REPRESENTATION_ID',
        'STATISTICS_DIMENSION_ASSOC_TYPE',
        'STATISTICS_DIMENSION_FILE_TYPE',
        'STATISTICS_DIMENSION_YEAR',
        'STATISTICS_DIMENSION_MONTH',
        'STATISTICS_DIMENSION_DAY',
        'STATISTICS_DIMENSION_DATE',
        'STATISTICS_DIMENSION_COUNTRY',
        'STATISTICS_DIMENSION_REGION',
        'STATISTICS_DIMENSION_CITY',
        'STATISTICS_METRIC',
        'STATISTICS_METRIC_UNIQUE',
        'STATISTICS_ORDER_ASC',
        'STATISTICS_ORDER_DESC',
        'STATISTICS_FILE_TYPE_HTML',
        'STATISTICS_FILE_TYPE_PDF',
        'STATISTICS_FILE_TYPE_OTHER',
        'STATISTICS_FILE_TYPE_DOC',
        'STATISTICS_EARLIEST_DATE',
        'COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS',
    ] as $constantName) {
        define($constantName, constant('\PKPStatisticsHelper::' . $constantName));
    }
}
