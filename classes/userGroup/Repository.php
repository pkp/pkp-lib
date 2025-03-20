<?php

/**
 * @file classes/userGroup/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\Repository
 *
 * @brief A repository to find and manage userGroups.
 */

namespace PKP\userGroup;

use APP\core\Application;
use APP\facades\Repo;
use Carbon\Carbon;
use DateInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\site\SiteDAO;
use PKP\user\enums\UserMastheadStatus;
use PKP\userGroup\relationships\enums\UserUserGroupStatus;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\validation\ValidatorFactory;
use PKP\xml\PKPXMLParser;
use stdClass;

class Repository
{
    /**
     * A list of roles not able to change submissionMetadataEdit permission option.
     */
    public const NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES = [Role::ROLE_ID_MANAGER];

    /** @var string Max lifetime for the Editorial Masthead and Editorial History users cache. */
    public const MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME = '1 year';

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;


    /** @var PKPSchemaService<UserGroup> */
    protected $schemaService;

    public function __construct(PKPSchemaService $schemaService)
    {
        $this->schemaService = $schemaService;
    }

    /**
     * Retrieve UserGroup by id and optional context id.
     *
     */
    public function get(int $id, ?int $contextId = null): ?UserGroup
    {
        return UserGroup::findById($id, $contextId);
    }

    /**
     * Check if UserGroup exists by id and context id.
     *
     */
    public function exists(int $id, ?int $contextId = null): bool
    {
        $query = UserGroup::query()->where('user_group_id', $id);

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->exists();
    }

    /**
     * Get an instance of the map class for mapping
     * user groups to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a user group
     *
     * Perform validation checks on data used to add or edit a user group.
     *
     * @param UserGroup|null $userGroup The userGroup being edited. Pass `null` if creating a new userGroup
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported submission locales
     * @param string $primaryLocale The submission's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook UserGroup::validate [[$errors, $userGroup, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate($userGroup, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = app()->get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_USER_GROUP, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $userGroup,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_USER_GROUP),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_USER_GROUP),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_USER_GROUP), $allowedLocales);

        $errors = [];
        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('UserGroup::validate', [$errors, $userGroup, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Delete all user groups assigned to a certain context by contextId
     *
     */
    public function deleteByContextId(int $contextId): void
    {
        UserGroup::query()
            ->withContextIds([$contextId])
            ->each(function (UserGroup $userGroup) {
                $userGroup->delete();
            });
    }

    /**
     * Return all user group ids given a certain role ID and context id
     *
     */
    public function getArrayIdByRoleId(int $roleId, ?int $contextId = null): array
    {
        $query = UserGroup::query()
            ->withRoleIds([$roleId]);

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->pluck('user_group_id')->toArray();
    }

    /**
     * Return all user groups given role ids, context id and default flag
     *
     * @return LazyCollection<int, UserGroup>
     */
    public function getByRoleIds(array $roleIds, int $contextId, ?bool $default = null): LazyCollection
    {
        $query = UserGroup::withRoleIds($roleIds)
            ->withContextIds([$contextId]);

        if ($default !== null) {
            $query->isDefault($default);
        }

        return $query->cursor();
    }

    /**
     * Return all active or ended user groups for a user id and context id (optional)
     *
     * @return LazyCollection<int, UserGroup>
     */
    public function userUserGroups(int $userId, ?int $contextId = null, UserUserGroupStatus $status = UserUserGroupStatus::STATUS_ACTIVE): LazyCollection
    {
        $query = UserGroup::query()
            ->withUserIds([$userId])
            ->withUserUserGroupStatus($status->value);

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->cursor();
    }

    /**
     * Return all context IDs for masthead user groups the given user is or was assigned to
     *
     */
    public function getUserUserGroupsContextIds(int $userId): Collection
    {
        return UserGroup::query()
            ->withUserIds([$userId])
            ->withUserUserGroupStatus(UserUserGroupStatus::STATUS_ALL->value)
            ->masthead(true)
            ->pluck('context_id')
            ->unique();
    }

