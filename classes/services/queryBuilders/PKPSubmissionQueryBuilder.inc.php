<?php

/**
 * @file classes/services/QueryBuilders/PKPSubmissionQueryBuilder.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission list Query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

abstract class PKPSubmissionQueryBuilder extends BaseQueryBuilder {

	/** @var int|string|null Context ID or '*' to get from all contexts */
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

	/** @var int|null user ID */
	protected $assigneeId = null;

	/** @var string|null search phrase */
	protected $searchPhrase = null;

	/** @var string|null return a Submission or PublishedArticle\PublishedMonograph */
	protected $returnObject = null;

	/** @var bool|null whether to return only a count of results */
	protected $countOnly = null;

	/** @var bool whether to return only incomplete results */
	protected $isIncomplete = false;

	/** @var bool|null whether to return only submissions with overdue review assignments */
	protected $isOverdue = false;

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
		if ($column === 'lastModified') {
			$this->orderColumn = 's.last_modified';
		} elseif ($column === 'title') {
			$this->orderColumn = 'st.setting_value';
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
	 * Limit results to a specific user's submissions
	 *
	 * @param int $assigneeId
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function assignedTo($assigneeId) {
		$this->assigneeId = $assigneeId;
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
	 * Return Submission or PublishedArticle|PublishedMonograph objects
	 *
	 * @param string $returnObject
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
	 */
	public function returnObject($returnObject) {
		$this->returnObject = $returnObject;
		return $this;
	}

	/**
	 * Whether to return only a count of results
	 *
	 * @param bool $enable
	 *
	 * @return \APP\Services\QueryBuilders\SubmissionQueryBuilder
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
		$this->columns[] = 's.*';
		$q = Capsule::table('submissions as s')
					->orderBy($this->orderColumn, $this->orderDirection)
					->groupBy('s.submission_id');

		// context
		// Never permit a query without a context_id clause unless the '*' wildcard
		// has been set explicitely.
		if (is_null($this->contextId)) {
			$q->where('s.context_id', '=', CONTEXT_ID_NONE);
		} elseif ($this->contextId !== '*') {
			$q->where('s.context_id', '=' , $this->contextId);
		}

		// order by title
		if ($this->orderColumn === 'st.setting_value') {
			$q->leftJoin('submission_settings as st', 's.submission_id', '=', 'st.submission_id')
				->where('st.setting_name', '=', 'title');
			$q->groupBy('st.setting_value');
		}

		// return object
		if ($this->returnObject === SUBMISSION_RETURN_PUBLISHED) {
			$this->columns[] = 'ps.*';
			$q->leftJoin('published_submissions as ps','ps.submission_id','=','s.submission_id')
				->groupBy('ps.date_published');
			$q->whereNotNull('ps.published_submission_id');
			$q->groupBy('ps.published_submission_id');
		}

		// statuses
		if (!is_null($this->statuses)) {
			import('lib.pkp.classes.submission.Submission'); // STATUS_ constants
			if (in_array(STATUS_PUBLISHED, $this->statuses) && $this->returnObject !== SUBMISSION_RETURN_PUBLISHED) {
				$this->columns[] = 'ps.date_published';
				$q->leftJoin('published_submissions as ps','ps.submission_id','=','s.submission_id')
					->groupBy('ps.date_published');
			}
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

		// overdue submisions
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

		// assigned to
		$isAssignedOnly = !is_null($this->assigneeId) && ($this->assigneeId !== -1);
		if ($isAssignedOnly) {
			$assigneeId = $this->assigneeId;

			// Stage assignments
			$q->leftJoin('stage_assignments as sa', function($table) use ($assigneeId) {
				$table->on('s.submission_id', '=', 'sa.submission_id');
				$table->on('sa.user_id', '=', Capsule::raw((int) $assigneeId));
			});

			// Review assignments
			$q->leftJoin('review_assignments as ra', function($table) use ($assigneeId) {
				$table->on('s.submission_id', '=', 'ra.submission_id');
				$table->on('ra.reviewer_id', '=', Capsule::raw((int) $assigneeId));
				$table->on('ra.declined', '=', Capsule::raw((int) 0));
			});

			$q->where(function($q) {
				$q->whereNotNull('sa.stage_assignment_id');
				$q->orWhereNotNull('ra.review_id');
			});
		} elseif ($this->assigneeId === -1) {
			$sub = Capsule::table('stage_assignments')
						->select(Capsule::raw('count(stage_assignments.stage_assignment_id)'))
						->leftJoin('user_groups','stage_assignments.user_group_id','=','user_groups.user_group_id')
						->where('stage_assignments.submission_id', '=', Capsule::raw('s.submission_id'))
						->whereIn('user_groups.role_id', array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR));

			$q->whereNotNull('s.date_submitted')
				->mergeBindings($sub)
				->where(Capsule::raw('(' . $sub->toSql() . ')'),'=','0');
		}

		// search phrase
		if (!empty($this->searchPhrase)) {
			$words = explode(' ', $this->searchPhrase);
			if (count($words)) {
				$q->leftJoin('submission_settings as ss','s.submission_id','=','ss.submission_id')
					->leftJoin('authors as au','s.submission_id','=','au.submission_id')
					->leftJoin('author_settings as as', 'as.author_id', '=', 'au.author_id');

				foreach ($words as $word) {
					$word = strtolower(addcslashes($word, '%_'));
					$q->where(function($q) use ($word, $isAssignedOnly)  {
						$q->where(function($q) use ($word) {
							$q->where('ss.setting_name', 'title');
							$q->where(Capsule::raw('lower(ss.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word) {
							$q->where('as.setting_name', IDENTITY_SETTING_GIVENNAME);
							$q->where(Capsule::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
						})
						->orWhere(function($q) use ($word, $isAssignedOnly) {
							$q->where('as.setting_name', IDENTITY_SETTING_FAMILYNAME);
							$q->where(Capsule::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
						});
						// Prevent reviewers from matching searches by author name
						if ($isAssignedOnly) {
							$q->whereNull('ra.reviewer_id');
						}
						if (ctype_digit($word)) {
							$q->orWhere('s.submission_id', '=', $word);
						}
					});
				}

			}
		}

		// Category IDs
		if (!empty($this->categoryIds)) {
			$q->leftJoin('submission_categories as sc', 's.submission_id', '=', 'sc.submission_id')
				->whereIn('sc.category_id', $this->categoryIds);
		}

		// Add app-specific query statements
		\HookRegistry::call('Submission::getMany::queryObject', array(&$q, $this));

		if (!empty($this->countOnly)) {
			$q->select(Capsule::raw('count(*) as submission_count'));
		} else {
			$q->distinct('s.*')->select($this->columns);
		}

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
