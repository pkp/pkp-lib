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

use APP\core\Application;
use APP\i18n\AppLocale;
use Carbon\Carbon;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\core\interfaces\CollectorInterface;
use PKP\core\PKPString;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    public const ORDERBY_ID = 'id';
    public const ORDERBY_GIVENNAME = 'givenName';
    public const ORDERBY_FAMILYNAME = 'familyName';

    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_ALL = null;

    public DAO $dao;

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
    public ?array $daysSinceLastAssignment = null;
    public ?int $averageCompletion = null;
    public ?array $reviewsActive = null;
    public ?int $count = null;
    public ?int $offset = null;

    /**
     * Constructor
     */
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

    /**
     * Filter by user IDs
     */
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
    public function filterByReviewsCompleted(?int $reviewsCompleted): self
    {
        $this->includeReviewerData(true);
        $this->reviewsCompleted = $reviewsCompleted;
        return $this;
    }

    /**
     * Limit results to those who's last review assignment was at least this many
     * days ago.
     */
    public function filterByDaysSinceLastAssignment(?int $minimumDaysSinceLastAssignment = null, ?int $maximumDaysSinceLastAssignment = null): self
    {
        $this->includeReviewerData(true);
        $this->daysSinceLastAssignment = [$minimumDaysSinceLastAssignment, $maximumDaysSinceLastAssignment];
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
     */
    public function filterByReviewsActive(?int $minimumReviewsActive = null, ?int $maximumReviewsActive = null): self
    {
        $this->includeReviewerData(true);
        $this->reviewsActive = [$minimumReviewsActive, $maximumReviewsActive];
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
                throw new InvalidArgumentException('If a stage or user group ID is specified, a submission ID must be specified as well.');
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
     * @param string|null $status self::STATUS_ACTIVE, self::STATUS_DISABLED or self::STATUS_ALL.
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

    /**
     * Filter by exact match of user settings (the locale is ignored)
     * @param array|null $settings The key must be a valid setting_name while the value will match the setting_value
     */
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
        $q = DB::table('users', 'u')
            ->select('u.*')

            // Handle any role assignment related constraints
            ->when($this->userGroupIds !== null || $this->roleIds !== null || $this->contextIds !== null || $this->workflowStageIds !== null, function (Builder $query): void {
                $query->whereExists(function (Builder $query) {
                    $query->from('user_user_groups', 'uug')
                        ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                        ->whereColumn('uug.user_id', '=', 'u.user_id')
                        ->when($this->userGroupIds !== null, function (Builder $query): void {
                            $query->whereIn('uug.user_group_id', $this->userGroupIds);
                        })
                        ->when($this->workflowStageIds !== null, function (Builder $query): void {
                            $query->join('user_group_stage AS ugs', 'ug.user_group_id', '=', 'ugs.user_group_id')
                                ->whereIn('ugs.stage_id', $this->workflowStageIds);
                        })
                        ->when($this->roleIds !== null, function (Builder $query): void {
                            $query->whereIn('ug.role_id', $this->roleIds);
                        })
                        ->when($this->contextIds !== null, function (Builder $query): void {
                            $query->whereIn('ug.context_id', $this->contextIds);
                        });
                });
            })

            ->when($this->registeredBefore !== null, function (Builder $query): void {
                // Include users who registered up to the end of the day
                $query->where('u.date_registered', '<', Carbon::rawParse($this->registeredBefore)->addDay()->toDateString());
            })

            ->when($this->registeredAfter !== null, function (Builder $query): void {
                $query->where('u.date_registered', '>=', $this->registeredAfter);
            })

            ->when($this->userIds !== null, function (Builder $query): void {
                $query->whereIn('u.user_id', $this->userIds);
            })

            ->when($this->excludeUserIds !== null, function (Builder $query): void {
                $query->whereNotIn('u.user_id', $this->excludeUserIds);
            })

            ->when($this->settings !== null, function (Builder $query): void {
                foreach ($this->settings as $settingName => $value) {
                    $query->whereExists(function (Builder $query) use ($settingName, $value): void {
                        $query->from('user_settings', 'us')
                            ->whereColumn('us.user_id', '=', 'u.user_id')
                            ->where('us.setting_name', '=', $settingName)
                            ->where('us.setting_value', '=', $value);
                    });
                }
            })

            ->when($this->excludeSubmissionStage !== null, function (Builder $query): void {
                // Left join on a match for the excluded submission stage list, then assert that it's null
                $query->join('user_user_groups AS uug_exclude', 'u.user_id', '=', 'uug_exclude.user_id')
                    ->join('user_group_stage AS ugs_exclude', function (JoinClause $join): void {
                        $join->on('uug_exclude.user_group_id', '=', 'ugs_exclude.user_group_id')
                            ->where('ugs_exclude.stage_id', '=', $this->excludeSubmissionStage['stage_id']);
                    })
                    ->leftJoin('stage_assignments AS sa_exclude', function (JoinClause $join): void {
                        $join->on('sa_exclude.user_id', '=', 'uug_exclude.user_id')
                            ->on('sa_exclude.user_group_id', '=', 'uug_exclude.user_group_id')
                            ->where('sa_exclude.submission_id', '=', $this->excludeSubmissionStage['submission_id']);
                    })
                    ->where('uug_exclude.user_group_id', '=', $this->excludeSubmissionStage['user_group_id'])
                    ->whereNull('sa_exclude.user_group_id');
            })

            // Handle conditions related to submission assignments (submission, stage, user group)
            ->when($this->assignedTo !== null, function (Builder $query): void {
                $query->whereExists(function (Builder $query): void {
                    $query->from('stage_assignments', 'sa')
                        ->join('user_group_stage AS ugs', 'sa.user_group_id', '=', 'ugs.user_group_id')
                        ->whereColumn('sa.user_id', '=', 'u.user_id')
                        ->when(isset($this->assignedTo['submissionId']), function (Builder $query): void {
                            $query->where('sa.submission_id', '=', $this->assignedTo['submissionId']);
                        })
                        ->when(isset($this->assignedTo['stageId']), function (Builder $query): void {
                            $query->where('ugs.stage_id', '=', $this->assignedTo['stageId']);
                        })
                        ->when(isset($this->assignedTo['userGroupId']), function (Builder $query): void {
                            $query->where('sa.user_group_id', '=', $this->assignedTo['userGroupId']);
                        });
                });
            })

            // User enabled/disabled state
            ->when($this->status !== self::STATUS_ALL, function (Builder $query): void {
                if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_DISABLED])) {
                    throw new InvalidArgumentException("Invalid status: \"{$this->status}\"");
                }
                $query->where('u.disabled', '=', $this->status === self::STATUS_DISABLED);
            })

            ->when(true, function (Builder $query): void {
                foreach ([Application::ASSOC_TYPE_SECTION => $this->assignedSectionIds, Application::ASSOC_TYPE_CATEGORY => $this->assignedCategoryIds] as $assocType => $assocIds) {
                    if ($assocIds !== null) {
                        $query->whereExists(function (Builder $query) use ($assocIds, $assocType): void {
                            $query->from('subeditor_submission_group', 'ssg')
                                ->whereColumn('ssg.user_id', '=', 'u.user_id')
                                ->where('ssg.assoc_type', '=', $assocType)
                                ->whereIn('ssg.assoc_id', $assocIds);
                        });
                    }
                }
            })

            ->when(strlen($searchPhrase = trim($this->searchPhrase)), function (Builder $query) use ($searchPhrase): void {
                $words = array_map(function (string $word): string {
                    return '%' . addcslashes($word, '%_') . '%';
                }, PKPString::regexp_split('/\s+/', $searchPhrase));
                foreach ($words as $word) {
                    $query->where(function(Builder $query) use ($word): void {
                        $query->whereRaw('LOWER(u.username) LIKE LOWER(?)', [$word])
                            ->orWhereRaw('LOWER(u.email) LIKE LOWER(?)', [$word])
                            ->orWhereExists(function (Builder $query) use ($word): void {
                                $query->from('user_settings', 'us')
                                    ->whereColumn('us.user_id', '=', 'u.user_id')
                                    ->whereIn('us.setting_name', [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName', 'affiliation', 'biography', 'orcid'])
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

            // When reviewer data is desired, fetch statistics and handle review related constraints
            ->when($this->includeReviewerData, function (Builder $query): void {
                // Compile the statistics into a sub-query
                $query->leftJoinSub(function (Builder $query): void {
                    $dateDiff = DB::connection() instanceof MySqlConnection
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
                        ->selectRaw("AVG(${dateDiff}) AS average_time")
                        ->selectRaw('AVG(ra.quality) AS reviewer_rating');
                }, 'ra_stats', 'u.user_id', '=', 'ra_stats.reviewer_id')

                    // Select all statistics columns
                    ->addSelect('ra_stats.*')

                    // Reviewer rating
                    ->when($this->reviewerRating !== null, function (Builder $query): void {
                        $query->where('ra_stats.reviewer_rating', '>=', $this->reviewerRating);
                    })

                    // Completed reviews
                    ->when($this->reviewsCompleted !== null, function (Builder $query): void {
                        $query->where('ra_stats.complete_count', '>=', $this->reviewsCompleted);
                    })

                    // Active reviews
                    ->when($this->reviewsActive !== null, function (Builder $query): void {
                        [$min, $max] = $this->reviewsActive;
                        if ($min !== null) {
                            $query->where('ra_stats.incomplete_count', '>=', $min);
                        }
                        if ($max !== null) {
                            $query->where('ra_stats.incomplete_count', '<=', $max);
                        }
                    })

                    // Days since last review assignment
                    ->when($this->daysSinceLastAssignment !== null, function (Builder $query): void {
                        [$min, $max] = $this->daysSinceLastAssignment;
                        if ($min !== null) {
                            $dbTimeMin = (string) Carbon::now()->subDays((int) $min)->toDateString();
                            $query->where('ra_stats.last_assigned', '<=', $dbTimeMin);
                        }
                        if ($max !== null) {
                            $dbTimeMax = (string) Carbon::now()->subDays((int) $max + 1)->toDateString(); // Add one to include upper bound
                            $query->where('ra_stats.last_assigned', '>=', $dbTimeMax);
                        }
                    })

                    // Average days to complete review
                    ->when($this->averageCompletion !== null, function (Builder $query): void {
                        $query->where('ra_stats.average_time', '<=', $this->averageCompletion);
                    });
            })

            // Limit and offset results for pagination
            ->when($this->count !== null, function (Builder $query): void {
                $query->limit($this->count);
            })

            ->when($this->offset !== null, function (Builder $query): void {
                if ($this->count === null) {
                    throw new InvalidArgumentException('The offset requires the count to be defined');
                }
                $query->offset($this->offset);
            })

            ->when(
                in_array($this->orderBy = self::ORDERBY_GIVENNAME, $settingMap = [
                    self::ORDERBY_GIVENNAME => Identity::IDENTITY_SETTING_GIVENNAME,
                    self::ORDERBY_FAMILYNAME => Identity::IDENTITY_SETTING_FAMILYNAME
                ]),
                function (Builder $query) use ($settingMap): void {
                    $query->orderBy(function (Builder $query) use ($settingMap): void {
                        $locales = array_unique(
                            empty($this->orderLocales)
                                ? [AppLocale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()]
                                : $this->orderLocales
                        );
                        // The bindings below will be used to build a sequence of "CASE locale WHEN en_US THEN 1 WHEN pt_BR THEN 2 [...] ELSE $lastIndex + 1 END" below
                        $lastIndex = 0;
                        $bindinds = array_reduce($locales, function (array $bindings, string $locale) use (&$lastIndex): array {
                            array_push($bindings, $locale, ++$lastIndex);
                            return $bindings;
                        }, []);
                        $bindinds[] = ++$lastIndex;
                        
                        $query->from('user_settings', 'us')
                            ->whereColumn('us.user_id', '=', 'u.user_id')
                            ->where('us.setting_name', '=', $settingMap[$this->orderBy])
                            ->whereIn('us.locale', $locales)
                            ->orderByRaw("COALESCE(us.setting_value, '') = ''") // Not empty values first
                            ->orderByRaw('CASE us.locale ' . str_repeat('WHEN ? THEN ?', count($locales)) . ' ELSE ? END', $bindinds) // Then follow the locale order
                            ->limit(1)
                            ->select('us.setting_value');
                    }, $this->orderDirection);
                },
                function (Builder $query): void {
                    $sortMap = [self::ORDERBY_ID => 'u.user_id'];
                    if (!isset($sortMap[$this->orderBy])) {
                        throw new InvalidArgumentException("Invalid order by: {$this->orderBy}");
                    }
                    $query->orderBy($sortMap[$this->orderBy], $this->orderDirection);
                }
            );

        // Add app-specific query statements
        HookRegistry::call('User::Collector', [$q, $this]);

        return $q;
    }
}