    /**
     * Determine whether a user is in a specific user group
     *
     */
    public function userInGroup(int $userId, int $userGroupId): bool
    {
        return UserUserGroup::query()
            ->withUserId($userId)
            ->withUserGroupIds([$userGroupId])
            ->withActive()
            ->exists();
    }

    /**
     * Determine whether an active user in a user group should be displayed on the masthead
     *
     */
    public function userOnMasthead(int $userId, ?int $userGroupId = null): bool
    {
        if ($userGroupId) {
            $userGroup = $this->get($userGroupId);
            if (!$userGroup || !$userGroup->masthead) {
                return false;
            }
        }

        $query = UserUserGroup::query()
            ->withUserId($userId)
            ->withActive()
            ->withMasthead();

        if ($userGroupId) {
            $query->withUserGroupIds([$userGroupId]);
        }

        return $query->exists();
    }

    /**
     * Update UserUserGroup masthead status for a UserGroup the user is currently active in
     *
     */
    public function updateUserUserGroupMasthead(int $userId, int $userGroupId, bool $masthead): void
    {
        UserUserGroup::query()
            ->withUserId($userId)
            ->withUserGroupIds([$userGroupId])
            ->withActive()
            ->update(['masthead' => $masthead]);

        $userGroup = UserGroup::find($userGroupId);
        if ($userGroup?->masthead) {
            self::forgetEditorialCache($userGroup->contextId);
        }
    }

    /**
     * Determine whether a context has a specific UserGroup
     *
     */
    public function contextHasGroup(int $contextId, int $userGroupId): bool
    {
        return UserGroup::query()
            ->withContextIds([$contextId])
            ->withUserGroupIds([$userGroupId])
            ->exists();
    }

    /**
     * Assign a user to a UserGroup
     *
     * @param string|null $startDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     * @param string|null $endDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     */
    public function assignUserToGroup(
        int $userId,
        int $userGroupId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?bool $masthead = null
    ): ?UserUserGroup {
        if ($endDate && !Carbon::parse($endDate)->isFuture()) {
            return null;
        }

        $dateStart = $startDate ?? Core::getCurrentDate();
        $userGroup = UserGroup::find($userGroupId);

        if (!$userGroup) {
            return null;
        }

        // Clear editorial masthead cache if a new user is assigned to a masthead role
        if ($userGroup->masthead && $masthead) {
            self::forgetEditorialCache($userGroup->contextId);
        }

        return UserUserGroup::create([
            'userId' => $userId,
            'userGroupId' => $userGroupId,
            'dateStart' => $dateStart,
            'dateEnd' => $endDate,
            'masthead' => $masthead,
        ]);
    }

    /**
     * Remove all user role assignments for a user. optionally within a specific UserGroup
     *
     * This should be used only when merging, i.e., fully deleting a user.
     *
     */
    public function deleteAssignmentsByUserId(int $userId, ?int $userGroupId = null): bool
    {
        if (!$userGroupId) {
            $contextIds = $this->getUserUserGroupsContextIds($userId);
            foreach ($contextIds as $contextId) {
                self::forgetEditorialCache($contextId);
                self::forgetEditorialHistoryCache($contextId);
            }
        }

        $query = UserUserGroup::query()->withUserId($userId);

        if ($userGroupId) {
            $query->withUserGroupIds([$userGroupId]);
            $userGroup = $this->get($userGroupId);
            if ($userGroup && $userGroup->masthead) {
                self::forgetEditorialCache($userGroup->contextId);
                self::forgetEditorialHistoryCache($userGroup->contextId);
            }
        }

        return $query->delete() > 0;
    }

    /**
     * End user assignments by setting the end date.
     *
     */
    public function endAssignments(int $contextId, int $userId, ?int $userGroupId = null): void
    {
        // Clear editorial masthead and history cache if the user was displayed on the masthead for the given role
        if ($this->userOnMasthead($userId, $userGroupId)) {
            self::forgetEditorialCache($contextId);
            self::forgetEditorialHistoryCache($contextId);
        }

        $dateEnd = Core::getCurrentDate();
        $query = UserUserGroup::query()
            ->withContextId($contextId)
            ->withUserId($userId)
            ->withActive();

        if ($userGroupId) {
            $query->withUserGroupIds([$userGroupId]);
        }

        $query->update(['date_end' => $dateEnd]);
    }

