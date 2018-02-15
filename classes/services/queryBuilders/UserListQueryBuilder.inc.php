<?php
/**
 * @file classes/services/QueryBuilders/UserListQueryBuilder.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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

	/** @var bool whether to return reviewer activity data */
	protected $getReviewerData = null;

	/** @var int filter by minimum reviewer rating */
	protected $reviewerRating = null;

	/** @var int|array filter by reviews completed by user */
	protected $reviewsCompleted = null;

	/** @var int|array filter by active review assignments for user */
	protected $reviewsActive = null;

	/** @var int|array filter by days since last review assignment */
	protected $daysSinceLastAssignment = null;

	/** @var int|array filter by average days to complete a review */
	protected $averageCompletion = null;

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
	 * @param $column string
	 * @param $direction string
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
	 * @param $status string
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
	 * @param $roleIds int|array
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
	 * @param $submissionId int
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
	 * @param $sectionId int
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
	 * @param $phrase string
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
	 * @param $enable bool
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function countOnly($enable = true) {
		$this->countOnly = $enable;
		return $this;
	}

	/**
	 * Whether to return reviewer activity data
	 *
	 * @param $enable bool
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function getReviewerData($enable = true) {
		$this->getReviewerData = $enable;
		return $this;
	}

	/**
	 * Limit results to those who have a minimum reviewer rating
	 *
	 * @param $reviewerRating int
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByReviewerRating($reviewerRating = null) {
		if (!is_null($reviewerRating)) {
			$this->reviewerRating = $reviewerRating;
		}

		return $this;
	}

	/**
	 * Limit results to those who have completed at least this many reviews
	 *
	 * @param $reviewsCompleted int|array
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByReviewsCompleted($reviewsCompleted = null) {
		if (!is_null($reviewsCompleted)) {
			$this->reviewsCompleted = $reviewsCompleted;
		}

		return $this;
	}

	/**
	 * Limit results to those who have at least this many active review assignments
	 *
	 * @param $reviewsActive int|array
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByReviewsActive($reviewsActive = null) {
		if (!is_null($reviewsActive)) {
			$this->reviewsActive = $reviewsActive;
		}

		return $this;
	}

	/**
	 * Limit results to those who's last review assignment was at least this many
	 * days ago.
	 *
	 * @param $daysSinceLastAssignment int|array
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByDaysSinceLastAssignment($daysSinceLastAssignment = null) {
		if (!is_null($daysSinceLastAssignment)) {
			$this->daysSinceLastAssignment = $daysSinceLastAssignment;
		}

		return $this;
	}

	/**
	 * Limit results to those who complete a review on average less than this many
	 * days after their assignment.
	 *
	 * @param $averageCompletion int|array
	 *
	 * @return \PKP\Services\QueryBuilders\UserListQueryBuilder
	 */
	public function filterByAverageCompletion($averageCompletion = null) {
		if (!is_null($averageCompletion)) {
			$this->averageCompletion = $averageCompletion;
		}

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
				$q->leftJoin('user_settings as us', 'u.user_id', '=', 'us.user_id');
				$q->leftJoin('user_interests as ui', 'u.user_id', '=', 'ui.user_id');
				$q->leftJoin('controlled_vocab_entry_settings as cves', 'ui.controlled_vocab_entry_id', '=', 'cves.controlled_vocab_entry_id');
				foreach ($words as $word) {
					$q->where(function($q) use ($word) {
						$q->where('u.username', 'LIKE', "%{$word}%")
							->orWhere('u.salutation', 'LIKE', "%{$word}%")
							->orWhere('u.first_name', 'LIKE', "%{$word}%")
							->orWhere('u.middle_name', 'LIKE', "%{$word}%")
							->orWhere('u.last_name', 'LIKE', "%{$word}%")
							->orWhere('u.suffix', 'LIKE', "%{$word}%")
							->orWhere('u.initials', 'LIKE', "%{$word}%")
							->orWhere('u.email', 'LIKE', "%{$word}%")
							->orWhere(function($q) use ($word) {
								$q->where('us.setting_name', 'affiliation');
								$q->where('us.setting_value', 'LIKE', "%{$word}%");
							})
							->orWhere(function($q) use ($word) {
								$q->where('us.setting_name', 'biography');
								$q->where('us.setting_value', 'LIKE', "%{$word}%");
							})
							->orWhere(function($q) use ($word) {
								$q->where('us.setting_name', 'orcid');
								$q->where('us.setting_value', 'LIKE', "%{$word}%");
							})
							->orWhere('cves.setting_value', 'LIKE', "%{$word}%");
					});
				}
			}
		}

		// reviewer data
		if (!empty($this->getReviewerData)) {
			$q->leftJoin('review_assignments as ra', 'u.user_id', '=', 'ra.reviewer_id');
			$this->columns[] = Capsule::raw('MAX(ra.date_assigned) as last_assigned');
			$this->columns[] = Capsule::raw('(SELECT SUM(CASE WHEN ra.date_completed IS NULL THEN 1 ELSE 0 END) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as incomplete_count');
			$this->columns[] = Capsule::raw('(SELECT SUM(CASE WHEN ra.date_completed IS NOT NULL THEN 1 ELSE 0 END) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as complete_count');
			switch (\Config::getVar('database', 'driver')) {
				case 'mysql':
				case 'mysqli':
					$dateDiffClause = 'DATEDIFF(ra.date_completed, ra.date_notified)';
					break;
				default:
					$dateDiffClause = 'DATE_PART(\'day\', ra.date_completed - ra.date_notified)';
			}
			$this->columns[] = Capsule::raw('AVG(' . $dateDiffClause . ') as average_time');
			$this->columns[] = Capsule::raw('(SELECT AVG(ra.quality) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.quality IS NOT NULL) as reviewer_rating');

			// reviewer rating
			if (!empty($this->reviewerRating)) {
				$q->havingRaw('(SELECT AVG(ra.quality) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.quality IS NOT NULL) >= ' . (int) $this->reviewerRating);
			}

			// completed reviews
			if (!empty($this->reviewsCompleted)) {
				$doneMin = is_array($this->reviewsCompleted) ? $this->reviewsCompleted[0] : $this->reviewsCompleted;
				$subqueryStatement = '(SELECT SUM(CASE WHEN ra.date_completed IS NOT NULL THEN 1 ELSE 0 END) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id)';
				$q->having(Capsule::raw($subqueryStatement), '>=', $doneMin);
				if (is_array($this->reviewsCompleted) && !empty($this->reviewsCompleted[1])) {
					$q->having(Capsule::raw($subqueryStatement), '<=', $this->reviewsCompleted[1]);
				}
			}

			// active reviews
			if (!empty($this->reviewsActive)) {
				$activeMin = is_array($this->reviewsActive) ? $this->reviewsActive[0] : $this->reviewsActive;
				$subqueryStatement = '(SELECT SUM(CASE WHEN ra.date_completed IS NULL THEN 1 ELSE 0 END) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id)';
				$q->having(Capsule::raw($subqueryStatement), '>=', $activeMin);
				if (is_array($this->reviewsActive) && !empty($this->reviewsActive[1])) {
					$q->having(Capsule::raw($subqueryStatement), '<=', $this->reviewsActive[1]);
				}
			}

			// days since last review assignment
			if (!empty($this->daysSinceLastAssignment)) {
				$daysSinceMin = is_array($this->daysSinceLastAssignment) ? $this->daysSinceLastAssignment[0] : $this->daysSinceLastAssignment;
				$userDao = \DAORegistry::getDAO('UserDAO');
				$dbTimeMin = $userDao->dateTimeToDB(time() - ((int) $daysSinceMin * 86400));
				$q->havingRaw('MAX(ra.date_assigned) <= ' . $dbTimeMin);
				if (is_array($this->daysSinceLastAssignment) && !empty($this->daysSinceLastAssignment[1])) {
					$daysSinceMax = $this->daysSinceLastAssignment[1];
					// Subtract an extra day so that our outer bound rounds "up". This accounts
					// for the UI rounding "down" in the string "X days ago".
					$dbTimeMax = $userDao->dateTimeToDB(time() - ((int) $daysSinceMax * 86400) - 84600);
					$q->havingRaw('MAX(ra.date_assigned) >= ' . $dbTimeMax);
				}
			}

			// average days to complete review
			if (!empty($this->averageCompletion)) {
				$q->havingRaw('AVG(' . $dateDiffClause . ') <= ' . (int) $this->averageCompletion);
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
