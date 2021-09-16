<?php
/**
 * @file classes/user/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of users
 */

namespace PKP\user;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\db\DAORegistry;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    public const ORDERBY_ID = 'id';
    public const ORDERBY_GIVENNAME = 'givenName';
    public const ORDERBY_FAMILYNAME = 'famlyName';

    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_ALL = null;

    /** @var DAO */
    public $dao;

    public string $orderBy = self::ORDERBY_ID;
    public string $orderDirection = 'ASC';
    public ?array $orderLocales = null;
    public ?array $userGroupIds = null;
    public ?array $roleIds = null;
    public ?array $userIds = null;
    public ?array $excludeUserIds = null;
    public ?array $workflowStageIds = null;
    public ?array $contextIds = null;
    public ?string $registeredBefore = null;
    public ?string $registeredAfter = null;
    public ?string $status = self::STATUS_ACTIVE;
    public bool $includeReviewerData = false;
    public ?array $assignedSectionIds = null;
    public ?array $assignedCategoryIds = null;
    public ?array $settings = null;
    public ?string $searchPhrase = null;
    public ?array $excludeSubmissionStage = null;
    public ?array $assignedTo = null;
    public ?int $reviewerRating = null;
    public ?int $reviewsCompleted = null;

    /** @var int|array|null */
    public $daysSinceLastAssignment = null;

    public ?int $averageCompletion = null;
    public ?int $reviewsActive = null;
    public ?int $count = null;
    public ?int $offset = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Limit results to users in these user groups
     */
    public function filterByUserGroupIds(?array $userGroupIds): self
    {
        $this->userGroupIds = $userGroupIds;
        return $this;
    }

    public function filterByUserIds(?array $userIds): self
    {
        $this->userIds = $userIds;
        return $this;
    }

    /**
     * Limit results to those who have a minimum reviewer rating
     */
    public function filterByReviewerRating(?int $reviewerRating): self
    {
        $this->includeReviewerData(true);
        $this->reviewerRating = $reviewerRating;
        return $this;
    }

    /**
     * Limit results to those who have completed at least this many reviews
     */
    public function filterByReviewsCompleted(?int $reviewsCompleted)
    {
        $this->includeReviewerData(true);
        $this->reviewsCompleted = $reviewsCompleted;
        return $this;
    }

    /**
     * Limit results to those who's last review assignment was at least this many
     * days ago.
     *
     * @param $daysSinceLastAssignment int|array(min,max)|null
     */
    public function filterByDaysSinceLastAssignment($daysSinceLastAssignment): self
    {
        $this->includeReviewerData(true);
        $this->daysSinceLastAssignment = $daysSinceLastAssignment;
        return $this;
    }

    /**
     * Limit results to those who complete a review on average less than this many
     * days after their assignment.
     */
    public function filterByAverageCompletion(?int $averageCompletion): self
    {
        $this->includeReviewerData(true);
        $this->averageCompletion = $averageCompletion;
        return $this;
    }

    /**
     * Limit results to those who have at least this many active review assignments
     *
     * @param null|mixed $reviewsActive
     */
    public function filterByReviewsActive($reviewsActive = null): self
    {
        $this->includeReviewerData(true);
        $this->reviewsActive = $reviewsActive;
        return $this;
    }

    /**
     * Exclude results with the specified user IDs.
     */
    public function excludeUserIds(?array $excludeUserIds): self
    {
        $this->excludeUserIds = $excludeUserIds;
        return $this;
    }

    /**
     * Limit results to users enrolled in these roles
     */
    public function filterByRoleIds(?array $roleIds): self
    {
        $this->roleIds = $roleIds;
        return $this;
    }

    /**
     * Limit results to users enrolled in these roles
     */
    public function filterByWorkflowStageIds(?array $workflowStageIds): self
    {
        $this->workflowStageIds = $workflowStageIds;
        return $this;
    }


    /**
     * Limit results to users with user groups in these context IDs
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Limit results to users registered before a specified date.
     */
    public function filterRegisteredBefore(?string $registeredBefore): self
    {
        $this->registeredBefore = $registeredBefore;
        return $this;
    }

    /**
     * Limit results to users registered after a specified date.
     */
    public function filterRegisteredAfter(?string $registeredAfter): self
    {
        $this->registeredAfter = $registeredAfter;
        return $this;
    }

    public function includeReviewerData(bool $includeReviewerData = true): self
    {
        $this->includeReviewerData = $includeReviewerData;
        return $this;
    }

    /**
     * Retrieve a set of users not assigned to a given submission stage as a user group.
     * (Replaces UserStageAssignmentDAO::getUsersNotAssignedToStageInUserGroup)
     */
    public function filterExcludeSubmissionStage(int $submissionId, int $stageId, int $userGroupId): self
    {
        $this->excludeSubmissionStage = [
            'submission_id' => $submissionId,
            'stage_id' => $stageId,
            'user_group_id' => $userGroupId,
        ];
        return $this;
    }

    /**
     * Retrieve assigned users by submission and stage IDs.
     * (Replaces UserStageAssignmentDAO::getUsersBySubmissionAndStageId)
     */
    public function assignedTo(?int $submissionId = null, ?int $stageId = null, ?int $userGroupId = null): self
    {
        if ($submissionId === null) {
            // Clear the condition.
            $this->assignedTo = null;
            if ($stageId !== null || $userGroupId !== null) {
                throw new \InvalidArgumentException('If a stage or user group ID is specified, a submission ID must be specified as well.');
            }
        } else {
            $this->assignedTo = [
                'submissionId' => $submissionId,
                'stageId' => $stageId,
                'userGroupId' => $userGroupId,
            ];
        }
        return $this;
    }

    /**
     * Filter by active / disabled status.
     *
     * @param $status STATUS_ACTIVE, STATUS_DISABLED, or STATUS_ALL.
     */
    public function filterByStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Filter by assigned subeditor section IDs
     */
    public function assignedToSectionIds(?array $sectionIds): self
    {
        $this->assignedSectionIds = $sectionIds;
        return $this;
    }

    /**
     * Filter by assigned subeditor section IDs
     */
    public function assignedToCategoryIds(?array $categoryIds): self
    {
        $this->assignedCategoryIds = $categoryIds;
        return $this;
    }

    public function filterBySettings(?array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    /**
     * Limit results to users matching this search query
     */
    public function searchPhrase(?string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Order the results
     *
     * @param string $sorter One of the self::ORDERBY_ constants
     * @param string $direction One of the self::ORDER_DIR_ constants
     * @param ?array locales Optional list of locale precedences when ordering by localized columns
     */
    public function orderBy(string $sorter, string $direction = self::ORDER_DIR_DESC, ?array $locales = null): Collector
    {
        $this->orderBy = $sorter;
        $this->orderDirection = $direction;
        $this->orderLocales = $locales;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table('users AS u')
            ->select('u.*')

            // Handle any role assignment related constraints
            ->when($this->userGroupIds !== null || $this->roleIds !== null || $this->contextIds !== null || $this->workflowStageIds !== null, function ($query) {
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('uug.user_id')
                        ->from('user_user_groups AS uug')
                        ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                        ->when($this->userGroupIds !== null, function ($query) {
                            return $query->whereIn('uug.user_group_id', $this->userGroupIds);
                        })
                        ->when($this->workflowStageIds !== null, function ($query) {
                            $query->join('user_group_stage AS ugs', 'ug.user_group_id', '=', 'ugs.user_group_id')
                                ->whereIn('ugs.stage_id', $this->workflowStageIds);
                        })
                        ->when($this->roleIds !== null, function ($query) {
                            return $query->whereIn('ug.role_id', $this->roleIds);
                        })
                        ->when($this->contextIds !== null, function ($query) {
                            return $query->whereIn('ug.context_id', $this->contextIds);
                        });
                });
            })

            ->when($this->registeredBefore !== null, function ($query) {
                // Include useres who registered up to the end of the day
                $dateTime = new \DateTime($this->registeredBefore);
                $dateTime->add(new \DateInterval('P1D'));
                $query->where('u.date_registered', '<', $dateTime->format('Y-m-d'));
            })

            ->when($this->registeredAfter !== null, function ($query) {
                $query->where('u.date_registered', '>=', $this->registeredAfter);
            })

            ->when($this->userIds !== null, function ($query) {
                $query->whereIn('u.user_id', $this->userIds);
            })

            ->when($this->excludeUserIds !== null, function ($query) {
                $query->whereNotIn('u.user_id', $this->excludeUserIds);
            })

            ->when($this->settings !== null, function ($query) {
                foreach ($this->settings as $settingName => $value) {
                    $query->whereIn('u.user_id', function ($query) use ($settingName, $value) {
                        return $query->select('user_id')
                            ->from('user_settings')
                            ->where('setting_name', '=', $settingName)
                            ->where('setting_value', '=', $value);
                    });
                }
            })

            ->when($this->excludeSubmissionStage !== null, function ($query) {
                // Left join on a match for the excluded submission stage list, then assert that it's null
                $query->join('user_user_groups AS uug_exclude', 'u.user_id', '=', 'uug_exclude.user_id')
                    ->join('user_group_stage AS ugs_exclude', function ($join) {
                        return $join->on('uug_exclude.user_group_id', '=', 'ugs_exclude.user_group_id')
                            ->where('ugs_exclude.stage_id', '=', $this->excludeSubmissionStage['stage_id']);
                    })
                    ->leftJoin('stage_assignments AS sa_exclude', function ($join) {
                        return $join->on('sa_exclude.user_id', '=', 'uug_exclude.user_id')
                            ->on('sa_exclude.user_group_id', '=', 'uug_exclude.user_group_id')
                            ->where('sa_exclude.submission_id', '=', $this->excludeSubmissionStage['submission_id']);
                    })
                    ->where('uug_exclude.user_group_id', '=', $this->excludeSubmissionStage['user_group_id'])
                    ->whereNull('sa_exclude.user_group_id');
            })

            // Handle conditions related to submission assignments (submission, stage, user group)
            ->when($this->assignedTo !== null, function ($query) {
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('sa.user_id')
                        ->from('stage_assignments AS sa')
                        ->join('user_group_stage AS ugs', 'sa.user_group_id', '=', 'ugs.user_group_id')
                        ->when(isset($this->assignedTo['submissionId']), function ($query) {
                            return $query->where('sa.submission_id', '=', $this->assignedTo['submissionId']);
                        })
                        ->when(isset($this->assignedTo['stageId']), function ($query) {
                            return $query->where('ugs.stage_id', '=', $this->assignedTo['stageId']);
                        })
                        ->when(isset($this->assignedTo['userGroupId']), function ($query) {
                            return $query->where('sa.user_group_id', '=', $this->assignedTo['userGroupId']);
                        });
                });
            })

            // User enabled/disabled state
            ->when($this->status !== self::STATUS_ALL, function ($query) {
                switch ($this->status) {
                    case self::STATUS_ACTIVE: $query->where('u.disabled', '=', 0); break;
                    case self::STATUS_DISABLED: $query->where('u.disabled', '=', 1); break;
                    default: throw new \InvalidArgumentException('Invalid status!');
                }
            })

            ->when($this->assignedSectionIds !== null, function ($query) {
                $query->whereIn('u.user_id', function ($query) {
                    return $query->select('user_id')
                        ->from('subeditor_submission_group')
                        ->where('assoc_type', '=', ASSOC_TYPE_SECTION)
                        ->whereIn('assoc_id', $this->assignedSectionIds);
                });
            })

            ->when($this->assignedCategoryIds !== null, function ($query) {
                $query->whereIn('u.user_id', function ($query) {
                    return $query->select('user_id')
                        ->from('subeditor_submission_group')
                        ->where('assoc_type', '=', ASSOC_TYPE_CATEGORY)
                        ->whereIn('assoc_id', $this->assignedCategoryIds);
                });
            })

            ->when($this->searchPhrase !== null, function ($query) {
                $words = explode(' ', $this->searchPhrase);
                foreach ($words as $word) {
                    $query->whereIn('u.user_id', function ($query) use ($word) {
                        $likePattern = DB::raw("CONCAT('%', LOWER(?), '%')");
                        return $query->select('u.user_id')
                            ->from('users AS u')
                            ->join('user_settings AS us', function ($join) {
                                $join->on('u.user_id', '=', 'us.user_id')
                                    ->whereIn('us.setting_name', [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName', 'affiliation', 'biography', 'orcid']);
                            })
                            ->where(DB::raw('LOWER(us.setting_value)'), 'LIKE', $likePattern)->addBinding($word)
                            ->orWhere(DB::raw('LOWER(email)'), 'LIKE', $likePattern)->addBinding($word)
                            ->orWhere(DB::raw('LOWER(username)'), 'LIKE', $likePattern)->addBinding($word);
                    });
                }
            })

            // When reviewer data is desired, fetch statistics and handle review related constraints.
            ->when($this->includeReviewerData, function ($query) {
                // Latest assigned review
                $query->leftJoin('review_assignments AS ra_latest', 'u.user_id', '=', 'ra_latest.reviewer_id')
                    ->leftJoin('review_assignments AS ra_latest_nonexistent', function ($join) {
                        $join->on('u.user_id', '=', 'ra_latest_nonexistent.reviewer_id')
                            ->on('ra_latest.review_id', '<', 'ra_latest_nonexistent.review_id');
                    })
                    ->whereNull('ra_latest_nonexistent.review_id')
                    ->addSelect('ra_latest.date_assigned AS last_assigned')
                    ->when($this->daysSinceLastAssignment !== null, function ($query) {
                        $daysSinceMin = (int) (is_array($this->daysSinceLastAssignment) ? $this->daysSinceLastAssignment[0] : $this->daysSinceLastAssignment);
                        $dateTime = new \DateTime();
                        $dateTime->sub(new \DateInterval('P' . $daysSinceMin . 'D'));
                        $query->where('ra_latest.date_assigned', '>', $dateTime->format('Y-m-d'));
                        if (is_array($this->daysSinceLastAssignment) && !empty($this->daysSinceLastAssignment[1])) {
                            $daysSinceMax = (int) $this->daysSinceLastAssignment[1] + 1; // Add one to include upper bound
                            $dateTime = new \DateTime();
                            $dateTime->add(new \DateInterval('P' . $daysSinceMax . 'D'));
                            $query->where('ra_latest.date_assigned', '<', $dateTime->format('Y-m-d'));
                        }
                    });

                // Incomplete count
                $query->addSelect(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.date_completed IS NULL AND ra.declined <> 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as incomplete_count'))
                    ->when($this->reviewsActive !== null, function ($query) {
                        $query->whereBetween(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.date_completed IS NULL AND ra.declined <> 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id)'), $this->reviewsActive);
                    });

                // Complete count
                $query->addSelect(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.date_completed IS NOT NULL AND ra.declined <> 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as complete_count'))
                    ->when($this->reviewsCompleted !== null, function ($query) {
                        $query->where(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.date_completed IS NOT NULL AND ra.declined <> 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id)'), '>=', $this->reviewsCompleted);
                    });

                // Declined count
                $query->addSelect(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.declined = 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as declined_count'));

                // Cancelled count
                $query->addSelect(DB::raw('(SELECT COALESCE(SUM(CASE WHEN ra.cancelled = 1 THEN 1 ELSE 0 END), 0) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id) as cancelled_count'));

                // Average time to complete
                switch (\Config::getVar('database', 'driver')) {
                    case 'mysql':
                    case 'mysqli':
                        $dateDiffClause = 'DATEDIFF(ra.date_completed, ra.date_notified)';
                        break;
                    default: // PostgreSQL
                        $dateDiffClause = 'DATE_PART(\'day\', ra.date_completed - ra.date_notified)';
                }
                $query->addSelect(DB::raw('(SELECT AVG(' . $dateDiffClause . ') FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.date_completed IS NOT NULL) as average_time'))
                    ->when($this->averageCompletion !== null, function ($query) use ($dateDiffClause) {
                        $query->where(DB::raw('(SELECT AVG(' . $dateDiffClause . ') FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.date_completed IS NOT NULL)'), '<=', $this->averageCompletion);
                    });

                // Average quality
                $query->addSelect(DB::raw('(SELECT AVG(ra.quality) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.quality IS NOT NULL) as reviewer_rating'))
                    ->when($this->reviewerRating !== null, function ($query) {
                        $query->where(DB::raw('(SELECT AVG(ra.quality) FROM review_assignments AS ra WHERE u.user_id = ra.reviewer_id AND ra.quality IS NOT NULL)'), '>=', $this->reviewerRating);
                    });
            });

        // Limit and offset results for pagination
        if (!is_null($this->count)) {
            $q->limit($this->count);
        }
        if (!is_null($this->offset)) {
            $q->offset($this->offset);
        }

        $orderLocales = $this->orderLocales;
        if (in_array($this->orderBy, [self::ORDERBY_GIVENNAME, self::ORDERBY_FAMILYNAME])) {
            if (empty($orderLocales)) {
                // No order by locales were specified but one was needed; get a default.
                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                $site = $siteDao->getSite();
                $orderLocales = [$site->getPrimaryLocale];
            } else {
                // We'll use the keys for table aliases below, so make sure they're clean
                $orderLocales = array_values($orderLocales);
            }
        }

        switch ($this->orderBy) {
            case self::ORDERBY_ID:
                $q->orderBy('u.user_id', $this->orderDirection);
                break;
            case self::ORDERBY_GIVENNAME:
                $aliases = [];
                foreach ($orderLocales as $key => $locale) {
                    $alias = $aliases[] = "usg{$key}";
                    $q->leftJoin("user_settings AS ${alias}", function ($join) use ($alias, $locale) {
                        return $join->on("${alias}.user_id", '=', 'u.user_id')
                            ->where("${alias}.setting_name", '=', Identity::IDENTITY_SETTING_GIVENNAME)
                            ->where("${alias}.locale", '=', $locale);
                    });
                }
                $q->addSelect([DB::raw('COALESCE(' . implode('.setting_value, ', $aliases) . '.setting_value) AS given_name')])
                    ->orderBy('given_name', $this->orderDirection);
                break;
            case self::ORDERBY_FAMILYNAME:
                $aliases = [];
                foreach ($orderLocales as $key => $locale) {
                    $alias = $aliases[] = "usf{$key}";
                    $q->leftJoin("user_settings AS ${alias}", function ($join) use ($alias, $locale) {
                        return $join->on("${alias}.user_id", '=', 'u.user_id')
                            ->where("${alias}.setting_name", '=', Identity::IDENTITY_SETTING_FAMILYNAME)
                            ->where("${alias}.locale", '=', $locale);
                    });
                }
                $q->addSelect([DB::raw('COALESCE(' . implode('.setting_value, ', $aliases) . '.setting_value) AS family_name')])
                    ->orderBy('family_name', $this->orderDirection);
                break;
            default: throw new \InvalidArgumentException('Invalid order by!');
        }

        // Add app-specific query statements
        HookRegistry::call('User::Collector', [$q, $this]);

        return $q;
    }
}