    /**
     * Get the user groups assigned to each stage.
     *
     * @return Collection<int, UserGroup>
     */
    public function getUserGroupsByStage(int $contextId, int $stageId, ?int $roleId = null, ?int $count = null): Collection
    {
        $query = UserGroup::query()
            ->withContextIds([$contextId])
            ->withStageIds([$stageId]);

        if ($roleId !== null) {
            $query->withRoleIds([$roleId]);
        }

        $query->orderByRoleId();

        if ($count !== null) {
            $query->limit($count);
        }

        return $query->get();
    }

    /**
     * Remove a user group from a stage
     *
     */
    public function removeGroupFromStage(int $contextId, int $userGroupId, int $stageId): bool
    {
        return UserGroupStage::query()
            ->withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->withStageId($stageId)
            ->delete() > 0;
    }

    /**
     * Get all stages assigned to one user group in one context.
     *
     * @param int $contextId The context ID.
     * @param int $userGroupId The UserGroup ID
     */
    public function getAssignedStagesByUserGroupId(int $contextId, int $userGroupId): Collection
    {
        return UserGroupStage::query()
            ->withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->pluck('stage_id');
    }

    /**
     * Load the XML file and move the settings to the DB
     *
     * @return bool True on success otherwise false
     */
    public function installSettings(?int $contextId, string $filename): bool
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filename);

        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
        $site = $siteDao->getSite();
        $installedLocales = $site->getInstalledLocales();

        if (!$tree) {
            return false;
        }

        foreach ($tree->getChildren() as $setting) {
            $roleId = hexdec($setting->getAttribute('roleId'));
            $nameKey = $setting->getAttribute('name');
            $abbrevKey = $setting->getAttribute('abbrev');
            $permitSelfRegistration = $setting->getAttribute('permitSelfRegistration') === 'true';
            $permitMetadataEdit = $setting->getAttribute('permitMetadataEdit') === 'true';
            $masthead = $setting->getAttribute('masthead') === 'true';
            $permitSettings = $setting->getAttribute('permitSettings') === 'true';

            // If has manager role then permitMetadataEdit can't be overridden
            if (in_array($roleId, self::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $permitMetadataEdit = $setting->getAttribute('permitMetadataEdit') === 'true';
                $permitSettings = $setting->getAttribute('permitSettings') === 'true';
            }

            $defaultStages = explode(',', (string) $setting->getAttribute('stages'));

            // Create a new UserGroup instance and set attributes
            $userGroup = new UserGroup([
                'roleId' => $roleId,
                'contextId' => $contextId,
                'permitSelfRegistration' => $permitSelfRegistration,
                'permitMetadataEdit' => $permitMetadataEdit,
                'isDefault' => true,
                'showTitle' => true,
                'masthead' => $masthead,
                'permitSettings' => $permitSettings,
            ]);

            // Save the UserGroup instance to the database
            $userGroup->save();

            $userGroupId = $userGroup->id;

            // Install default groups for each stage
            foreach ($defaultStages as $stageId) {
                $stageId = (int) trim($stageId);
                if ($stageId >= WORKFLOW_STAGE_ID_SUBMISSION && $stageId <= WORKFLOW_STAGE_ID_PRODUCTION) {
                    UserGroupStage::create([
                        'contextId' => $contextId,
                        'userGroupId' => $userGroupId,
                        'stageId' => $stageId,
                    ]);
                }
            }

            // Update the settings for nameLocaleKey and abbrevLocaleKey directly
            $userGroup->fill([
                'nameLocaleKey' => $nameKey,
                'abbrevLocaleKey' => $abbrevKey,
            ]);

            $userGroup->save();

            // Install the settings in the current locale for this context
            foreach ($installedLocales as $locale) {
                $this->installLocale($locale, $contextId);
            }
        }

        self::forgetEditorialCache($contextId);
        self::forgetEditorialHistoryCache($contextId);

        return true;
    }

    /**
     * use the locale keys stored in the settings table to install the locale settings
     *
     */
    public function installLocale(string $locale, ?int $contextId = null): void
    {
        $userGroups = UserGroup::query();

        if ($contextId !== null) {
            $userGroups->withContextIds([$contextId]);
        }

        $userGroups = $userGroups->get();

        foreach ($userGroups as $userGroup) {
            $nameKey = $userGroup->nameLocaleKey ?? null;
            $abbrevKey = $userGroup->abbrevLocaleKey ?? null;

            if ($nameKey) {
                $userGroup->name = [$locale => __($nameKey, [], $locale)];
            }

            if ($abbrevKey) {
                $userGroup->abbrev = [$locale => __($abbrevKey, [], $locale)];
            }

            $userGroup->save();
        }
    }

    /**
     * Cache/get cached array of masthead user IDs grouped by masthead role IDs
     * Format: [user_group_id => [user_ids]]
     *
     * @param array $mastheadRoles Masthead roles, filtered by the given context ID, and sorted as they should appear on the Editorial Masthead and Editorial History page
     */
    public function getMastheadUserIdsByRoleIds(array $mastheadRoles, int $contextId, UserUserGroupStatus $userUserGroupStatus = UserUserGroupStatus::STATUS_ACTIVE): array
    {
        $statusSuffix = match ($userUserGroupStatus) {
            UserUserGroupStatus::STATUS_ACTIVE => 'EditorialMasthead',
            UserUserGroupStatus::STATUS_ENDED => 'EditorialHistory',
            default => 'EditorialMasthead',
        };

        $cacheKey = __METHOD__ . $statusSuffix . $contextId . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        $expiration = DateInterval::createFromDateString(self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);

        return Cache::remember($cacheKey, $expiration, function () use ($mastheadRoles, $contextId, $userUserGroupStatus) {
            // extract UserGroup IDs from mastheadRoles within the context
            $mastheadRoleIds = array_map(fn (UserGroup $item) => $item->userGroupId, $mastheadRoles);

            // Query that gets all users that are or were active in the given masthead roles
            // and that have accepted to be displayed on the masthead for the roles.
            // Sort the results by role ID and user family name.
            $usersCollector = Repo::user()->getCollector();
            $usersQuery = $usersCollector
                ->filterByContextIds([$contextId])
                ->filterByUserGroupIds($mastheadRoleIds)
                ->filterByUserUserGroupStatus($userUserGroupStatus)
                ->filterByUserMastheadStatus(UserMastheadStatus::STATUS_ON)
                ->orderBy(
                    $usersCollector::ORDERBY_FAMILYNAME,
                    $usersCollector::ORDER_DIR_ASC,
                    [
                        Locale::getLocale(),
                        Application::get()->getRequest()->getSite()->getPrimaryLocale()
                    ]
                )
                ->orderByUserGroupIds($mastheadRoleIds)
                ->getQueryBuilder()
                ->get();

            // Get unique user IDs grouped by user group ID
            $userIdsByUserGroupId = $usersQuery->mapToGroups(function (stdClass $item, int $key) {
                return [$item->user_group_id => $item->user_id];
            })->map(function ($item) {
                return collect($item)->unique();
            });

            return $userIdsByUserGroupId->toArray();
        });
    }

    /**
     * Clear editorial masthead cache for a given context
     *
     */
    public static function forgetEditorialCache(int $contextId): void
    {
        $cacheKeyPrefix = 'PKP\userGroup\Repository::getMastheadUserIdsByRoleIds';
        $cacheKeys = [
            "{$cacheKeyPrefix}EditorialMasthead{$contextId}" . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME,
            "{$cacheKeyPrefix}EditorialHistory{$contextId}" . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME,
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear editorial history cache for a given context.
     *
     */
    public static function forgetEditorialHistoryCache(int $contextId): void
    {
        $cacheKey = "PKP\userGroup\Repository::getMastheadUserIdsByRoleIdsEditorialHistory{$contextId}" . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        Cache::forget($cacheKey);
    }
}
