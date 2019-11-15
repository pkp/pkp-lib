<?php
/**
 * @file controllers/stats/EditorialReportComponentHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorialReportComponentHandler
 * @ingroup classes_controllers_stats
 *
 * @brief A class to create and compile configuration date for a Statistics UI
 * component.
 */
class EditorialReportComponentHandler extends PKPHandler {
	/** @var string The URL to the editorial report API endpoint */
	public $_apiUrl = '';

	/** @var array List of editorial statistics */
	public $_editorialItems = [];

	/** @var array List of user statistics */
	public $_userItems = [];

	/** @var array Configuration for the columns to display in the table */
	public $_tableColumns = [];

	/** @var string Retrieve stats after this date */
	public $_dateStart = '';

	/** @var string Retrieve stats before this date */
	public $_dateEnd = '';

	/** @var array Quick options to provide for configuring the date range */
	public $_dateRangeOptions = [];

	/** @var array|null Configuration assoc array for available filters */
	public $_filters = null;

	/** @var array Localized strings to pass to the component */
	public $_i18n = [];

	/** @var array List of submission stages and their statistics */
	public $_submissionsStage = [];

	/** @var array Data to present on the pie chart */
	public $_editorialChartData = [];

	/**
	 * Constructor
	 *
	 * @param $apiUrl string The URL to fetch stats from
	 * @param $args array Optional arguments
	 */
	public function __construct(string $apiUrl, array $args = []) {
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
	public function init(array $args = []) : void
	{
		foreach ($args as $key => $value) {
			$property = '_' . $key;
			if (property_exists($this, $property)) {
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
	public function getConfig() : array
	{
		$config = [
			'submissionsStage' => $this->_submissionsStage,
			'editorialChartData' => $this->_editorialChartData,
			'apiUrl' => $this->_apiUrl,
			'editorialItems' => $this->_editorialItems,
			'userItems' => $this->_userItems,
			'tableColumns' => [
				[
					'name' => 'metric',
					'label' => __('common.name'),
					'isRowHeader' => true,
					'value' => 'name'
				],
				[
					'name' => 'period',
					'label' => __('manager.statistics.totalWithinDateRange'),
					'value' => 'period'
				],
				[
					'name' => 'total',
					'label' => __('stats.total'),
					'value' => 'total'
				]
			],
			'dateStart' => $this->_dateStart,
			'dateEnd' => $this->_dateEnd,
			'dateEndMax' => date('Y-m-d', strtotime('yesterday')),
			'dateRangeOptions' => $this->_dateRangeOptions,
			'activeFilters' => [],
			'isFilterVisible' => false,
			'i18n' => $this->_i18n + [
				'filter' => __('common.filter'),
				'filterRemove' => __('common.filterRemove'),
				'activeSubmissions' => __('stats.activeSubmissions'),
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
			]
		];

		if ($this->_filters) {
			$config['filters'] = $this->_filters;
		}

		return $config;
	}
}
