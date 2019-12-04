<?php
/**
 * @file components/PKPStatsComponent.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsComponent
 * @ingroup classes_components_stats
 *
 * @brief A class to prepare the data object for a statistics UI component
 */
namespace PKP\components;

import('classes.statistics.StatisticsHelper');

class PKPStatsComponent {
	/** @var string The URL to the /stats API endpoint */
	public $apiUrl = '';

	/** @var array Configuration for the columns to display in the table */
	public $tableColumns = [];

	/** @var string Retrieve stats after this date */
	public $dateStart = '';

	/** @var string Retrieve stats before this date */
	public $dateEnd = '';

	/** @var array Quick options to provide for configuring the date range */
	public $dateRangeOptions = [];

	/** @var array|null Configuration assoc array for available filters */
	public $filters = null;

	/** @var array Localized strings to pass to the component */
	public $i18n = [];

	/**
	 * Constructor
	 *
	 * @param $apiUrl string The URL to fetch stats from
	 * @param $args array Optional arguments
	 */
	function __construct($apiUrl, $args = array()) {
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		$this->apiUrl = $apiUrl;
		$this->init($args);
	}

	/**
	 * Initialize the handler with config parameters
	 *
	 * @param $args array Configuration params
	 */
	public function init($args = array()) {
		foreach ($args as $key => $value) {
			if (property_exists( $this, $key)) {
				$this->{$key} = $value;
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
			'apiUrl' => $this->apiUrl,
			'tableColumns' => $this->tableColumns,
			'dateStart' => $this->dateStart,
			'dateEnd' => $this->dateEnd,
			'dateEndMax' => date('Y-m-d', strtotime('yesterday')),
			'dateRangeOptions' => $this->dateRangeOptions,
			'activeFilters' => [],
			'isLoadingItems' => false,
			'isSidebarVisible' => false,
			'i18n' => array_merge(
				[
					'filter' => __('common.filter'),
					'filterRemove' => __('common.filterRemove'),
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
				],
				$this->i18n
			),
		];

		if ($this->filters) {
			$config['filters'] = $this->filters;
		}

		return $config;
	}
}
