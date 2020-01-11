<?php
/**
 * @file classes/services/QueryBuilders/PKPAuthorQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorQueryBuilder
 * @ingroup query_builders
 *
 * @brief Class for building database queries for authors
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class PKPAuthorQueryBuilder extends BaseQueryBuilder {

	/** @var array get authors for one or more contexts */
	protected $contextIds = [];

	/** @var string get authors with a family name */
	protected $familyName = '';

	/** @var string get authors with a given name */
	protected $givenName = '';

	/** @var array get authors for one or more publications */
	protected $publicationIds = [];

	/** @var bool whether to return only a count of results */
	protected $countOnly = null;

	/**
	 * Filter by one or more contexts
	 *
	 * @param array|int $contextIds
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 */
	public function filterByContextIds($contextIds) {
		$this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
		return $this;
	}

	/**
	 * Filter by the given and family name
	 *
	 * @param string $givenName
	 * @param string $familyName
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 */
	public function filterByName($givenName, $familyName) {
		$this->givenName = $givenName;
		$this->familyName = $familyName;
		return $this;
	}

	/**
	 * Set publicationIds filter
	 *
	 * @param array|int $publicationIds
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 */
	public function filterByPublicationIds($publicationIds) {
		$this->publicationIds = is_array($publicationIds) ? $publicationIds : [$publicationIds];
		return $this;
	}

	/**
	 * Whether to return only a count of results
	 *
	 * @param $enable bool
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 */
	public function countOnly($enable = true) {
		$this->countOnly = $enable;
		return $this;
	}

	/**
	 * Execute query builder
	 *
	 * @return object Query object
	 */
	public function get() {
		$this->columns = ['*'];
		$q = Capsule::table('authors as a');

		if (!empty($this->contextIds)) {
			$q->leftJoin('publications as p', 'a.publication_id', '=', 'p.publication_id')
				->leftJoin('submissions as s', 'p.submission_id', '=', 's.submission_id')
				->whereIn('s.context_id', $this->contextIds);
		}

		if (!empty($this->familyName) || !empty($this->givenName)) {
			$familyName = $this->familyName;
			$givenName = $this->givenName;
			$q->leftJoin('author_settings as fn', 'a.author_id', '=', 'fn.author_id')
				->where(function($q) use ($familyName) {
					$q->where('fn.setting_name', '=', 'familyName');
					$q->where('fn.setting_value', '=', $familyName);
				});
			$q->leftJoin('author_settings as gn', 'a.author_id', '=', 'gn.author_id')
				->where(function($q) use ($givenName) {
					$q->where('gn.setting_name', '=', 'givenName');
					$q->where('gn.setting_value', '=', $givenName);
				});
		}

		if (!empty($this->publicationIds)) {
			$q->whereIn('a.publication_id', $this->publicationIds);
		}

		// Add app-specific query statements
		\HookRegistry::call('Author::getMany::queryObject', array(&$q, $this));

		if (!empty($this->countOnly)) {
			$q->select(Capsule::raw('count(*) as author_count'));
		} else {
			$q->select($this->columns);
		}

		return $q;
	}
}
