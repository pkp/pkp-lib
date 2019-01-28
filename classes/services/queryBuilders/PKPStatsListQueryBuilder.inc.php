<?php

/**
 * @file classes/services/QueryBuilders/PKPStatsListQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsListQueryBuilder
 * @ingroup query_builders
 *
 * @brief Stats list Query builder
 */

namespace PKP\Services\QueryBuilders;

use PKP\Services\QueryBuilders\BaseQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPStatsListQueryBuilder extends BaseQueryBuilder {

	/** @var int context ID */
	protected $contextId = null;

	/** @var string metric type ojs::counter or omp::counter */
	protected $metricType = null;

	/** @var string defines what stats are needed */
	protected $lookingFor = null;

	/** @var string time segment */
	protected $timeSegment = 'month';

	/** @var array columns (aggregation level) selection */
	protected $columns = array();

	/** @var array report-level filter selection */
	protected $filters = array();

	/** @var string order criteria */
	protected $orderBy = array();

	/** @var boolean if there is no existing object for the given search phrase */
	protected $noObjectForSearchPhrase = null;

	/**
	 * Constructor
	 *
	 * @param $contextId int context ID
	 */
	public function __construct($contextId) {
		parent::__construct();
		$this->contextId = $contextId;
		$this->metricType = $this->getMetricType();
		// Add the metric type and context id filter.
		$filters[STATISTICS_DIMENSION_METRIC_TYPE] = $this->metricType;
		$filters[STATISTICS_DIMENSION_CONTEXT_ID] = $contextId;
	}

	/**
	 * Set the variable that defines what stats are needed
	 *
	 * @param $lookingForDesc array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function lookingFor($lookingForDesc) {
		$this->lookingFor = $lookingForDesc;
		return $this;
	}

	/**
	 * Set time segment
	 *
	 * @param $timeSegment string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function timeSegment($timeSegment) {
		$this->timeSegment = $timeSegment == 'daily' ? STATISTICS_DIMENSION_DAY : STATISTICS_DIMENSION_MONTH;
		return $this;
	}

	/**
	 * Set the columns (aggregation level)
	 * Assumes lookingFor and timeSegment are set
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function columns() {
		switch ($this->lookingFor) {
			case 'orderedSubmissions':
				$this->columns = array(STATISTICS_DIMENSION_SUBMISSION_ID);
				break;
			case 'totalSubmissionsStats':
				$this->columns = array($this->timeSegment, STATISTICS_DIMENSION_ASSOC_TYPE);
				break;
			case 'submissionStats':
				$this->columns = array($this->timeSegment, STATISTICS_DIMENSION_ASSOC_TYPE, STATISTICS_DIMENSION_FILE_TYPE);
				break;
		}
		return $this;
	}

	/**
	 * Set assoc type filter
	 * Assumes lookingFor ais set
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function filterByAssocTypes() {
		switch ($this->lookingFor) {
			case 'orderedSubmissions':
			case 'totalSubmissionsStats':
			case 'submissionStats':
				$this->filters[STATISTICS_DIMENSION_ASSOC_TYPE] = array(ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_SUBMISSION_FILE);
				break;
		}
		return $this;
	}

	/**
	 * Set submission IDs filter
	 *
	 * @param $submissionIds array | int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function filterBySubmissionIds($submissionIds) {
		$this->filters[STATISTICS_DIMENSION_SUBMISSION_ID] = $submissionIds;
		return $this;
	}

	/**
	 * Set section IDs filter
	 *
	 * @param $sectionIds array | int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function filterBySectionIds($sectionIds) {
		$this->filters[STATISTICS_DIMENSION_PKP_SECTION_ID] = $sectionIds;
		return $this;
	}

	/**
	 * Set daily date range (from and to) filter
	 * Assumes that either start or end date exists
	 *
	 * @param $dateStart string optional
	 * @param $dateEnd string optional
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function filterByDateRange($dateStart = null, $dateEnd = null) {
		// pre-assumption: one of the dates exist
		assert($dateStart != null || $dateEnd != null);

		$from = $to = null;
		// convert the date strings to the right format
		if ($dateStart && preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dateStart, $matches) === 1) {
			$from = $matches[1] . $matches[2] . $matches[3];
		}
		if ($dateEnd && preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dateEnd, $matches) === 1) {
			$to = $matches[1] . $matches[2] . $matches[3];
		}
		// set the default value for from and to date, if missing
		if ($to == null) {
			$to = date('Ymd', time());
		} elseif ($from == null) {
			// TO-DO eventually: chose the start date differently ?
			$from = '20010101';
		}
		// set the filter
		$this->filters[STATISTICS_DIMENSION_DAY]['from'] = $from;
		$this->filters[STATISTICS_DIMENSION_DAY]['to'] = $to;
	}

	/**
	 * Set filter according to the given search phrase
	 * Assumes that lookingFor is already set
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function filterBySearchPhrase($searchPhrase) {
		switch ($this->lookingFor) {
			case 'orderedSubmissions':
			case 'totalSubmissionsStats':
				$submissionService = \ServicesContainer::instance()->get('submission');
				$submissions = $submissionService->getSubmissions($this->contextId, array('searchPhrase' => $searchPhrase));
				$submissionIds = array_map(
					function($submission){
						return $submission->getId();
					},
					$submissions
				);
				if (!empty($submissionIds)) {
					$this->filterBySubmissionIds($submissionIds);
				} else {
					$this->noObjectForSearchPhrase = true;
				}
				break;
		}
	}

	/**
	 * Add orderBy statement
	 *
	 * @param $orderBy string
	 * @param $orderDirection string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function orderBy($orderBy, $orderDirection) {
		$orderByColumn = $direction = null;
		switch($orderBy) {
			case 'total':
				$orderByColumn = STATISTICS_METRIC;
				break;
			case 'monthly':
				$orderByColumn = STATISTICS_DIMENSION_MONTH;
				break;
			case 'daily':
				$orderByColumn = STATISTICS_DIMENSION_DAY;
				break;
		}
		switch($orderBy) {
			case 'ASC':
				$direction = STATISTICS_ORDER_ASC;
				break;
			case 'DESC':
				$direction = STATISTICS_ORDER_DESC;
				break;
		}
		$this->orderBy[$orderByColumn] = $direction;
		return $this;
	}

	/**
	 * Execute query builder
	 *
	 * @return object | null Query object
	 */
	public function get() {
		// return null if no submisison matches the search phrase
		if ($this->noObjectForSearchPhrase === true) return null;

		// set columns and filter by assoc types, depending
		// on the stats information we want to receive
		$this->columns();
		$this->filterByAssocTypes();

		$q = Capsule::table('metrics');

		foreach ($this->filters as $column => $values) {
			// The filter array contains STATISTICS_* constants for the filtered
			// hierarchy aggregation level as keys.
			if ($column === STATISTICS_METRIC) {
				$havingClause = true;
				$whereClause = false;
			} else {
				$havingClause = false;
				$whereClause = true;
			}

			if (is_array($values) && isset($values['from'])) {
				// Range filter: The value is a hashed array with from/to entries.
				if ($whereClause) {
					$q->whereBetween($column, array($values['from'], $values['to']));
				} elseif ($havingClause) {
					$q->havingRaw($column . 'BETWEEN ? AND ?', [$values['from'], $values['to']]);
				}
			} else {
				// Element selection filter: The value is a scalar or an
				// unordered array of one or more hierarchy element IDs.
				if (is_array($values) && count($values) === 1) {
					$values = array_pop($values);
				}
				if (is_scalar($values)) {
					if ($whereClause) {
						$q->where($column, '=', $values);
					} elseif ($havingClause) {
						$q->having($column, '=', $values);
					}
				} else {
					if ($whereClause) {
						$q->whereIn($column, $values);
					} elseif ($havingClause) {
						$valuesString = implode(', ', $values);
						$q->havingRaw($column . ' IN (' . $valuesString .')');
					}
				}
			}
		}

		// Replace the current time constant by time values
		// inside the parameters array.
		$params = $q->getBindings();
		$currentTime = array(
			STATISTICS_YESTERDAY => date('Ymd', strtotime('-1 day', time())),
			STATISTICS_CURRENT_MONTH => date('Ym', time())
		);
		foreach ($currentTime as $constant => $time) {
			$currentTimeKeys = array_keys($params, $constant);
			foreach ($currentTimeKeys as $key) {
				$params[$key] = $time;
			}
		}
		$q->setBindings($params);

		// Build the order-by clause.
		foreach ($this->orderBy as $orderColumn => $direction) {
			$q->orderBy($orderColumn, $direction);
		}

		// Allow third-party query statements
		\HookRegistry::call('Stats::getStats::queryObject', array(&$q, $this));

		if (empty($this->columns)) {
			$q->selectRaw('SUM(metric) AS metric');
		} else {
			$selectedColumns = implode(', ', $this->columns);
			$q->select($this->columns)
				->selectRaw('SUM(metric) AS metric')
				->groupBy($this->columns);
		}

		return $q;
	}

	/**
	 * Get the app specific metric type.
	 * @return string
	 */
	protected function getMetricType() {
		$application = \Application::getApplication();
		$applicationName = \Application::getName();
		switch ($applicationName) {
			case 'ojs2':
				return OJS_METRIC_TYPE_COUNTER;
				break;
			case 'omp':
				return OMP_METRIC_TYPE_COUNTER;
				break;
			default:
				assert(false);
		}
	}
}
