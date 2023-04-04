<?php
/**
 * @file classes/services/QueryBuilders/PKPUserQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission list Query builder
 */

namespace PKP\Services\QueryBuilders;

use Application;
use AppLocale;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface;
use PKPString;

class PKPUserQueryBuilder implements EntityQueryBuilderInterface {

	/** @var int Context ID */
	protected $contextId = null;

	/** @var string order by column */
	protected $orderColumn = 'u.user_id';

	/** @var string order by direction */
	protected $orderDirection = 'DESC';

	/** @var string enabled or disabled users */
	protected $status = null;

	/** @var array list of role ids */
	protected $roleIds = null;

	/** @var array list of user group ids */
	protected $userGroupIds = null;

	/** @var array list of user ids */
	protected $userIds = [];

	/** @var int Assigned as editor to this category id */
	protected $assignedToCategoryId = null;

	/** @var int Assigned as editor to this section id */
	protected $assignedToSectionId = null;

	/** @var int submission ID */
	protected $assignedToSubmissionId = null;

	/** @var int submission stage ID */
	protected $assignedToSubmissionStageId = null;

	/** @var string get users registered after this date */
	protected $registeredAfter = '';

	/** @var string get users registered before this date */
	protected $registeredBefore = '';

	/** @var array user IDs */
	protected $includeUsers = null;

	/** @var array user IDs */
	protected $excludeUsers = null;

	/** @var string search phrase */
	protected $searchPhrase = null;

	/** @var bool whether to return reviewer activity data */
	protected $getReviewerData = null;

	/** @var int filter by review stage id */
	protected $reviewStageId = null;

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

	/** @var int|null whether to limit the number of results returned */
	protected $limit = null;

	/** @var int whether to offset the number of results returned. Use to return a second page of results. */
	protected $offset = 0;

