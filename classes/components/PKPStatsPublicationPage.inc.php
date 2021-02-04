<?php
/**
 * @file components/PKPStatsPublicationPage.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationPage
 * @ingroup classes_controllers_stats
 *
 * @brief A class to prepare the data object for the publication statistics
 *   UI component
 */
namespace PKP\components;

use PKP\components\PKPStatsComponent;

import('classes.statistics.StatisticsHelper');

class PKPStatsPublicationPage extends PKPStatsComponent {
	/** @var array A timeline of stats (eg - monthly) for a graph */
	public $timeline = [];

	/** @var string Which time segment (eg - month) is displayed in the graph */
	public $timelineInterval = STATISTICS_DIMENSION_MONTH;

	/** @var string Which views to show in the graph. Supports `abstract` or `galley`. */
	public $timelineType = '';

	/** @var array List of items to display stats for */
	public $items = [];

	/** @var integer The maximum number of items that stats can be shown for */
	public $itemsMax = 0;

	/** @var integer How many items to show per page */
	public $count = 30;

	/** @var string Order items by this property */
	public $orderBy = '';

	/** @var string Order items in this direction: ASC or DESC*/
	public $orderDirection = 'DESC';

	/** @var string A search phrase to filter the list of items */
	public $searchPhrase = '';

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		$config = parent::getConfig();

		$config = array_merge(
			$config,
			[
				'timeline' => $this->timeline,
				'timelineInterval' => $this->timelineInterval,
				'timelineType' => $this->timelineType,
				'items' => $this->items,
				'itemsOfTotalLabel' => __('stats.publications.countOfTotal'),
				'itemsMax' => $this->itemsMax,
				'count' => $this->count,
				'offset' => 0,
				'searchPhrase' => '',
				'orderBy' => $this->orderBy,
				'orderDirection' => $this->orderDirection,
				'isLoadingTimeline' => false,
			]
		);

		return $config;
	}
}
