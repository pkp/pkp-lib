<?php
/**
 * @file classes/services/QueryBuilders/PKPPublicationQueryBuilder.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationQueryBuilder
 * @ingroup query_builders
 *
 * @brief Class for building database queries for publications
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;
use PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface;

class PKPPublicationQueryBuilder extends BaseQueryBuilder implements EntityQueryBuilderInterface {

	/** @var array get publications for one or more contexts */
	protected $contextIds = [];

	/** @var array get publications for one or more submissions */
	protected $submissionIds = [];

	/** @var int|null whether to limit the number of results returned */
	protected $limit = null;

	/** @var int whether to offset the number of results returned. Use to return a second page of results. */
	protected $offset = 0;

	/**
	 * Set contextIds filter
	 *
	 * @param array|int $contextIds
	 * @return \PKP\Services\QueryBuilders\PKPPublicationQueryBuilder
	 */
	public function filterByContextIds($contextIds) {
		$this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
		return $this;
	}

	/**
	 * Set submissionIds filter
	 *
	 * @param array|int $submissionIds
	 * @return \PKP\Services\QueryBuilders\PKPPublicationQueryBuilder
	 */
	public function filterBySubmissionIds($submissionIds) {
		$this->submissionIds = is_array($submissionIds) ? $submissionIds : [$submissionIds];
		return $this;
	}

	/**
	 * Set query limit
	 *
	 * @param int $count
	 *
	 * @return \PKP\Services\QueryBuilders\PKPPublicationQueryBuilder
	 */
	public function limitTo($count) {
		$this->limit = $count;
		return $this;
	}

	/**
	 * Set how many results to skip
	 *
	 * @param int $offset
	 *
	 * @return \PKP\Services\QueryBuilders\PKPPublicationQueryBuilder
	 */
	public function offsetBy($offset) {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getCount()
	 */
	public function getCount() {
		return $this
			->getQuery()
			->select('p.publication_id')
			->get()
			->count();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getIds()
	 */
	public function getIds() {
		return $this
			->getQuery()
			->select('p.publication_id')
			->pluck('p.publication_id')
			->toArray();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getQuery()
	 * @param $applyOrder boolean True iff an order by version (ascending) should be applied
	 */
	public function getQuery($applyOrder = true) {
		$this->columns = ['*'];
		$q = Capsule::table('publications as p');

		if (!empty($this->contextIds)) {
			$q->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
				->whereIn('s.context_id', $this->contextIds);
		}

		if (!empty($this->submissionIds)) {
			$q->whereIn('p.submission_id', $this->submissionIds);
		}

		// Limit and offset results for pagination
		if (!is_null($this->limit)) {
			$q->limit($this->limit);
		}
		if (!empty($this->offset)) {
			$q->offset($this->offset);
		}

		if ($applyOrder) {
			// Order by version number
			$q->orderBy('p.version', 'asc');
		}

		// Add app-specific query statements
		\HookRegistry::call('Publication::getMany::queryObject', array(&$q, $this));

		$q->select($this->columns);

		return $q;
	}

	/**
	 * Get the oldest and most recent publication dates for publications
	 *
	 * @return object Query object
	 */
	public function getDateBoundaries() {
		return $this->getQuery(false)
			->select([
				Capsule::raw('MIN(p.date_published), MAX(p.date_published)')
			]);
	}

	/**
	 * Get a query builder to retrieve publications by their urlPath
	 *
	 * @param string $urlPath
	 * @param int $contextId
	 * @return Illuminate\Database\Query\Builder
	 */
	public function getQueryByUrlPath($urlPath, $contextId) {
		return Capsule::table('publications as p')
			->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
			->where('s.context_id', '=', $contextId)
			->where('p.url_path', '=', $urlPath);
	}

	/**
	 * Is the urlPath a duplicate?
	 *
	 * Checks if the urlPath is used in any publication other than the
	 * submission passed.
	 *
	 * A urlPath may be duplicated across more than one publication of the
	 * same submission. But two publications in two different submissions
	 * can not share the same urlPath.
	 *
	 * This is only applied within a single context.
	 *
	 * @param string $urlPath
	 * @param int $submissionId
	 * @param int $contextId
	 * @return boolean
	 */
	public function isDuplicateUrlPath($urlPath, $submissionId, $contextId) {
		return (bool) Capsule::table('publications as p')
			->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
			->where('url_path', '=' , $urlPath)
			->where('p.submission_id', '!=', $submissionId)
			->where('s.context_id', '=', $contextId)
			->count();
	}
}
