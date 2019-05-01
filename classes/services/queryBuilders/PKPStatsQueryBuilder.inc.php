<?php

/**
 * @file classes/services/QueryBuilders/PKPStatsQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics table.
 */

namespace PKP\Services\QueryBuilders;

use PKP\Services\QueryBuilders\BaseQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPStatsQueryBuilder extends BaseQueryBuilder {

	/** @var array Include records for these objects. Requires $assocType to be specified. */
	protected $assocIds = [];

	/**
	 * Include records for these object types.
	 *
	 * One of ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_CONTEXT, ASSOC_TYPE_ISSUE,
	 * 	ASSOC_TYPE_SUBMISSION_FILE, ASSOC_TYPE_REPRESENTATION
	 *
	 * @var int
	 */
	protected $assocTypes = [];

	/** @var array Include records for these contexts */
	protected $contextIds = [];

	/** @var string Include records from this date or before. Default: yesterday's date */
	protected $dateEnd;

	/** @var string Include records from this date or after. Default: STATISTICS_EARLIEST_DATE */
	protected $dateStart;

	/** @var array Include records for these file types: STATISTICS_FILE_TYPE_* */
	protected $fileTypes;

	/** @var array Include records from for these sections (or series in OMP) */
	protected $sectionIds = [];

	/** @var array Include records for these submissions */
	protected $submissionIds = [];

	/**
	 * Set the contexts to get records for
	 *
	 * @param array|int $contextIds
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function filterByContexts($contextIds) {
		$this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
		return $this;
	}

	/**
	 * Set the submissions to get records for
	 *
	 * @param array|int $submissionIds
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function filterBySubmissions($submissionIds) {
		$this->submissionIds = is_array($submissionIds) ? $submissionIds : [$submissionIds];
		return $this;
	}

	/**
	 * Set the assocTypes to get records for
	 *
	 * @param array|int $assocTypes
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function filterByAssocTypes($assocTypes) {
		$this->assocTypes = is_array($assocTypes) ? $assocTypes : [$assocTypes];
		return $this;
	}

	/**
	 * Set the assoc type object ids to get records for
	 *
	 * @param array|int $assocIds
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function filterByAssocIds($assocIds) {
		$this->assocIds = is_array($assocIds) ? $assocIds : [$assocIds];
		return $this;
	}

	/**
	 * Set the galley file type to get records for
	 *
	 * @param array|int $fileTypes STATISTICS_FILE_TYPE_*
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function filterByFileTypes($fileTypes) {
		$this->fileTypes = is_array($fileTypes) ? $fileTypes : [$fileTypes];
		return $this;
	}

	/**
	 * Set the date before which to get records
	 *
	 * @param string $dateEnd YYYY-MM-DD
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function before($dateEnd) {
		$this->dateEnd = str_replace('-', '', $dateEnd);
		return $this;
	}

	/**
	 * Set the date after which to get records
	 *
	 * @param string $dateStart YYYY-MM-DD
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	public function after($dateStart) {
		$this->dateStart = str_replace('-', '', $dateStart);
		return $this;
	}

	/**
	 * Get all matching records
	 *
	 * @return QueryObject
	 */
	public function getRecords() {
		return $this->_getObject()->select('*');
	}

	/**
	 * Get the sum of all matching records
	 *
	 * Use this method to get the total X views. Pass a
	 * $groupBy argument to get the total X views for each
	 * object, grouped by one or more columns.
	 *
	 * @param array $groupBy One or more columns to group by
	 * @return QueryObject
	 */
	public function getSum($groupBy = []) {
		$q = $this->_getObject();

		$q->select(array_merge(
			[Capsule::raw('SUM(metric) as metric')],
			$groupBy
		));

		if (!empty($groupBy)) {
			$q->groupBy($groupBy);
		}

		return $q;
	}

	/**
	 * Get the sum of all matching records for one day or month
	 *
	 * @param string $date A month or day in the format YYYY-MM or YYYY-MM-DD
	 * @return QueryObject
	 */
	public function getTimeline($date) {
		$q = $this->_getObject();
		$q->select(Capsule::raw('SUM(metric) as metric'));
		if (strlen($date) === 10) {
			$q->where(STATISTICS_DIMENSION_DAY, '=', str_replace('-', '', $date));
		} else {
			$q->where(STATISTICS_DIMENSION_MONTH, '=', str_replace('-', '', $date));
		}
		return $q;
	}

	/**
	 * Generate a query object based on the configured conditions.
	 *
	 * Public methods should call this method to set up the query
	 * object and apply any additional selection, grouping and
	 * ordering conditions.
	 *
	 * @return QueryObject
	 */
	protected function _getObject() {
		$q = Capsule::table('metrics');

		if (!empty($this->contextIds)) {
			$q->whereIn(STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
		}

		if (!empty($this->submissionIds)) {
			$q->whereIn(STATISTICS_DIMENSION_SUBMISSION_ID, $this->submissionIds);
		}

		if (!empty($this->sectionIds)) {
			$q->whereIn(STATISTICS_DIMENSION_PKP_SECTION_ID, $this->sectionIds);
		}

		if (!empty($this->assocTypes)) {
			$q->whereIn(STATISTICS_DIMENSION_ASSOC_TYPE, $this->assocTypes);

			if (!empty($this->assocIds)) {
				$q->whereIn(STATISTICS_DIMENSION_ASSOC_ID, $this->assocIds);
			}
		}

		if (!empty($this->fileTypes)) {
			$q->whereIn(STATISTICS_DIMENSION_FILE_TYPE, $this->fileTypes);
		}

		$q->whereBetween(STATISTICS_DIMENSION_DAY, [$this->dateStart, $this->dateEnd]);

		$q->where(STATISTICS_DIMENSION_METRIC_TYPE, '=', METRIC_TYPE_COUNTER);

		\HookRegistry::call('Stats::queryObject', array($q, $this));

		return $q;
	}
}

