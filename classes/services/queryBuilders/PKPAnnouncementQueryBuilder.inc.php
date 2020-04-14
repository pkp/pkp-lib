<?php
/**
 * @file classes/services/QueryBuilders/PKPAnnouncementQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementQueryBuilder
 * @ingroup query_builders
 *
 * @brief Class for building database queries for announcements
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class PKPAnnouncementQueryBuilder extends BaseQueryBuilder {

	/** @var array get announcements for one or more contexts */
	protected $contextIds = [];

	/** @var string get announcements matching one or more words in this phrase */
	protected $searchPhrase = '';

	/** @var array get announcements with one of these typeIds */
	protected $typeIds = [];

	/** @var bool whether to return only a count of results */
	protected $countOnly = null;

	/**
	 * Set contextIds filter
	 *
	 * @param array|int $contextIds
	 * @return \PKP\Services\QueryBuilders\PKPAnnouncementQueryBuilder
	 */
	public function filterByContextIds($contextIds) {
		$this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
		return $this;
	}

	/**
	 * Set type filter
	 *
	 * @param array|int $typeIds
	 * @return \PKP\Services\QueryBuilders\PKPAnnouncementQueryBuilder
	 */
	public function filterByTypeIds($typeIds) {
		$this->typeIds = is_array($typeIds) ? $typeIds : [$typeIds];
		return $this;
	}

	/**
	 * Set query search phrase
	 *
	 * @param string $phrase
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function searchPhrase($phrase) {
		$this->searchPhrase = $phrase;
		return $this;
	}

	/**
	 * Whether to return only a count of results
	 *
	 * @param $enable bool
	 * @return \PKP\Services\QueryBuilders\PKPAnnouncementQueryBuilder
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
		$q = Capsule::table('announcements as a');

		if (!empty($this->contextIds)) {
			$q->whereIn('a.assoc_id', $this->contextIds);
		}

		if (!empty($this->typeIds)) {
			$q->whereIn('a.type_id', $this->typeIds);
		}

		// search phrase
		if (!empty($this->searchPhrase)) {
			$words = explode(' ', $this->searchPhrase);
			if (count($words)) {
				$q->leftJoin('announcement_settings as as','a.announcement_id','=','as.announcement_id');
				foreach ($words as $word) {
					$word = strtolower(addcslashes($word, '%_'));
					$q->where(function($q) use ($word)  {
						$q->where(function($q) use ($word) {
							$q->where('as.setting_name', 'title');
							$q->where(Capsule::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word) {
							$q->where('as.setting_name', 'descriptionShort');
							$q->where(Capsule::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word) {
							$q->where('as.setting_name', 'description');
							$q->where(Capsule::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
						});
					});
				}
			}
		}

		$q->orderBy('a.date_posted', 'desc');

		// Add app-specific query statements
		\HookRegistry::call('Announcement::getMany::queryObject', array(&$q, $this));

		if (!empty($this->countOnly)) {
			$q->select(Capsule::raw('count(*) as count'));
		} else {
			$q->select($this->columns);
		}

		return $q;
	}
}
