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

	/** @var string metric type OJS_METRIC_TYPE_COUNTER or OMP_METRIC_TYPE_COUNTER */
	protected $metricType = null;

	/** @var string defines what stats are needed */
	protected $lookingFor = null;

	/** @var string time segment, STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY */
	protected $timeSegment = STATISTICS_DIMENSION_MONTH;

	/** @var string order column */
	protected $orderColumn = null;

	/** @var string order direction */
	protected $orderDirection = STATISTICS_ORDER_DESC;

	/** @var array columns (aggregation level) selection */
	protected $columns = array();

	/** @var array report-level filter selection */
	protected $filters = array();

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
		$this->filters[STATISTICS_DIMENSION_METRIC_TYPE] = $this->metricType;
		$this->filters[STATISTICS_DIMENSION_CONTEXT_ID] = $contextId;
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
		$this->timeSegment = $timeSegment == STATISTICS_DIMENSION_DAY ? STATISTICS_DIMENSION_DAY : STATISTICS_DIMENSION_MONTH;
		return $this;
	}

	/**
	 * Set order column
	 *
	 * @param $orderColumn string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function orderColumn($orderColumn) {
		switch($orderColumn) {
			case 'total':
				$this->orderColumn = STATISTICS_METRIC;
				break;
			case 'month':
				$this->orderColumn = STATISTICS_DIMENSION_MONTH;
				break;
			case 'day':
				$this->orderColumn = STATISTICS_DIMENSION_DAY;
				break;
		}
		return $this;
	}

	/**
	 * Set order direction
	 *
	 * @param $orderDirection string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder
	 */
	public function orderDirection($orderDirection) {
		switch($orderDirection) {
			case 'ASC':
				$this->orderDirection = STATISTICS_ORDER_ASC;
				break;
			case 'DESC':
				$this->orderDirection = STATISTICS_ORDER_DESC;
				break;
		}
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
	 * Set (OJS) section i.e. (OMP) series IDs filter
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

		$dateStart = $dateStart ? str_replace('-', '', $dateStart) : STATISTICS_EARLIEST_DATE;
		$dateEnd = $dateEnd ? str_replace('-', '', $dateEnd) : date('Ymd', time());

		// set the filter
		$this->filters[STATISTICS_DIMENSION_DAY]['from'] = $dateStart;
		$this->filters[STATISTICS_DIMENSION_DAY]['to'] = $dateEnd;
	}

	/**
	 * Execute query builder
	 *
	 * @return object | null Query object
	 */
	public function get() {
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
					$q->havingRaw($column . ' BETWEEN ? AND ?', [$values['from'], $values['to']]);
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
		if ($this->orderColumn != null) {
			$q->orderBy($this->orderColumn, $this->orderDirection);
		}

		// Allow third-party query statements
		\HookRegistry::call('Stats::getStats::queryObject', array(&$q, $this));

		if (empty($this->columns)) {
			$q->selectRaw('SUM(metric) AS metric');
		} else {
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
