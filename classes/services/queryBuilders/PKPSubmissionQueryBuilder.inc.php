<?php

/**
 * @file classes/services/QueryBuilders/PKPSubmissionQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission list Query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;
use PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface;

abstract class PKPSubmissionQueryBuilder implements EntityQueryBuilderInterface {

	/** @var int|string|null Context ID or CONTEXT_ID_ALL to get from all contexts */
	protected $categoryIds = null;

	/** @var int|null Context ID */
	protected $contextId = null;

	/** @var array list of columns for query */
	protected $columns = array();

	/** @var string order by column */
	protected $orderColumn = 's.date_submitted';

	/** @var string order by direction */
	protected $orderDirection = 'DESC';

	/** @var array|null list of statuses */
	protected $statuses = null;

	/** @var array|null list of stage ids */
	protected $stageIds = null;

	/** @var int|array user IDs */
	protected $assignedTo = [];

	/** @var string|null search phrase */
	protected $searchPhrase = null;

	/** @var bool whether to return only incomplete results */
	protected $isIncomplete = false;

	/** @var bool|null whether to return only submissions with overdue review assignments */
	protected $isOverdue = false;

	/** @var int|null whether to return only submissions that have not been modified for last X days */
	protected $daysInactive = null;

	/** @var int|null whether to limit the number of results returned */
	protected $limit = null;

	/** @var int whether to offset the number of results returned. Use to return a second page of results. */
	protected $offset = 0;

	/**
	 * Set context submissions filter
	 *
	 * @param int|string $contextId
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByContext($contextId) {
		$this->contextId = $contextId;
		return $this;
	}

	/**
	 * Set result order column and direction
	 *
	 * @param string $column
	 * @param string $direction
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function orderBy($column, $direction = 'DESC') {
		// Bring in orderby constants
		import('classes.submission.SubmissionDAO');
		if ($column === 'lastModified') {
			$this->orderColumn = 's.last_modified';
		} elseif ($column === 'dateLastActivity') {
			$this->orderColumn = 's.date_last_activity';
		} elseif ($column === 'title') {
			$this->orderColumn = Capsule::raw('COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)');
		} elseif ($column === 'seq') {
			$this->orderColumn = 'po.seq';
		} elseif ($column === ORDERBY_DATE_PUBLISHED) {
			$this->orderColumn = 'po.date_published';
		} else {
			$this->orderColumn = 's.date_submitted';
		}
		$this->orderDirection = $direction;
		return $this;
	}

	/**
	 * Set category filter
	 *
	 * @param int|array|null $categoryIds
	 *
	 * @return \OMP\Services\QueryBuilders\SubmissionListQueryBuilder
	 */
	public function filterByCategories($categoryIds) {
		if (!is_null($categoryIds) && !is_array($categoryIds)) {
			$categoryIds = array($categoryIds);
		}
		$this->categoryIds = $categoryIds;
		return $this;
	}

	/**
	 * Set statuses filter
	 *
	 * @param int|array $statuses
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByStatus($statuses) {
		if (!is_null($statuses) && !is_array($statuses)) {
			$statuses = array($statuses);
		}
		$this->statuses = $statuses;
		return $this;
	}

	/**
	 * Set stage filter
	 *
	 * @param int|array $stageIds
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByStageIds($stageIds) {
		if (!is_null($stageIds) && !is_array($stageIds)) {
			$stageIds = array($stageIds);
		}
		$this->stageIds = $stageIds;
		return $this;
	}

	/**
	 * Set incomplete submissions filter
	 *
	 * @param boolean $isIncomplete
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByIncomplete($isIncomplete) {
		$this->isIncomplete = $isIncomplete;
		return $this;
	}

	/**
	 * Set overdue submissions filter
	 *
	 * @param boolean $isOverdue
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByOverdue($isOverdue) {
		$this->isOverdue = $isOverdue;
		return $this;
	}

	/**
	 *  Set inactive submissions filter
	 *
	 * @param int $daysInactive
	 *
	 * @return \OJS\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function filterByDaysInactive($daysInactive) {
		$this->daysInactive = $daysInactive;
		return $this;
	}

	/**
	 * Limit results to a specific user's submissions
	 *
	 * @param int|array $assignedTo List of assigned user ids or -1 to
	 *   get submissions with no user assigned.
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function assignedTo($assignedTo) {
		$this->assignedTo = $assignedTo;
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
	 * Set query limit
	 *
	 * @param int $count
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
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
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
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
			->select('s.submission_id')
			->get()
			->count();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getIds()
	 */
	public function getIds() {
		return $this
			->getQuery()
			->select('s.submission_id')
			->pluck('s.submission_id')
			->toArray();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getQuery()
	 */
	public function getQuery() {
		$this->columns[] = 's.*';
		$q = Capsule::table('submissions as s')
					->orderBy($this->orderColumn, $this->orderDirection)
					->groupBy('s.submission_id');

		// context
		// Never permit a query without a context_id clause unless the CONTEXT_ID_ALL wildcard
		// has been set explicitely.
		if (is_null($this->contextId)) {
			$q->where('s.context_id', '=', CONTEXT_ID_NONE);
		} elseif ($this->contextId !== CONTEXT_ID_ALL) {
			$q->where('s.context_id', '=' , $this->contextId);
		}

		// order by title
		if (is_object($this->orderColumn) && $this->orderColumn->getValue() === 'COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)') {
			$locale = \AppLocale::getLocale();
			$this->columns[] = Capsule::raw('COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)');
			$q->leftJoin('publications as publication_tlp', 's.current_publication_id', '=', 'publication_tlp.publication_id')
				->leftJoin('publication_settings as publication_tlps', 'publication_tlp.publication_id', '=', 'publication_tlps.publication_id')
				->where('publication_tlps.setting_name', '=', 'title')
				->where('publication_tlps.locale', '=', $locale);
			$q->leftJoin('publications as publication_tlpl', 's.current_publication_id', '=', 'publication_tlpl.publication_id')
				->leftJoin('publication_settings as publication_tlpsl', 'publication_tlp.publication_id', '=', 'publication_tlpsl.publication_id')
				->where('publication_tlpsl.setting_name', '=', 'title')
				->where('publication_tlpsl.locale', '=', Capsule::raw('s.locale'));
			$q->groupBy(Capsule::raw('COALESCE(publication_tlps.setting_value, publication_tlpsl.setting_value)'));
		}

		// order by publication sequence
		if ($this->orderColumn === 'po.seq') {
			$this->columns[] = 'po.seq';
			$q->leftJoin('publications as po', 's.current_publication_id', '=', 'po.publication_id');
			$q->groupBy('po.seq');

		// order by date of current version's publication
		} else if ($this->orderColumn === 'po.date_published') {
			$this->columns[] = 'po.date_published';
			$q->leftJoin('publications as po', 's.current_publication_id', '=', 'po.publication_id');
			$q->groupBy('po.date_published');
		}

		// statuses
		if (!is_null($this->statuses)) {
			$q->whereIn('s.status', $this->statuses);
		}

		// stage ids
		if (!is_null($this->stageIds)) {
			$q->whereIn('s.stage_id', $this->stageIds);
		}

		// incomplete submissions
		if ($this->isIncomplete) {
			$q->where('s.submission_progress', '>', 0);
		}

		//inactive for X days
		if ($this->daysInactive) {
			$q->where('s.date_last_activity', '<', \Core::getCurrentDate(strtotime('-'.$this->daysInactive.' days')));
		}

		// overdue submissions
		if ($this->isOverdue) {
			$q->leftJoin('review_assignments as raod', 'raod.submission_id', '=', 's.submission_id')
				->leftJoin('review_rounds as rr', function($table) {
					$table->on('rr.submission_id', '=', 's.submission_id');
					$table->on('raod.review_round_id', '=', 'rr.review_round_id');
				});
			// Only get overdue assignments on active review rounds
			import('lib.pkp.classes.submission.reviewRound.ReviewRound');
			$q->where('rr.status', '!=', REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW);
			$q->where('rr.status', '!=', REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL);
			$q->where('rr.status', '!=', REVIEW_ROUND_STATUS_ACCEPTED);
			$q->where('rr.status', '!=', REVIEW_ROUND_STATUS_DECLINED);
			$q->where(function ($q) {
				$q->where('raod.declined', '<>', 1);
				$q->where('raod.cancelled', '<>', 1);
				$q->where(function ($q) {
					$q->where('raod.date_due', '<', \Core::getCurrentDate(strtotime('tomorrow')));
					$q->whereNull('raod.date_completed');
				});
				$q->orWhere(function ($q) {
					$q->where('raod.date_response_due', '<', \Core::getCurrentDate(strtotime('tomorrow')));
					$q->whereNull('raod.date_confirmed');
				});
			});
		}

		// Assigned to
		$isAssignedOnly = !empty($this->assignedTo) && $this->assignedTo !== -1;
		if ($isAssignedOnly) {
			$assignedTo = $this->assignedTo;

			// Stage assignments
			$q->leftJoin('stage_assignments as sa', function($table) use ($assignedTo) {
				$table->on('s.submission_id', '=', 'sa.submission_id');
				$table->whereIn('sa.user_id', $assignedTo);
			});

			// Review assignments
			$q->leftJoin('review_assignments as ra', function($table) use ($assignedTo) {
				$table->on('s.submission_id', '=', 'ra.submission_id');
				$table->on('ra.declined', '=', Capsule::raw((int) 0));
				$table->on('ra.cancelled', '=', Capsule::raw((int) 0));
				$table->whereIn('ra.reviewer_id', $assignedTo);
			});

			$q->where(function($q) {
				$q->whereNotNull('sa.stage_assignment_id');
				$q->orWhereNotNull('ra.review_id');
			});
		} elseif ($this->assignedTo === -1) {
			$sub = Capsule::table('stage_assignments')
						->select(Capsule::raw('count(stage_assignments.stage_assignment_id)'))
						->leftJoin('user_groups','stage_assignments.user_group_id','=','user_groups.user_group_id')
						->where('stage_assignments.submission_id', '=', Capsule::raw('s.submission_id'))
						->whereIn('user_groups.role_id', array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR));

			$q->whereNotNull('s.date_submitted')
				->mergeBindings($sub)
				->where(Capsule::raw('(' . $sub->toSql() . ')'),'=','0')
				->groupBy('s.date_submitted'); // postgres compatibility
		}

		// search phrase
		if (!empty($this->searchPhrase)) {
			$words = explode(' ', $this->searchPhrase);
			if (count($words)) {
				$q->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
					->leftJoin('publication_settings as ps','p.publication_id','=','ps.publication_id')
					->leftJoin('authors as au','p.publication_id','=','au.publication_id')
					->leftJoin('author_settings as aus', 'aus.author_id', '=', 'au.author_id');

				foreach ($words as $word) {
					$word = strtolower(addcslashes($word, '%_'));
					$q->where(function($q) use ($word, $isAssignedOnly)  {
						$q->where(function($q) use ($word) {
							$q->where('ps.setting_name', 'title');
							$q->where(Capsule::raw('lower(ps.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word) {
							$q->where('aus.setting_name', IDENTITY_SETTING_GIVENNAME);
							$q->where(Capsule::raw('lower(aus.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word, $isAssignedOnly) {
							$q->where('aus.setting_name', IDENTITY_SETTING_FAMILYNAME);
							$q->where(Capsule::raw('lower(aus.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word, $isAssignedOnly) {
							$q->where('aus.setting_name', 'orcid');
							$q->where(Capsule::raw('lower(aus.setting_value)'), '=', "{$word}");
						});
						// Prevent reviewers from matching searches by author name
						if ($isAssignedOnly) {
							$q->whereNull('ra.reviewer_id');
						}
						if (ctype_digit((string) $word)) {
							$q->orWhere('s.submission_id', '=', $word);
						}
					});
				}

			}
		}

		// Category IDs
		if (!empty($this->categoryIds)) {
			$q->leftJoin('publication_categories as pc', 's.current_publication_id', '=', 'pc.publication_id')
				->whereIn('pc.category_id', $this->categoryIds);
		}

		// Limit and offset results for pagination
		if (!is_null($this->limit)) {
			$q->limit($this->limit);
		}
		if (!empty($this->offset)) {
			$q->offset($this->offset);
		}

		// Add app-specific query statements
		\HookRegistry::call('Submission::getMany::queryObject', array(&$q, $this));

		$q->select($this->columns);

		return $q;
	}

	/**
	 * Execute additional actions for app-specific query objects
	 *
	 * @param object Query object
	 * @return object Query object
	 */
	abstract function appGet($q);
}