	/**
	 * Set context submissions filter
	 *
	 * @param int|string $contextId
	 *
	 * @return \APP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByContext($contextId) {
		$this->contextId = $contextId;
		return $this;
	}

	/**
	 * Set result order column and direction
	 *
	 * @param $column string
	 * @param $direction string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function orderBy($column, $direction = 'DESC') {
		$orderColumns = [
			'id' => 'u.user_id',
			IDENTITY_SETTING_GIVENNAME => IDENTITY_SETTING_GIVENNAME,
			IDENTITY_SETTING_FAMILYNAME => IDENTITY_SETTING_FAMILYNAME
		];
		if (!isset($orderColumns[$column])) {
			error_log((string) new DomainException("Invalid sort column: {$column}"));
			$column = 'id';
		}
		$this->orderColumn = $orderColumns[$column];
		$this->orderDirection = $direction;
		return $this;
	}

	/**
	 * Set status filter
	 *
	 * @param $status string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByStatus($status) {
		if ($status && !in_array($status, ['active', 'disabled'])) {
			error_log((string) new DomainException("Invalid status \"{$status}\""));
		}
		$this->status = $status;
		return $this;
	}

	/**
	 * Set roles filter
	 *
	 * @param $roleIds int|array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByRoleIds($roleIds) {
		if (!is_null($roleIds) && !is_array($roleIds)) {
			$roleIds = array($roleIds);
		}
		$this->roleIds = $roleIds;
		return $this;
	}

	/**
	 * Set user groups filter
	 *
	 * @param array $userGroupIds
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByUserGroupIds(array $userGroupIds) {
		$this->userGroupIds = $userGroupIds;
		return $this;
	}

	/**
	 * Set user ID filter
	 *
	 * @param array $userIds
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByUserIds(array $userIds) {
		$this->userIds = $userIds;
		return $this;
	}

	/**
	 * Limit results to users assigned as editors to this category
	 *
	 * @param $categoryId int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function assignedToCategory($categoryId) {
		$this->assignedToCategoryId = $categoryId;
		return $this;
	}

	/**
	 * Limit results to users assigned as editors to this section
	 *
	 * @param $sectionId int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function assignedToSection($sectionId) {
		$this->assignedToSectionId = $sectionId;
		return $this;
	}

	/**
	 * Limit results to users assigned to this submission
	 *
	 * @param $submissionId int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function assignedToSubmission($submissionId, $submissionStage) {
		$this->assignedToSubmissionId = $submissionId;
		if ($submissionStage && $this->assignedToSubmissionId) {
			$this->assignedToSubmissionStageId = $submissionStage;
		}
		return $this;
	}

	/**
	 * Limit results to users who registered after this date
	 *
	 * @param $date string
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function registeredAfter($date) {
		$this->registeredAfter = $date;
		return $this;
	}

	/**
	 * Limit results to users who registered before this date
	 *
	 * @param $date string
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function registeredBefore($date) {
		$this->registeredBefore = $date;
		return $this;
	}

	/**
	 * Include selected users
	 *
	 * This will include a user even if they do not match
	 * the query conditions.
	 *
	 * @param $userIds array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function includeUsers($userIds) {
		$this->includeUsers = $userIds;
		return $this;
	}

	/**
	 * Exclude selected users
	 *
	 * This will exclude a user even if they match all of the
	 * query conditions.
	 *
	 * @param $userIds array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function excludeUsers($userIds) {
		$this->excludeUsers = $userIds;
		return $this;
	}

	/**
	 * Set query search phrase
	 *
	 * @param $phrase string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function searchPhrase($phrase) {
		$this->searchPhrase = $phrase;
		return $this;
	}

	/**
	 * Whether to return reviewer activity data
	 *
	 * @param $enable bool
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function getReviewerData($enable = true) {
		$this->getReviewerData = $enable;
		return $this;
	}

	/**
	 * Limit results to reviewers for a particular stage
	 *
	 * @param $reviewStageId int WORKFLOW_STAGE_ID_*_REVIEW
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByReviewStage($reviewStageId = null) {
		if (!is_null($reviewStageId)) {
			$this->reviewStageId = $reviewStageId;
		}

		return $this;
	}

	/**
	 * Limit results to those who have a minimum reviewer rating
	 *
	 * @param $reviewerRating int
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function filterByAverageCompletion($averageCompletion = null) {
		if (!is_null($averageCompletion)) {
			$this->averageCompletion = $averageCompletion;
		}

		return $this;
	}

	/**
	 * Set query limit
	 *
	 * @param int $count
	 *
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
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
	 * @return \PKP\Services\QueryBuilders\PKPUserQueryBuilder
	 */
	public function offsetBy($offset) {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getCount()
	 */
	public function getCount() {
		$q = $this->getQuery();
		// Reset the orderBy
		$q->orders = [];
		return $q->select('u.user_id')
			->get()
			->count();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getIds()
	 */
	public function getIds() {
		$q = $this->getQuery();
		// Reset the orderBy
		$q->orders = [];
		return $q->select('u.user_id')
			->pluck('u.user_id')
			->toArray();
	}

	/**
	 * Execute query builder
	 *
	 * @return object Query object
	 */
	public function getQuery() {
		$query = Capsule::table('users', 'u')
			->select('u.*')
			// Filters by registration date
			->when(!empty($this->registeredBefore), function (Builder $query) {
				// Include users who registered up to the end of the day
				$query->where('u.date_registered', '<', Carbon::rawParse($this->registeredBefore)->addDay()->toDateString());
			})
			->when(!empty($this->registeredAfter), function (Builder $query) {
				$query->where('u.date_registered', '>=', $this->registeredAfter);
			})
			->when(!empty($this->userIds), function (Builder $query) {
				$query->whereIn('u.user_id', $this->userIds);
			})
			->when($this->excludeUsers !== null, function (Builder $query) {
				$query->whereNotIn('u.user_id', $this->excludeUsers);
			})
			// User enabled/disabled state
			->when(in_array($this->status, ['active', 'disabled']), function (Builder $query) {
				$query->where('u.disabled', '=', $this->status === 'disabled');
			})
			// Adds limit and offset for pagination
			->when($this->limit !== null, function (Builder $query) {
				$query->limit($this->limit);
			})
			->when(!empty($this->offset), function (Builder $query) {
				$query->offset($this->offset);
			});

		$this
			->buildReviewerStatistics($query)
			->buildUserGroupFilter($query)
			->buildSearchFilter($query)
			->buildSubEditorFilter($query)
			->buildSubmissionAssignmentsFilter($query)
			->buildOrderBy($query);

		// Forces the inclusion of determined users (must be the last statement)
		$query->when(!empty($this->includeUsers), function (Builder $query) {
			$query->orWhereIn('u.user_id', $this->includeUsers);
		});

		// Add app-specific query statements
		\HookRegistry::call('User::getMany::queryObject', array(&$query, $this));

		return $query;
	}

	/**
	 * Builds the filters related to submission assignments (submission, stage, user group)
	 */
	protected function buildSubmissionAssignmentsFilter(Builder $query): self
	{
		if ($this->assignedToSubmissionId === null && $this->assignedToSubmissionStageId === null) {
			return $this;
		}
		$query->whereExists(function (Builder $query) {
			$query->from('stage_assignments', 'sa')
				->join('user_group_stage AS ugs', 'sa.user_group_id', '=', 'ugs.user_group_id')
				->whereColumn('sa.user_id', '=', 'u.user_id')
				->when($this->assignedToSubmissionId !== null, function (Builder $query) {
					$query->where('sa.submission_id', '=', $this->assignedToSubmissionId);
				})
				->when($this->assignedToSubmissionStageId, function (Builder $query) {
					$query->where('ugs.stage_id', '=', $this->assignedToSubmissionStageId);
				});
		});
		return $this;
	}

	/**
	 * Builds the filters related to the user group
	 */
	protected function buildUserGroupFilter(Builder $query): self
	{
		if (empty($this->userGroupIds) && $this->roleIds === null && $this->contextId === CONTEXT_ID_ALL && $this->reviewStageId === null) {
			return $this;
		}
		$query->whereExists(function (Builder $query) {
			$query->from('user_user_groups', 'uug')
				->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
				->whereColumn('uug.user_id', '=', 'u.user_id')
				->when(!empty($this->userGroupIds), function (Builder $query) {
					$query->whereIn('uug.user_group_id', $this->userGroupIds);
				})
				->when($this->reviewStageId !== null, function (Builder $query) {
					$query->join('user_group_stage AS ugs', 'ug.user_group_id', '=', 'ugs.user_group_id')
						->where('ugs.stage_id', '=', $this->reviewStageId);
				})
				->when($this->roleIds !== null, function (Builder $query) {
					$query->whereIn('ug.role_id', $this->roleIds);
				})
				->when($this->contextId !== CONTEXT_ID_ALL, function (Builder $query) {
					$query->where('ug.context_id', '=', $this->contextId ?? CONTEXT_ID_NONE);
				});
		});
		return $this;
	}

	/**
	 * Builds the sub-editor filter
	 */
	protected function buildSubEditorFilter(Builder $query): self
	{
		foreach ([ASSOC_TYPE_SECTION => $this->assignedToSectionId, ASSOC_TYPE_CATEGORY => $this->assignedToCategoryId] as $type => $id) {
			$query->when($id !== null, function (Builder $query) use ($id, $type) {
				$query->whereExists(function (Builder $query) use ($id, $type) {
					$query->from('subeditor_submission_group', 'ssg')
						->whereColumn('ssg.user_id', '=', 'u.user_id')
						->where('ssg.assoc_type', '=', $type)
						->where('ssg.assoc_id', '=', $id);
				});
			});
		}
		return $this;
	}

	/**
	 * Builds the reviewer statistics and related filters
	 */
	protected function buildReviewerStatistics(Builder $query): self
	{
		if (empty($this->getReviewerData)) {
			return $this;
		}
		// Compile the statistics into a sub-query
		$query->leftJoinSub(function (Builder $query) {
			$dateDiff = Capsule::connection() instanceof MySqlConnection
				? 'DATEDIFF(ra.date_completed, ra.date_notified)'
				: "DATE_PART('day', ra.date_completed - ra.date_notified)";
			$query->from('review_assignments', 'ra')
				->groupBy('ra.reviewer_id')
				->select('ra.reviewer_id')
				->selectRaw('MAX(ra.date_assigned) AS last_assigned')
				->selectRaw('COUNT(CASE WHEN ra.date_completed IS NULL AND ra.declined = 0 AND ra.cancelled = 0 THEN 1 END) AS incomplete_count')
				->selectRaw('COUNT(CASE WHEN ra.date_completed IS NOT NULL AND ra.declined = 0 THEN 1 END) AS complete_count')
				->selectRaw('SUM(ra.declined) AS declined_count')
				->selectRaw('SUM(ra.cancelled) AS cancelled_count')
				->selectRaw("AVG($dateDiff) AS average_time")
				->selectRaw('AVG(ra.quality) AS reviewer_rating');
		}, 'ra_stats', 'u.user_id', '=', 'ra_stats.reviewer_id')
			// Select all statistics columns
			->addSelect('ra_stats.*')
			// Reviewer rating
			->when($this->reviewerRating !== null, function (Builder $query) {
				$query->where('ra_stats.reviewer_rating', '>=', $this->reviewerRating);
			})
			// Completed reviews
			->when(($reviewsCompleted = $this->reviewsCompleted[0] ?? $this->reviewsCompleted) !== null, function (Builder $query) use ($reviewsCompleted) {
				$query->where('ra_stats.complete_count', '>=', $reviewsCompleted);
			})
			// Minimum active reviews
			->when(($minReviews = $this->reviewsActive[0] ?? $this->reviewsActive) !== null, function (Builder $query) use ($minReviews) {
				$query->where('ra_stats.incomplete_count', '>=', $minReviews);
			})
			// Maximum active reviews
			->when(($maxReviews = $this->reviewsActive[1] ?? null) !== null, function (Builder $query) use ($maxReviews) {
				$query->where('ra_stats.incomplete_count', '<=', $maxReviews);
			})
			// Minimum days since last review assignment
			->when(($minDays = $this->daysSinceLastAssignment[0] ?? $this->daysSinceLastAssignment) !== null, function (Builder $query) use ($minDays) {
				$query->where('ra_stats.last_assigned', '<=', Carbon::now()->subDays($minDays)->toDateString());
			})
			// Maximum days since last review assignment
			->when(($maxDays = $this->daysSinceLastAssignment[1] ?? null) !== null, function (Builder $query) use ($maxDays) {
				$query->where('ra_stats.last_assigned', '>=', Carbon::now()->subDays($maxDays + 1)->toDateString()); // Add one to include upper bound
			})
			// Average days to complete review
			->when(($averageCompletion = $this->averageCompletion[0] ?? $this->averageCompletion) !== null, function (Builder $query) use ($averageCompletion) {
				$query->where('ra_stats.average_time', '<=', $averageCompletion);
			});
		return $this;
	}

	/**
	 * Builds the user search
	 */
	protected function buildSearchFilter(Builder $query): self
	{
		if (!strlen($searchPhrase = trim($this->searchPhrase ?? ''))) {
			return $this;
		}
		$words = array_map(function (string $word) {
			return '%' . addcslashes($word, '%_') . '%';
		}, PKPString::regexp_split('/\s+/', $searchPhrase));
		foreach ($words as $word) {
			$query->where(function (Builder $query) use ($word) {
				$query->whereRaw('LOWER(u.username) LIKE LOWER(?)', [$word])
					->orWhereRaw('LOWER(u.email) LIKE LOWER(?)', [$word])
					->orWhereExists(function (Builder $query) use ($word) {
						// Settings where the search will be performed
						$settings = [IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName', 'affiliation', 'biography', 'orcid'];
						$query->from('user_settings', 'us')
							->whereColumn('us.user_id', '=', 'u.user_id')
							->whereIn('us.setting_name', $settings)
							->whereRaw('LOWER(us.setting_value) LIKE LOWER(?)', [$word]);
					})
					->orWhereExists(function (Builder $query) use ($word) {
						$query->from('user_interests', 'ui')
							->join('controlled_vocab_entry_settings AS cves', 'ui.controlled_vocab_entry_id', '=', 'cves.controlled_vocab_entry_id')
							->whereColumn('ui.user_id', '=', 'u.user_id')
							->whereRaw('LOWER(cves.setting_value) LIKE LOWER(?)', [$word]);
					});
			});
		}
		return $this;
	}

	/**
	 * Handles the order by clause
	 */
	protected function buildOrderBy(Builder $query): self
	{
		$nameSettings = [IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME];
		if (in_array($this->orderColumn, $nameSettings)) {
			$locales = array_unique([AppLocale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()]);
			$sortedSettings = $this->orderColumn === IDENTITY_SETTING_GIVENNAME ? $nameSettings : array_reverse($nameSettings);
			$query->orderBy(
				function (Builder $query) use ($sortedSettings, $locales) {
					$query->fromSub(function (Builder $query) {
						$query->from(null)->selectRaw(0);
					}, 'placeholder');
					$aliasesBySetting = [];
					foreach ($sortedSettings as $i => $setting) {
						$aliases = [];
						foreach ($locales as $j => $locale) {
							$aliases[] = $alias = "us_{$i}_{$j}";
							$query->leftJoin(
								"user_settings AS {$alias}",
								function (JoinClause $join) use ($alias, $setting, $locale) {
									$join
										->on("{$alias}.user_id", '=', 'u.user_id')
										->where("{$alias}.setting_name", '=', $setting)
										->where("{$alias}.locale", '=', $locale);
								}
							);
						}
						$aliasesBySetting[] = $aliases;
					}
					// Build a possibly long CONCAT(COALESCE(given_localeA, given_localeB, [...]), COALESCE(family_localeA, family_localeB, [...])
					$coalescedSettings = array_map(function (array $aliases) {
						return 'COALESCE(' . implode(', ', array_map(function (string $alias) {
							return "{$alias}.setting_value";
						}, $aliases)) . ", '')";
					}, $aliasesBySetting);
					$query->selectRaw('CONCAT(' . implode(', ', $coalescedSettings) . ')');
				},
				$this->orderDirection
			);
			return $this;
		}

		$query->orderBy($this->orderColumn, $this->orderDirection);
		return $this;
	}
}
