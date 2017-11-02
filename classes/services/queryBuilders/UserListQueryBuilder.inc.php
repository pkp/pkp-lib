<?php
/**
 * @file classes/services/QueryBuilders/UserListQueryBuilder.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserListQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission list Query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class UserListQueryBuilder extends BaseQueryBuilder {

	/** @var int Context ID */
	protected $contextId = null;

	/** @var array list of columns for query */
	protected $columns = array();

	/** @var string order by column */
	protected $orderColumn = 'u.user_id';

	/** @var string order by direction */
	protected $orderDirection = 'DESC';

	/** @var string enabled or disabled users */
	protected $status = null;

	/** @var array list of role ids */
	protected $roleIds = null;

	/** @var int submission ID */
	protected $assignedToSubmissionId = null;

	/** @var int submission stage ID */
	protected $assignedToSubmissionStageId = null;

	/** @var int section ID */
	protected $assignedToSectionId = null;

	/** @var string search phrase */
	protected $searchPhrase = null;

	/** @var bool whether to return only a count of results */
	protected $countOnly = null;

	/**
	 * Constructor
	 *
	 * @param $contextId int context ID
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
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function orderBy($column, $direction = 'DESC') {
		if ($column === 'givenName') {
			$this->orderColumn = 'u.first_name';
		} elseif ($column === 'familyName') {
			$this->orderColumn = 'u.last_name';
		} else {
			$this->orderColumn = 'u.user_id';
		}
		$this->orderDirection = $direction;
		return $this;
	}

	/**
	 * Set status filter
	 *
	 * @param string $status
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * Set roles filter
	 *
	 * @param int|array $roleIds
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByRoleIds($roleIds) {
		if (!is_null($roleIds) && !is_array($roleIds)) {
			$roleIds = array($roleIds);
		}
		$this->roleIds = $roleIds;
		return $this;
	}

	/**
	 * Limit results to users assigned to this submission
	 *
	 * @param int $submissionId
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function assignedToSubmission($submissionId, $submissionStage) {
		$this->assignedToSubmissionId = $submissionId;
		if ($submissionStage && $this->assignedToSubmissionId) {
			$this->assignedToSubmissionStageId = $submissionStage;
		}
		return $this;
	}

	/**
	 * Limit results to users assigned as editors to this section
	 *
	 * @param int $sectionId
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function assignedToSection($sectionId) {
		$this->assignedToSectionId = $sectionId;
		return $this;
	}

	/**
	 * Set query search phrase
	 *
	 * @param string $phrase
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
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
		$this->columns[] = 'u.*';
		$q = Capsule::table('users as u')
					->leftJoin('user_user_groups as uug', 'uug.user_id', '=', 'u.user_id')
					->leftJoin('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
					->where('ug.context_id','=', $this->contextId)
					->orderBy($this->orderColumn, $this->orderDirection)
					->groupBy('u.user_id');

		// roles
		if (!is_null($this->roleIds)) {
			$q->whereIn('ug.role_id', $this->roleIds);
		}

		// status
		if (!is_null($this->status)) {
			if ($this->status === 'disabled') {
				$q->where('u.disabled', '=', 1);
			} elseif ($this->status === 'active') {
				$q->where('u.disabled', '=', 0);
			}
		}

		// assigned to submission
		if (!is_null($this->assignedToSubmissionId)) {
			$submissionId = $this->assignedToSubmissionId;

			$q->leftJoin('stage_assignments as sa', function($table) use ($submissionId) {
				$table->on('u.user_id', '=', 'sa.user_id');
				$table->on('sa.submission_id', '=', Capsule::raw((int) $submissionId));
			});

			$q->whereNotNull('sa.stage_assignment_id');

			if (!is_null($this->assignedToSubmissionStageId)) {
				$stageId = $this->assignedToSubmissionStageId;

				$q->leftJoin('user_group_stage as ugs', 'sa.user_group_id', '=', 'ugs.user_group_id');
				$q->where('ugs.stage_id', '=', Capsule::raw((int) $stageId));
			}
		}

		// assigned to section
		if (!is_null($this->assignedToSectionId)) {
			$sectionId = $this->assignedToSectionId;

			$q->leftJoin('section_editors as se', function($table) use ($sectionId) {
				$table->on('u.user_id', '=', 'se.user_id');
				$table->on('se.section_id', '=', Capsule::raw((int) $sectionId));
			});

			$q->whereNotNull('se.section_id');
		}

		// search phrase
		if (!empty($this->searchPhrase)) {
			$words = explode(' ', $this->searchPhrase);
			if (count($words)) {
				foreach ($words as $word) {
					$q->where('u.username', 'LIKE', "%{$word}%")
						->orWhere('u.salutation', 'LIKE', "%{$word}%")
						->orWhere('u.first_name', 'LIKE', "%{$word}%")
						->orWhere('u.middle_name', 'LIKE', "%{$word}%")
						->orWhere('u.last_name', 'LIKE', "%{$word}%")
						->orWhere('u.suffix', 'LIKE', "%{$word}%")
						->orWhere('u.initials', 'LIKE', "%{$word}%")
						->orWhere('u.email', 'LIKE', "%{$word}%");
				}
			}
		}

		// Add app-specific query statements
		\HookRegistry::call('User::getUsers::queryObject', array(&$q, $this));

		if (!empty($this->countOnly)) {
			$q->select(Capsule::raw('count(*) as user_count'));
		} else {
			$q->select($this->columns);
		}

		return $q;
	}
}
