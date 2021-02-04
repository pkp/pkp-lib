<?php
/**
 * @file classes/services/QueryBuilders/PKPAuthorQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorQueryBuilder
 * @ingroup query_builders
 *
 * @brief Class for building database queries for authors
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;
use PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface;

class PKPAuthorQueryBuilder implements EntityQueryBuilderInterface {

	/** @var array get authors for one or more contexts */
	protected $contextIds = [];

	/** @var string get authors with a family name */
	protected $familyName = '';

	/** @var string get authors with a given name */
	protected $givenName = '';

	/** @var array get authors for one or more publications */
	protected $publicationIds = [];

	/** @var string get authors with a specified country code */
	protected $country = '';

	/** @var string get authors with a specified affiliation */
	protected $affiliation = '';

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
	 * Filter by the specified country code
	 *
	 * @param $country string Country code (2-letter)
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 * */
	public function filterByCountry($country) {
		$this->country = $country;
		return $this;
	}

	/**
	 * Filter by the specified affiliation code
	 *
	 * @param $country string Affiliation
	 * @return \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder
	 * */
	public function filterByAffiliation($affiliation) {
		$this->affiliation = $affiliation;
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
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getCount()
	 */
	public function getCount() {
		return $this
			->getQuery()
			->select('a.author_id')
			->get()
			->count();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getIds()
	 */
	public function getIds() {
		return $this
			->getQuery()
			->select('a.author_id')
			->pluck('a.author_id')
			->toArray();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getQuery()
	 */
	public function getQuery() {
		$this->columns = ['*', 's.locale AS submission_locale'];
		$q = Capsule::table('authors as a');
		$q->leftJoin('publications as p', 'a.publication_id', '=', 'p.publication_id');
		$q->leftJoin('submissions as s', 'p.submission_id', '=', 's.submission_id');

		if (!empty($this->contextIds)) {
				$q->whereIn('s.context_id', $this->contextIds);
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

		if (!empty($this->country)) {
			$country = $this->country;
			$q->join('author_settings as cs', 'a.author_id', '=', 'cs.author_id')
				->where(function($q) use ($country) {
					$q->where('cs.setting_name', '=', 'country');
					$q->where('cs.setting_value', '=', $country);
				});
		}

		if (!empty($this->affiliation)) {
			$affiliation = $this->affiliation;
			$q->join('author_settings as afs', 'a.author_id', '=', 'afs.author_id')
				->where(function($q) use ($affiliation) {
					$q->where('afs.setting_name', '=', 'affiliation');
					$q->where('afs.setting_value', '=', $affiliation);
				});
		}

		$q->orderBy('a.seq', 'ASC');

		// Add app-specific query statements
		\HookRegistry::call('Author::getMany::queryObject', array(&$q, $this));

		$q->select($this->columns);

		return $q;
	}
}
