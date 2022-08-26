<?php
/**
 * @file classes/user/Collector.php
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
use Carbon\Carbon;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use PKP\core\interfaces\CollectorInterface;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\plugins\Hook;

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
    public ?array $excludeRoles = null;
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

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
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

    /**
     * Defines whether reviewer data should be included
     */
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
     * Retrieve a set of users not assigned to the given roles
     * (Replaces UserGroupDAO::getUsersNotInRole)
     */
    public function filterExcludeRoles(?array $excludedRoles): self
    {
        $this->excludeRoles = $excludedRoles;
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
        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_DISABLED, self::STATUS_ALL], true)) {
            throw new InvalidArgumentException("Invalid status: \"{$this->status}\"");
        }
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
     *
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
     * @param array|null locales Optional list of locale precedences when ordering by localized columns
     */
    public function orderBy(string $sorter, string $direction = self::ORDER_DIR_DESC, ?array $locales = null): self
    {
        if (!in_array($sorter, [static::ORDERBY_FAMILYNAME, static::ORDERBY_GIVENNAME, static::ORDERBY_ID])) {
            throw new InvalidArgumentException("Invalid order by: ${sorter}");
        }
        if (!in_array($direction, [static::ORDER_DIR_ASC, static::ORDER_DIR_DESC])) {
            throw new InvalidArgumentException("Invalid order direction: ${direction}");
        }
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
        if ($this->offset !== null && $this->count === null) {
            throw new InvalidArgumentException('The offset requires the count to be defined');
        }

        $query = DB::table('users', 'u')
            ->select('u.*')
            // Filters by registration date
            ->when($this->registeredBefore !== null, fn (Builder $query) => $query->where('u.date_registered', '<', Carbon::rawParse($this->registeredBefore)->addDay()->toDateString()))
            ->when($this->registeredAfter !== null, fn (Builder $query) => $query->where('u.date_registered', '>=', $this->registeredAfter))
            // Filters by user ID
            ->when($this->userIds !== null, fn (Builder $query) => $query->whereIn('u.user_id', $this->userIds))
            ->when($this->excludeUserIds !== null, fn (Builder $query) => $query->whereNotIn('u.user_id', $this->excludeUserIds))
            // User enabled/disabled state
            ->when($this->status !== self::STATUS_ALL, fn (Builder $query) => $query->where('u.disabled', '=', $this->status === self::STATUS_DISABLED))
            // Adds limit and offset for pagination
            ->when($this->count !== null, fn (Builder $query) => $query->limit($this->count))
            ->when($this->offset !== null, fn (Builder $query) => $query->offset($this->offset));

        $this
            ->buildReviewerStatistics($query)
            ->buildUserGroupFilter($query)
            ->buildSearchFilter($query)
            ->buildSubEditorFilter($query)
            ->buildSettingsFilter($query)
            ->buildExcludedSubmissionStagesFilter($query)
            ->buildSubmissionAssignmentsFilter($query)
            ->buildOrderBy($query);

        // Add app-specific query statements
        Hook::call('User::Collector', [$query, $this]);

        return $query;
    }

    /**
     * Builds the filters related to submission assignments (submission, stage, user group)
     */
    protected function buildSubmissionAssignmentsFilter(Builder $query): self
    {
        if ($this->assignedTo === null) {
            return $this;
        }
        $query->whereExists(
            fn (Builder $query) => $query->from('stage_assignments', 'sa')
                ->join('user_group_stage AS ugs', 'sa.user_group_id', '=', 'ugs.user_group_id')
                ->whereColumn('sa.user_id', '=', 'u.user_id')
                ->when(isset($this->assignedTo['submissionId']), fn ($query) => $query->where('sa.submission_id', '=', $this->assignedTo['submissionId']))
                ->when(isset($this->assignedTo['stageId']), fn ($query) => $query->where('ugs.stage_id', '=', $this->assignedTo['stageId']))
                ->when(isset($this->assignedTo['userGroupId']), fn ($query) => $query->where('sa.user_group_id', '=', $this->assignedTo['userGroupId']))
        );
        return $this;
    }

    /**
     * Builds the filters related to the user group
     */
    protected function buildUserGroupFilter(Builder $query): self
    {
        if ($this->userGroupIds === null && $this->roleIds === null && $this->contextIds === null && $this->workflowStageIds === null) {
            return $this;
        }
        $query->whereExists(
            fn (Builder $query) => $query->from('user_user_groups', 'uug')
                ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                ->whereColumn('uug.user_id', '=', 'u.user_id')
                ->when($this->userGroupIds !== null, fn ($query) => $query->whereIn('uug.user_group_id', $this->userGroupIds))
                ->when(
                    $this->workflowStageIds !== null,
                    fn ($query) => $query
                        ->join('user_group_stage AS ugs', 'ug.user_group_id', '=', 'ugs.user_group_id')
                        ->whereIn('ugs.stage_id', $this->workflowStageIds)
                )
                ->when($this->excludeRoles !== null, fn ($query) => $query->whereNotIn('ug.role_id', $this->excludeRoles))
                ->when($this->roleIds !== null, fn ($query) => $query->whereIn('ug.role_id', $this->roleIds))
                ->when($this->contextIds !== null, fn ($query) => $query->whereIn('ug.context_id', $this->contextIds))
        );
        return $this;
    }

    /**
     * Builds the settings filter
     */
    protected function buildSettingsFilter(Builder $query): self
    {
        foreach ($this->settings ?? [] as $name => $value) {
            $query->whereExists(
                fn (Builder $query) => $query->from('user_settings', 'us')
                    ->whereColumn('us.user_id', '=', 'u.user_id')
                    ->where('us.setting_name', '=', $name)
                    ->where('us.setting_value', '=', $value)
            );
        }
        return $this;
    }

    /**
     * Builds the excluded submission stages filter
     */
    protected function buildExcludedSubmissionStagesFilter(Builder $query): self
    {
        if ($this->excludeSubmissionStage === null) {
            return $this;
        }
        $query->whereExists(
            fn (Builder $query) => $query->from('user_user_groups', 'uug')
                ->join('user_group_stage AS ugs', 'ugs.user_group_id', '=', 'uug.user_group_id')
                ->leftJoin(
                    'stage_assignments AS sa',
                    fn (JoinClause $join) => $join->on('sa.user_id', '=', 'uug.user_id')
                        ->on('sa.user_group_id', '=', 'uug.user_group_id')
                        ->where('sa.submission_id', '=', $this->excludeSubmissionStage['submission_id'])
                )
                ->whereColumn('uug.user_id', '=', 'u.user_id')
                ->where('uug.user_group_id', '=', $this->excludeSubmissionStage['user_group_id'])
                ->where('ugs.stage_id', '=', $this->excludeSubmissionStage['stage_id'])
                ->whereNull('sa.user_group_id')
        );
        return $this;
    }

    /**
     * Builds the sub-editor filter
     */
    protected function buildSubEditorFilter(Builder $query): self
    {
        $subEditorFilters = [Application::ASSOC_TYPE_SECTION => $this->assignedSectionIds, Application::ASSOC_TYPE_CATEGORY => $this->assignedCategoryIds];
        foreach (array_filter($subEditorFilters, fn (?array $assocIds) => !empty($assocIds)) as $assocType => $assocIds) {
            $query->whereExists(
                fn (Builder $query) => $query->from('subeditor_submission_group', 'ssg')
                    ->whereColumn('ssg.user_id', '=', 'u.user_id')
                    ->where('ssg.assoc_type', '=', $assocType)
                    ->whereIn('ssg.assoc_id', $assocIds)
            );
        }
        return $this;
    }

    /**
     * Builds the reviewer statistics and related filters
     */
    protected function buildReviewerStatistics(Builder $query): self
    {
        if (!$this->includeReviewerData) {
            return $this;
        }

        $dateDiff = fn (string $dateA, string $dateB): string => DB::connection() instanceof MySqlConnection
            ? "DATEDIFF(${dateA}, ${dateB})"
            : "DATE_PART('day', ${dateA} - ${dateB})";

        $query->leftJoinSub(
            fn (Builder $query) => $query->from('review_assignments', 'ra')
                ->groupBy('ra.reviewer_id')
                ->select('ra.reviewer_id')
                ->selectRaw('MAX(ra.date_assigned) AS last_assigned')
                ->selectRaw('COUNT(CASE WHEN ra.date_completed IS NULL AND ra.declined = 0 AND ra.cancelled = 0 THEN 1 END) AS incomplete_count')
                ->selectRaw('COUNT(CASE WHEN ra.date_completed IS NOT NULL AND ra.declined = 0 THEN 1 END) AS complete_count')
                ->selectRaw('SUM(ra.declined) AS declined_count')
                ->selectRaw('SUM(ra.cancelled) AS cancelled_count')
                ->selectRaw('AVG(' . $dateDiff('ra.date_completed', 'ra.date_notified') . ') AS average_time')
                ->selectRaw('AVG(ra.quality) AS reviewer_rating'),
            'ra_stats',
            'u.user_id',
            '=',
            'ra_stats.reviewer_id'
        )
            // Select all statistics columns
            ->addSelect('ra_stats.*')
            // Reviewer rating
            ->when($this->reviewerRating !== null, fn (Builder $query) => $query->where('ra_stats.reviewer_rating', '>=', $this->reviewerRating))
            // Completed reviews
            ->when($this->reviewsCompleted !== null, fn (Builder $query) => $query->where('ra_stats.complete_count', '>=', $this->reviewsCompleted))
            // Minimum active reviews
            ->when(($minReviews = $this->reviewsActive[0] ?? null) !== null, fn (Builder $query) => $query->where('ra_stats.incomplete_count', '>=', $minReviews))
            // Maximum active reviews
            ->when(($maxReviews = $this->reviewsActive[1] ?? null) !== null, fn (Builder $query) => $query->where('ra_stats.incomplete_count', '<=', $maxReviews))
            // Minimum days since last review assignment
            ->when(
                ($minDays = $this->daysSinceLastAssignment[0] ?? null) !== null,
                fn (Builder $query) => $query
                    ->where('ra_stats.last_assigned', '<=', Carbon::now()->subDays($minDays)->toDateString())
            )
            // Maximum days since last review assignment
            ->when(
                ($maxDays = $this->daysSinceLastAssignment[1] ?? null) !== null,
                fn (Builder $query) => $query
                    ->where('ra_stats.last_assigned', '>=', Carbon::now()->subDays($maxDays + 1)->toDateString()) // Add one to include upper bound
            )
            // Average days to complete review
            ->when($this->averageCompletion !== null, fn (Builder $query) => $query->where('ra_stats.average_time', '<=', $this->averageCompletion));

        return $this;
    }

    /**
     * Builds the user search
     */
    protected function buildSearchFilter(Builder $query): self
    {
        if ($this->searchPhrase === null || !strlen($searchPhrase = trim($this->searchPhrase))) {
            return $this;
        }
        // Settings where the search will be performed
        $settings = [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName', 'affiliation', 'biography', 'orcid'];
        // Break words by whitespace, trims and escapes "%" and "_"
        $words = array_map(fn (string $word) => '%' . addcslashes($word, '%_') . '%', PKPString::regexp_split('/\s+/', $searchPhrase));
        foreach ($words as $word) {
            $query->where(
                fn ($query) => $query->whereRaw('LOWER(u.username) LIKE LOWER(?)', [$word])
                    ->orWhereRaw('LOWER(u.email) LIKE LOWER(?)', [$word])
                    ->orWhereExists(
                        fn (Builder $query) => $query->from('user_settings', 'us')
                            ->whereColumn('us.user_id', '=', 'u.user_id')
                            ->whereIn('us.setting_name', $settings)
                            ->whereRaw('LOWER(us.setting_value) LIKE LOWER(?)', [$word])
                    )
                    ->orWhereExists(
                        fn (Builder $query) => $query->from('user_interests', 'ui')
                            ->join('controlled_vocab_entry_settings AS cves', 'ui.controlled_vocab_entry_id', '=', 'cves.controlled_vocab_entry_id')
                            ->whereColumn('ui.user_id', '=', 'u.user_id')
                            ->whereRaw('LOWER(cves.setting_value) LIKE LOWER(?)', [$word])
                    )
            );
        }

        return $this;
    }

    /**
     * Handles the order by clause
     */
    protected function buildOrderBy(Builder $query): self
    {
        $orderByFields = [self::ORDERBY_ID => 'u.user_id'];
        if ($orderByField = $orderByFields[$this->orderBy] ?? null) {
            $query->orderBy($orderByField, $this->orderDirection);
            return $this;
        }

        $nameSettings = [self::ORDERBY_GIVENNAME => Identity::IDENTITY_SETTING_GIVENNAME, self::ORDERBY_FAMILYNAME => Identity::IDENTITY_SETTING_FAMILYNAME];
        if ($nameSettings[$this->orderBy] ?? null) {
            $locales = array_unique(
                empty($this->orderLocales)
                    ? [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()]
                    : array_values($this->orderLocales)
            );
            $sortedSettings = array_values($this->orderBy === self::ORDERBY_GIVENNAME ? $nameSettings : array_reverse($nameSettings));
            $query->orderBy(
                function (Builder $query) use ($sortedSettings, $locales): void {
                    $query->fromSub(fn (Builder $query) => $query->from(null)->selectRaw(0), 'placeholder');
                    $aliasesBySetting = [];
                    foreach ($sortedSettings as $i => $setting) {
                        $aliases = [];
                        foreach ($locales as $j => $locale) {
                            $aliases[] = $alias = "us_${i}_${j}";
                            $query->leftJoin(
                                "user_settings AS ${alias}",
                                fn (JoinClause $join) => $join
                                    ->on("${alias}.user_id", '=', 'u.user_id')
                                    ->where("${alias}.setting_name", '=', $setting)
                                    ->where("${alias}.locale", '=', $locale)
                            );
                        }
                        $aliasesBySetting[] = $aliases;
                    }
                    // Build a possibly long CONCAT(COALESCE(given_localeA, given_localeB, [...]), COALESCE(family_localeA, family_localeB, [...])
                    $coalescedSettings = array_map(
                        fn (array $aliases) => 'COALESCE(' . implode(', ', array_map(fn (string $alias) => "${alias}.setting_value", $aliases)) . ", '')",
                        $aliasesBySetting
                    );
                    $query->selectRaw('CONCAT(' . implode(', ', $coalescedSettings) . ')');
                },
                $this->orderDirection
            );
        }

        return $this;
    }
}
