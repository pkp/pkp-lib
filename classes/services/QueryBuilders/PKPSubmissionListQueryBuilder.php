<?php

/**
 * @file classes/services/QueryBuilders/PKPSubmissionListQueryBuilder.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionListQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission list Query builder
 */

namespace App\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

abstract class PKPSubmissionListQueryBuilder extends BaseQueryBuilder {

	/** @var int Context ID */
	protected $contextId = null;

	/** @var array list of columns for query */
	protected $columns = array();

	/** @var string order by column */
	protected $orderColumn = 's.date_submitted';

	/** @var string order by direction */
	protected $orderDirection = 'DESC';

	/** @var array list of statuses */
	protected $statuses = null;

	/** @var int user ID */
	protected $assigneeId = null;

	/** @var string search phrase */
	protected $searchPhrase = null;

	/** @var bool whether to return only a count of results */
	protected $countOnly = null;

	/**
	 * Constructor
	 *
	 * @param int $contextId context ID
	 */
	public function __construct($contextId) {
		parent::__construct();
		$this->contextId = $contextId;
	}

	/**
	 * Set result order column and direction
	 *
	 * @param string $column
	 * @param string $direction
	 *
	 * @return \App\Services\QueryBuilders\SubmissionListQueryBuilder
	 */
	public function orderBy($column, $direction = 'DESC') {
		if ($column === 'lastModified') {
			$column = 'last_modified';
		} else {
			$column = 'date_submitted';
		}
		$this->orderColumn = "s.{$column}";
		$this->orderDirection = $direction;
		return $this;
	}

	/**
	 * Set statuses filter
	 *
	 * @param int|array $statuses
	 *
	 * @return \App\Services\QueryBuilders\SubmissionListQueryBuilder
	 */
	public function filterByStatus($statuses) {
		if (!is_null($statuses) && !is_array($statuses)) {
			$statuses = array($statuses);
		}
		$this->statuses = $statuses;
		return $this;
	}

	/**
	 * Limit results to a specific user's submissions
	 *
	 * @param int $assigneeId
	 *
	 * @return \App\Services\QueryBuilders\SubmissionListQueryBuilder
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
	 * @return \App\Services\QueryBuilders\SubmissionListQueryBuilder
	 */
	public function searchPhrase($phrase) {
		$this->searchPhrase = $phrase;
		return $this;
	}

	/**
	 * Whether to return only a count of results
	 *
	 * @param bool $enable
	 *
	 * @return \App\Services\QueryBuilders\SubmissionListQueryBuilder
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
					->where('s.context_id','=', $this->contextId)
					->orderBy($this->orderColumn, $this->orderDirection)
					->groupBy('s.submission_id');

		// statuses
		if (!is_null($this->statuses)) {
			if (in_array(STATUS_PUBLISHED, $this->statuses)) {
				$this->columns[] = 'ps.date_published';
				$q->leftJoin('published_submissions as ps','ps.submission_id','=','s.submission_id')
					->groupBy('ps.date_published');
			}
			$q->whereIn('s.status', $this->statuses);
		}

		// assigned to
		if (!is_null($this->assigneeId) && ($this->assigneeId !== -1)) {
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
					->leftJoin('authors as au','s.submission_id','=','au.submission_id');

				foreach ($words as $word) {
					$q->where(function($q) use ($word)  {
						$q->where(function($q) use ($word) {
							$q->where('ss.setting_name', 'title');
							$q->where('ss.setting_value', 'LIKE', "%{$word}%");
						});
						$q->orWhere(function($q) use ($word) {
							$q->where('au.first_name', 'LIKE', "%{$word}%");
							$q->orWhere('au.middle_name', 'LIKE', "%{$word}%");
							$q->orWhere('au.last_name', 'LIKE', "%{$word}%");
						});
					});
				}

			}
		}

		// Add app-specific query statements
		\HookRegistry::call('Submission::listQueryBuilder::get', array(&$q, $this));

		if (!empty($this->countOnly)) {
			$q->select(Capsule::raw('count(*) as submission_count'));
		} else {
			$q->select($this->columns);
		}

		return $q;
	}
}
