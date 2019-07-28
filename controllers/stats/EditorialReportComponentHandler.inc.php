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
	public function __construct($apiUrl, $args = array()) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
		parent::__construct();

		$this->_apiUrl = $apiUrl;
		$this->init($args);
	}

	/**
	 * Given an array with statistics extracts the required editorial information to display in the component
	 * @param $rangedStatistics array An array with a subset of the results from a call to PKPStatsService::getSubmissionStatistics
	 * @param $statistics array An array with the results from a call to PKPStatsService::getSubmissionStatistics
	 */
	public static function extractEditorialStatistics($rangedStatistics, $statistics) {
		$editorialStatistics = [];
		$percentageField = 1;
		$averageField = 2;
		foreach ([
			'SUBMISSION_RECEIVED' => [__('manager.statistics.editorial.submissionsReceived')],
			'SUBMISSION_ACCEPTED' => [__('manager.statistics.editorial.submissionsAccepted')],
			'SUBMISSION_DECLINED_TOTAL' => [__('manager.statistics.editorial.submissionsDeclined')],
			'SUBMISSION_DECLINED_INITIAL' => ['&emsp;' . __('manager.statistics.editorial.submissionsDeclined.deskReject')],
			'SUBMISSION_DECLINED' => ['&emsp;' . __('manager.statistics.editorial.submissionsDeclined.postReview')],
			'SUBMISSION_DECLINED_OTHER' => ['&emsp;' . __('manager.statistics.editorial.submissionsDeclined.other')],
			'SUBMISSION_PUBLISHED' => [__('manager.statistics.editorial.submissionsPublished')],
			'SUBMISSION_DAYS_TO_FIRST_DECIDE' => [__('manager.statistics.editorial.averageDaysToDecide'), $averageField],
			'SUBMISSION_DAYS_TO_ACCEPT' => ['&emsp;' . __('manager.statistics.editorial.averageDaysToAccept'), $averageField],
			'SUBMISSION_DAYS_TO_REJECT' => ['&emsp;' . __('manager.statistics.editorial.averageDaysToReject'), $averageField],
			'SUBMISSION_ACCEPTANCE_RATE' => [__('manager.statistics.editorial.acceptanceRate'), $percentageField],
			'SUBMISSION_REJECTION_RATE' => [__('manager.statistics.editorial.rejectionRate'), $percentageField],
			'SUBMISSION_DECLINED_INITIAL_RATE' => ['&emsp;' . __('manager.statistics.editorial.deskRejectRate'), $percentageField],
			'SUBMISSION_DECLINED_RATE' => ['&emsp;' . __('manager.statistics.editorial.postReviewRejectRate'), $percentageField],
			'SUBMISSION_DECLINED_OTHER_RATE' => ['&emsp;' . __('manager.statistics.editorial.otherRejectRate'), $percentageField]
		] as $field => list($name, $type)) {
			$isPercentage = $type == $percentageField;
			$isAverage = $type == $averageField || $isPercentage;
			$editorialStatistics[] = [
				'name' => $name,
				'period' => round($rangedStatistics[$field], $isPercentage ? 2 : 0) . ($isPercentage ? '%' : ''),
				'average' => round($statistics[($isAverage ? '' : 'AVG_') . $field], $isPercentage ? 2 : 0) . ($isPercentage ? '%' : ''),
				'total' => round($statistics[$field], $isPercentage ? 2 : 0) . ($isPercentage ? '%' : '')
			];
		}
		return $editorialStatistics;
	}

	/**
	 * Given an array with statistics extracts the required user statistics to display in the component
	 * @param $rangedStatistics array An array with a subset of the results from a call to PKPStatsService::getUserStatistics
	 * @param $statistics array An array with the results from a call to PKPStatsService::getUserStatistics
	 */
	public static function extractUserStatistics($rangedStatistics, $statistics) {
		$userStatistics = [];
		foreach ([0 => 'manager.statistics.editorial.registeredUsers'] + Application::getRoleNames(true) as $id => $role) {
			$userStatistics[] = [
				'name' => __($role),
				'period' => (int)$rangedStatistics[$id]['total'],
				'average' => round($statistics[$id]['average']),
				'total' => (int)$statistics[$id]['total']
			];
		}
		return $userStatistics;
	}

	/**
	 * Given an array with statistics extracts the required submission information to display in the chart component
	 * @param $statistics array An array with the results from a call to PKPStatsService::getSubmissionStatistics
	 */
	public static function extractSubmissionChartData($statistics) {
		return [
			[
				'name' => __('manager.publication.submissionStage'),
				'value' => (int)$statistics['ACTIVE_SUBMISSION'],
				'color' => '#d00a0a',
			],
			[
				'name' => __('workflow.review.internalReview'),
				'value' => (int)$statistics['ACTIVE_INTERNAL_REVIEW'],
				'color' => '#e05c14',
			],
			[
				'name' => __('manager.statistics.editorial.externalReview'),
				'value' => (int)$statistics['ACTIVE_EXTERNAL_REVIEW'],
				'color' => '#e08914',
			],
			[
				'name' => __('submission.copyediting'),
				'value' => (int)$statistics['ACTIVE_EDITING'],
				'color' => '#007ab2',
			],
			[
				'name' => __('manager.publication.productionStage'),
				'value' => (int)$statistics['ACTIVE_PRODUCTION'],
				'color' => '#00b28d',
			]
		];
	}

	/**
	 * Initialize the handler with config parameters
	 *
	 * @param $args array Configuration params
	 */
	public function init($args = array()) {
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
	public function getConfig() {
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
					'name' => 'average',
					'label' => __('common.average') . '/' . __('common.year'),
					'value' => 'average'
				],
				[
					'name' => 'total',
					'label' => __('stats.total'),
					'value' => 'total'
				]
			],
			'dateStart' => $this->_dateStart,
			'dateEnd' => $this->_dateEnd,
			'dateEndMax' => $this->_dateEndMax,
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
