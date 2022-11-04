<?php
/**
 * @file components/PKPStatsContextPage.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextPage
 * @ingroup classes_controllers_stats
 *
 * @brief A class to prepare the data object for the context statistics
 *   UI component
 */

namespace PKP\components;

use PKP\statistics\PKPStatisticsHelper;

class PKPStatsContextPage extends PKPStatsComponent
{
    /** @var array A timeline of stats (eg - monthly) for a graph */
    public $timeline = [];

    /** @var string Which time segment (eg - month) is displayed in the graph */
    public $timelineInterval = PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH;

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * @return array Configuration data
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'timeline' => $this->timeline,
                'timelineInterval' => $this->timelineInterval,
                'dateRangeLabel' => __('stats.dateRange'),
                'betweenDatesLabel' => __('stats.downloadReport.betweenDates'),
                'allDatesLabel' => __('stats.dateRange.allDates'),
                'contextLabel' => __('context.context'),
                'timelineTypeLabel' => __('stats.timelineType'),
                'timelineIntervalLabel' => __('stats.timelineInterval'),
                'viewsLabel' => __('submission.views'),
                'dayLabel' => __('common.day'),
                'monthLabel' => __('common.month'),
                'timelineDescriptionLabel' => __('stats.timeline.downloadReport.description'),
                'isLoadingTimeline' => false,
            ]
        );

        return $config;
    }
}
