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

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
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
			error_log((string) new DomainException("Invalid sort column: ${column}"));
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
		$q = Capsule::table('users', 'u')
			->select('u.*')

			// Handle any role assignment related constraints
			->when(!empty($this->userGroupIds) || $this->roleIds !== null || $this->contextId !== CONTEXT_ID_ALL || $this->reviewStageId !== null, function (Builder $query) {
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
			})

			->when(!empty($this->registeredBefore), function (Builder $query) {
				// Include users who registered up to the end of the day
				$query->where('u.date_registered', '<', Carbon::rawParse($this->registeredBefore)->addDay()->toDateString());
			})

			->when(!empty($this->registeredAfter), function (Builder $query) {
				$query->where('u.date_registered', '>=', $this->registeredAfter);
			})

			->when(!empty($this->includeUsers), function (Builder $query) {
				$query->whereIn('u.user_id', $this->includeUsers);
			})

			->when($this->excludeUsers !== null, function (Builder $query) {
				$query->whereNotIn('u.user_id', $this->excludeUsers);
			})

			// Handle conditions related to submission assignments (submission, stage)
			->when($this->assignedToSubmissionId !== null || $this->assignedToSubmissionStageId !== null, function (Builder $query) {
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
			})

			// User enabled/disabled state
			->when(
				in_array($this->status, ['active', 'disabled']),
				function (Builder $query) {
					$query->where('u.disabled', '=', $this->status === 'disabled');
				},
				function () {
					if ($this->status) {
						error_log((string) new DomainException('Invalid status ' . $this->status));
					}
				}
			)

			->when(true, function (Builder $query) {
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
			})

			->when(strlen($searchPhrase = PKPString::strtolower(trim($this->searchPhrase))), function (Builder $query) use ($searchPhrase) {
				$words = array_map(function (string $word): string {
					return '%' . addcslashes($word, '%_') . '%';
				}, PKPString::regexp_split('/\s+/', $searchPhrase));

				foreach ($words as $word) {
					$query->where(function(Builder $query) use ($word) {
						$query->whereRaw('LOWER(u.username) LIKE LOWER(?)', [$word])
							->orWhereRaw('LOWER(u.email) LIKE LOWER(?)', [$word])
							->orWhereExists(function (Builder $query) use ($word): void {
								$query->from('user_settings', 'us')
									->whereColumn('us.user_id', '=', 'u.user_id')
									->whereIn('us.setting_name', [
										IDENTITY_SETTING_GIVENNAME,
										IDENTITY_SETTING_FAMILYNAME,
										'preferredPublicName',
										'affiliation',
										'biography',
										'orcid'
									])
									->whereRaw('LOWER(us.setting_value) LIKE LOWER(?)', [$word]);
							})
							->orWhereExists(function (Builder $query) use ($word): void {
								$query->from('user_interests', 'ui')
									->join('controlled_vocab_entry_settings AS cves', 'ui.controlled_vocab_entry_id', '=', 'cves.controlled_vocab_entry_id')
									->whereColumn('ui.user_id', '=', 'u.user_id')
									->whereRaw('LOWER(cves.setting_value) LIKE LOWER(?)', [$word]);
							});
					});
				}
			})

			// When reviewer data is desired, fetch statistics and handle review related constraints.
			->when(!empty($this->getReviewerData), function (Builder $query) {
				// Compile the statistics into a sub-query
				$query->leftJoinSub(function (Builder $query): void {
					$dateDiff = Capsule::connection() instanceof MySqlConnection
						? 'DATEDIFF(ra.date_completed, ra.date_notified)'
						: "DATE_PART('day', ra.date_completed - ra.date_notified)";
					$query->from('review_assignments', 'ra')
						->groupBy('ra.reviewer_id')
						->select('ra.reviewer_id')
						->selectRaw('MAX(ra.date_assigned) AS last_assigned')
						->selectRaw('COUNT(CASE WHEN ra.date_completed IS NULL AND ra.declined = 0 THEN 1 END) AS incomplete_count')
						->selectRaw('COUNT(CASE WHEN ra.date_completed IS NOT NULL AND ra.declined = 0 THEN 1 END) AS complete_count')
						->selectRaw('SUM(ra.declined) AS declined_count')
						->selectRaw('SUM(ra.cancelled) AS cancelled_count')
						->selectRaw("AVG($dateDiff) AS average_time")
						->selectRaw('AVG(ra.quality) AS reviewer_rating');
				}, 'ra_stats', 'u.user_id', '=', 'ra_stats.reviewer_id')

					// Select all statistics columns
					->addSelect('ra_stats.*')

					// Reviewer rating
					->when(!empty($this->reviewerRating), function (Builder $query) {
						$query->where('ra_stats.reviewer_rating', '>=', $this->reviewerRating);
					})

					// Completed reviews
					->when(!empty($this->reviewsCompleted), function (Builder $query) {
						$reviewsCompleted = is_array($this->reviewsCompleted) ? $this->reviewsCompleted : [$this->reviewsCompleted];
						if (count($reviewsCompleted) > 1) {
							$query->whereBetween('ra_stats.complete_count', $reviewsCompleted);
						} else {
							$query->where('ra_stats.complete_count', '>=', reset($reviewsCompleted));
						}
					})

					// Active reviews
					->when(!empty($this->reviewsActive), function (Builder $query) {
						$reviewsActive = is_array($this->reviewsActive) ? $this->reviewsActive : [$this->reviewsActive];
						if (count($reviewsActive) > 1) {
							$query->whereBetween('ra_stats.incomplete_count', $reviewsActive);
						} else {
							$query->where('ra_stats.incomplete_count', '>=', reset($reviewsActive));
						}
					})

					// Days since last review assignment
					->when(!empty($this->daysSinceLastAssignment), function (Builder $query) {
						$daysSinceLastAssignment = is_array($this->daysSinceLastAssignment) ? $this->daysSinceLastAssignment : [$this->daysSinceLastAssignment];
						$dbTimeMin = (string) Carbon::now()->subDays((int) reset($daysSinceLastAssignment))->toDateString();
						$query->where('ra_stats.last_assigned', '<=', $dbTimeMin);
						if (count($daysSinceLastAssignment) > 1) {
							// Subtract an extra day so that our outer bound rounds "up". This accounts for the UI rounding "down" in the string "X days ago".
							$dbTimeMax = (string) Carbon::now()->subDays((int) end($daysSinceLastAssignment) + 1)->toDateString();
							$query->where('ra_stats.last_assigned', '>=', $dbTimeMax);
						}
					})

					// Average days to complete review
					->when(!empty($this->averageCompletion), function (Builder $query) {
						$query->where('ra_stats.average_time', '<=', $this->averageCompletion);
					});
			})

			// Limit and offset results for pagination
			->when($this->limit !== null, function (Builder $query) {
				$query->limit($this->limit);
			})

			->when(!empty($this->offset), function (Builder $query) {
				$query->offset($this->offset);
			})

			// Handle custom sorting
			->when(
				in_array($this->orderColumn, [IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME]),
				function (Builder $query) {
					$query->orderBy(function (Builder $query) {
						$locale = \AppLocale::getLocale();
						// The users register for the site, thus the site primary locale should be the default locale
						$fallbackLocale = \Application::get()->getRequest()->getSite()->getPrimaryLocale();

						$query->from('user_settings', 'us')
							->whereColumn('us.user_id', '=', 'u.user_id')
							->where('us.setting_name', '=', $this->orderColumn)
							->whereIn('us.locale', array_unique([$locale, $fallbackLocale]))
							->orderByRaw("COALESCE(us.setting_value, '') = ''")
							->orderByRaw('us.locale <> ?', [$locale])
							->limit(1)
							->select('us.setting_value');
					}, $this->orderDirection);
				},
				function (Builder $query) {
					$query->orderBy($this->orderColumn, $this->orderDirection);
				}
			);

		// Add app-specific query statements
		\HookRegistry::call('User::getMany::queryObject', array(&$q, $this));

		return $q;
	}
}
