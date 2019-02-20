<?php
/**
 * @file controllers/stats/StatsComponentHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StatsComponentHandler
 * @ingroup classes_controllers_stats
 *
 * @brief A class to create and compile configuration date for a Statistics UI
 * component.
 */
class StatsComponentHandler extends PKPHandler {
	/** @var string The URL to the /stats API endpoint */
	public $_apiUrl = '';

	/** @var array Stats by time segment (eg - month) for a graph */
	public $_timeSegments = [];

	/** @var array List of items to display stats for */
	public $_items = [];

	/** @var integer The maximum number of items that stats can be shown for */
	public $_itemsMax = 0;

	/** @var array Configuration for the columns to display in the table */
	public $_tableColumns = [];

	/** @var integer How many items to show per page */
	public $_count = 30;

	/** @var string Which time segment (eg - month) is displayed in the graph */
	public $_timeSegment = 'month';

	/** @var string Retrieve stats after this date */
	public $_dateStart = '';

	/** @var string Retrieve stats before this date */
	public $_dateEnd = '';

	/** @var array Quick options to provide for configuring the date range */
	public $_dateRangeOptions = [];

	/** @var string Order items by this property */
	public $_orderBy = '';

	/** @var string Order items in this direction: ASC or DESC*/
	public $_orderDirection = 'DESC';

	/** @var array|null Configuration assoc array for available filters */
	public $_filters = null;

	/** @var array Localized strings to pass to the component */
	public $_i18n = [];

	/**
	 * Constructor
	 *
	 * @param $apiUrl string The URL to fetch stats from
	 * @param $args array Optional arguments
	 */
	function __construct($apiUrl, $args = array()) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
		parent::__construct();

		$this->_apiUrl = $apiUrl;
		$this->init($args);
	}

	/**
	 * Initialize the handler with config parameters
	 *
	 * @param $args array Configuration params
	 */
	public function init($args = array()) {
		foreach ($args as $key => $value) {
			$property = '_' . $key;
			if (property_exists( $this, $property)) {
				$this->{$property} = $value;
			}
		}
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		$config = [
			'apiUrl' => $this->_apiUrl,
			'timeSegment' => $this->_timeSegment,
			'timeSegments' => $this->_timeSegments,
			'items' => $this->_items,
			'itemsMax' => $this->_itemsMax,
			'tableColumns' => $this->_tableColumns,
			'count' => $this->_count,
			'offset' => 0,
			'searchPhrase' => '',
			'dateStart' => $this->_dateStart,
			'dateEnd' => $this->_dateEnd,
			'dateEndMax' => date('Y-m-d', strtotime('yesterday')),
			'dateRangeOptions' => $this->_dateRangeOptions,
			'orderBy' => $this->_orderBy,
			'orderDirection' => $this->_orderDirection,
			'activeFilters' => [],
			'isFilterVisible' => false,
			'isLoading' => false,
			'i18n' => array_merge(
				[
					'filter' => __('common.filter'),
					'filterRemove' => __('common.filterRemove'),
					'itemsOfTotal' => __('stats.articlesOfTotal'),
					'paginationLabel' => __('common.pagination.label'),
					'goToLabel' => __('common.pagination.goToPage'),
					'pageLabel' => __('common.pageNumber'),
					'nextPageLabel' => __('common.pagination.next'),
					'previousPageLabel' => __('common.pagination.previous'),
					'search' => __('stats.searchSubmissionDescription'),
					'clearSearch' => __('common.clearSearch'),
					'dateRange' => __('stats.dateRange'),
					'dateFormatInstructions' => __('stats.dateRange.instructions'),
					'changeDateRange' => __('stats.dateRange.change'),
					'sinceDate' => __('stats.dateRange.sinceDate'),
					'untilDate' => __('stats.dateRange.untilDate'),
					'allDates' => __('stats.dateRange.allDates'),
					'customRange' => __('stats.dateRange.customRange'),
					'fromDate' => __('stats.dateRange.from'),
					'toDate' => __('stats.dateRange.to'),
					'apply' => __('stats.dateRange.apply'),
					'invalidDate' => __('stats.dateRange.invalidDate'),
					'dateDoesNotExist' => __('stats.dateRange.dateDoesNotExist'),
					'invalidDateRange' => __('stats.dateRange.invalidDateRange'),
					'invalidEndDateMax' => __('stats.dateRange.invalidEndDateMax'),
					'invalidStartDateMin' => __('stats.dateRange.invalidStartDateMin'),
					'day' => __('stats.daily'),
					'month' => __('stats.monthly'),
				],
				$this->_i18n
			),
		];

		if ($this->_filters) {
			$config['filters'] = $this->_filters;
		}

		return $config;
	}
}
