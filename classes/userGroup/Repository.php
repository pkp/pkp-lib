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
use APP\core\Request;
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
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\enums\UserUserGroupStatus;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\validation\ValidatorFactory;
use PKP\xml\PKPXMLParser;
use Illuminate\Database\Query\Builder;
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
     * Create UserGroup instance
     *
     * @param array $params
     * @return UserGroup
     */
    public function newDataObject(array $params = []): UserGroup
    {
        return new UserGroup($params);
    }

    /**
     * Retrieve UserGroup by id and optional context id.
     *
     * @param int $id
     * @param int|null $contextId
     * @return UserGroup|null
     */
    public function get(int $id, ?int $contextId = null): ?UserGroup
    {
        $query = UserGroup::query()->where('user_group_id', $id);

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->first();
    }

    /**
     * Check if UserGroup exists by id and context id.
     *
     * @param int $id
     * @param int|null $contextId
     * @return bool
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
     * Add new UserGroup to database
     *
     * @param UserGroup $userGroup
     * @return int The id of the new UserGroup
     */
    public function add(UserGroup $userGroup): int
    {
        $userGroup->save();

        // reload to make sure that all relationships and settings are loaded
        $userGroup = $this->get($userGroup->user_group_id, $userGroup->context_id);

        Hook::call('UserGroup::add', [$userGroup]);

        // Clear editorial masthead cache if the new role should be added to the masthead
        if ($userGroup->masthead) {
            self::forgetEditorialMastheadCache($userGroup->context_id);
        }

        return $userGroup->user_group_id;
    }

    /**
     * Edit existing UserGroup with new parameters
     *
     * @param UserGroup $userGroup
     * @param array $params
     * @return void
     */
    public function edit(UserGroup $userGroup, array $params): void
    {
        // merge existing data with new parameters
        $userGroup->fill($params);
        $userGroup->save();

        Hook::call('UserGroup::edit', [$userGroup, $params]);

        // Clear editorial masthead and history cache if the role is on the masthead
        if ($userGroup->masthead) {
            self::forgetEditorialMastheadCache($userGroup->context_id);
            self::forgetEditorialHistoryCache($userGroup->context_id);
        }

        // reload to make sure all relationships and settings are updated
        $this->get($userGroup->user_group_id, $userGroup->context_id);
    }

    /**
     * Delete UserGroup from the database.
     *
     * @param UserGroup $userGroup
     * @return void
     */
    public function delete(UserGroup $userGroup): void
    {
        Hook::call('UserGroup::delete::before', [$userGroup]);

        // clear editorial masthead and history cache if the role is on the masthead
        if ($userGroup->masthead) {
            self::forgetEditorialMastheadCache($userGroup->context_id);
            self::forgetEditorialHistoryCache($userGroup->context_id);
        }

        $userGroup->delete();

        Hook::call('UserGroup::delete', [$userGroup]);
    }

    /**
     * Delete all user groups assigned to a certain context by contextId
     *
     * @param int $contextId
     * @return void
     */
    public function deleteByContextId(int $contextId): void
    {
        UserGroup::query()
            ->withContextIds([$contextId])
            ->each(function (UserGroup $userGroup) {
                $this->delete($userGroup);
            });
    }

    /**
     * Return all user group ids given a certain role ID and context id
     *
     * @param int $roleId
     * @param int|null $contextId
     * @return array
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
     * @param array $roleIds
     * @param int $contextId
     * @param bool|null $default
     * @return LazyCollection<int, UserGroup>
     */
    public function getByRoleIds(array $roleIds, int $contextId, ?bool $default = null): LazyCollection
    {
        $query = UserGroup::query()
            ->withRoleIds($roleIds)
            ->withContextIds([$contextId]);

        if ($default !== null) {
            $query->isDefault($default);
        }

        return $query->cursor();
    }

    /**
     * Return all active or ended user groups for a user id and context id (optional)
     *
     * @param int $userId
     * @param int|null $contextId
     * @param UserUserGroupStatus $status
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
     * @param int $userId
     * @return Collection
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
     * @param int $userId
     * @param int $userGroupId
     * @return bool
     */
    public function userInGroup(int $userId, int $userGroupId): bool
    {
        return UserUserGroup::query()
            ->withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->withActive()
            ->exists();
    }

    /**
     * Determine whether an active user in a user group should be displayed on the masthead
     *
     * @param int $userId
     * @param int|null $userGroupId
     * @return bool
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
            $query->withUserGroupId($userGroupId);
        }

        return $query->exists();
    }

    /**
     * Get UserUserGroup masthead status for a UserGroup the user is currently active in
     *
     * @param int $userId
     * @param int $userGroupId
     * @return UserUserGroupMastheadStatus
     */
    public function getUserUserGroupMastheadStatus(int $userId, int $userGroupId): UserUserGroupMastheadStatus
    {
        $masthead = UserUserGroup::query()
            ->withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->withActive()
            ->pluck('masthead')
            ->first();

        return match ($masthead) {
            1 => UserUserGroupMastheadStatus::STATUS_ON,
            0 => UserUserGroupMastheadStatus::STATUS_OFF,
            default => UserUserGroupMastheadStatus::STATUS_NULL,
        };
    }

    /**
     * Determine whether a context has a specific UserGroup
     *
     * @param int $contextId
     * @param int $userGroupId
     * @return bool
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
     * @param int $userId
     * @param int $userGroupId
     * @param string|null $startDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     * @param string|null $endDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     * @param UserUserGroupMastheadStatus|null $mastheadStatus
     * @return UserUserGroup|null
     */
    public function assignUserToGroup(
        int $userId,
        int $userGroupId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?UserUserGroupMastheadStatus $mastheadStatus = null
    ): ?UserUserGroup {
        if ($endDate && !Carbon::parse($endDate)->isFuture()) {
            return null;
        }

        $dateStart = $startDate ?? Core::getCurrentDate();
        $userGroup = $this->get($userGroupId, null);

        if (!$userGroup) {
            return null;
        }

        // Determine masthead status
        $masthead = match ($mastheadStatus) {
            UserUserGroupMastheadStatus::STATUS_ON => 1,
            UserUserGroupMastheadStatus::STATUS_OFF => 0,
            default => $userGroup->masthead ? 1 : null,
        };

        // Clear editorial masthead cache if a new user is assigned to a masthead role
        if ($userGroup->masthead) {
            self::forgetEditorialMastheadCache($userGroup->context_id);
        }

        return UserUserGroup::create([
            'user_id' => $userId,
            'user_group_id' => $userGroupId,
            'date_start' => $dateStart,
            'date_end' => $endDate,
            'masthead' => $masthead,
        ]);
    }

    /**
     * Remove all user role assignments for a user. optionally within a specific UserGroup
     *
     * This should be used only when merging, i.e., fully deleting a user.
     *
     * @param int $userId
     * @param int|null $userGroupId
     * @return bool
     */
    public function deleteAssignmentsByUserId(int $userId, ?int $userGroupId = null): bool
    {
        if (!$userGroupId) {
            $contextIds = $this->getUserUserGroupsContextIds($userId);
            foreach ($contextIds as $contextId) {
                self::forgetEditorialMastheadCache($contextId);
                self::forgetEditorialHistoryCache($contextId);
            }
        }

        $query = UserUserGroup::query()->withUserId($userId);

        if ($userGroupId) {
            $query->withUserGroupId($userGroupId);
            $userGroup = $this->get($userGroupId, null);
            if ($userGroup && $userGroup->masthead) {
                self::forgetEditorialMastheadCache($userGroup->context_id);
                self::forgetEditorialHistoryCache($userGroup->context_id);
            }
        }

        return $query->delete() > 0;
    }

    /**
     * End user assignments by setting the end date.
     *
     * @param int $contextId
     * @param int $userId
     * @param int|null $userGroupId
     * @return void
     */
    public function endAssignments(int $contextId, int $userId, ?int $userGroupId = null): void
    {
        // Clear editorial masthead and history cache if the user was displayed on the masthead for the given role
        if ($this->userOnMasthead($userId, $userGroupId)) {
            self::forgetEditorialMastheadCache($contextId);
            self::forgetEditorialHistoryCache($contextId);
        }

        $dateEnd = Core::getCurrentDate();
        $query = UserUserGroup::query()
            ->withContextId($contextId)
            ->withUserId($userId)
            ->withActive();

        if ($userGroupId) {
            $query->withUserGroupId($userGroupId);
        }

        $query->update(['date_end' => $dateEnd]);
    }

    /**
     * Get the user groups assigned to each stage.
     *
     * @param int $contextId
     * @param int $stageId
     * @param int|null $roleId
     * @param int|null $count
     * @return LazyCollection<int, UserGroup>
     */
    public function getUserGroupsByStage(int $contextId, int $stageId, ?int $roleId = null, ?int $count = null): LazyCollection
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

        return $query->cursor();
    }

    /**
     * Remove a user group from a stage
     *
     * @param int $contextId
     * @param int $userGroupId
     * @param int $stageId
     * @return bool
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
     * @return Collection
     */
    public function getAssignedStagesByUserGroupId(int $contextId, int $userGroupId): Collection
    {
        return UserGroupStage::query()
            ->withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->pluck('stage_id');
    }

    /**
     * Retrieve a keyed Collection (key = user_group_id, value = count) with the amount of active users for each UserGroup.
     *
     * @param int|null $contextId
     * @return Collection
     */
    public function getUserCountByContextId(?int $contextId = null): Collection
    {
        $currentDateTime = now();

        $query = UserGroup::query()
            ->select('user_groups.user_group_id')
            ->selectRaw('COUNT(user_user_groups.user_id) AS count')
            ->join('user_user_groups', 'user_user_groups.user_group_id', '=', 'user_groups.user_group_id')
            ->join('users', 'users.user_id', '=', 'user_user_groups.user_id')
            ->where('users.disabled', 0)
            ->where(function (Builder $q) use ($currentDateTime) {
                $q->where('user_user_groups.date_start', '<=', $currentDateTime)
                  ->orWhereNull('user_user_groups.date_start');
            })
            ->where(function (Builder $q) use ($currentDateTime) {
                $q->where('user_user_groups.date_end', '>', $currentDateTime)
                  ->orWhereNull('user_user_groups.date_end');
            })
            ->groupBy('user_groups.user_group_id');

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->pluck('count', 'user_group_id');
    }

    /**
     * Get the user group a new author may be assigned to
     * when they make their first submission, if they are
     * not already assigned to an author user group.
     *
     * This returns the first user group with ROLE_ID_AUTHOR
     * that permits self-registration.
     *
     * @param int $contextId
     * @return UserGroup|null
     */
    public function getFirstSubmitAsAuthorUserGroup(int $contextId): ?UserGroup
    {
        return UserGroup::query()
            ->withContextIds([$contextId])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->permitSelfRegistration(true)
            ->first();
    }

    /**
     * Load the XML file and move the settings to the DB
     *
     * @param int|null $contextId
     * @param string $filename
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

            // If has manager role then permitMetadataEdit can't be overridden
            if (in_array($roleId, self::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $permitMetadataEdit = $setting->getAttribute('permitMetadataEdit') === 'true';
            }

            $defaultStages = explode(',', (string) $setting->getAttribute('stages'));

            // Create a UserGroup associated with this role
            $userGroup = $this->newDataObject([
                'role_id' => $roleId,
                'context_id' => $contextId,
                'permit_self_registration' => $permitSelfRegistration,
                'permit_metadata_edit' => $permitMetadataEdit,
                'is_default' => true,
                'show_title' => true,
                'masthead' => $masthead,
            ]);

            // insert the user group into the DB
            $userGroupId = $this->add($userGroup);

            // Install default groups for each stage
            foreach ($defaultStages as $stageId) {
                $stageId = (int) trim($stageId);
                if ($stageId >= WORKFLOW_STAGE_ID_SUBMISSION && $stageId <= WORKFLOW_STAGE_ID_PRODUCTION) {
                    UserGroupStage::create([
                        'context_id' => $contextId,
                        'user_group_id' => $userGroupId,
                        'stage_id' => $stageId,
                    ]);
                }
            }

            // add the i18n keys to the settings table so that they
            // can be used when a new locale is added/reloaded
            $newUserGroup = $this->get($userGroupId, $contextId);
            $this->edit($newUserGroup, [
                'nameLocaleKey' => $nameKey,
                'abbrevLocaleKey' => $abbrevKey,
            ]);

            // Install the settings in the current locale for this context
            foreach ($installedLocales as $locale) {
                $this->installLocale($locale, $contextId);
            }
        }

        self::forgetEditorialMastheadCache($contextId);
        self::forgetEditorialHistoryCache($contextId);

        return true;
    }

    /**
     * use the locale keys stored in the settings table to install the locale settings
     *
     * @param string $locale
     * @param ?int $contextId
     * @return void
     */
    public function installLocale(string $locale, ?int $contextId = null): void
    {
        $userGroups = UserGroup::query();

        if ($contextId !== null) {
            $userGroups->withContextIds([$contextId]);
        }

        $userGroups = $userGroups->get();

        foreach ($userGroups as $userGroup) {
            $nameKey = $userGroup->settings['nameLocaleKey'] ?? null;
            $abbrevKey = $userGroup->settings['abbrevLocaleKey'] ?? null;

            if ($nameKey) {
                $userGroup->settings['name'] = __($nameKey, [], $locale);
            }

            if ($abbrevKey) {
                $userGroup->settings['abbrev'] = __($abbrevKey, [], $locale);
            }

            $userGroup->save();
        }
    }

    /**
     * Cache/get cached array of masthead user IDs grouped by masthead role IDs
     * Format: [user_group_id => [user_ids]]
     *
     * @param array $mastheadRoles Masthead roles, filtered by the given context ID, and sorted as they should appear on the Editorial Masthead and Editorial History page
     * @param int $contextId
     * @param UserUserGroupStatus $userUserGroupStatus
     * @return array
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
            $mastheadRolesIds = array_filter(array_map(function (UserGroup $item) use ($contextId) {
                return ($item->context_id === $contextId) ? $item->user_group_id : null;
            }, $mastheadRoles));

            // Query that gets all users that are or were active in the given masthead roles
            // and that have accepted to be displayed on the masthead for the roles.
            // Sort the results by role ID and user family name.
            $users = UserUserGroup::query()
                ->withContextId($contextId)
                ->withUserGroupIds($mastheadRolesIds)
                ->withUserUserGroupStatus($userUserGroupStatus->value)
                ->withUserUserGroupMastheadStatus(UserUserGroupMastheadStatus::STATUS_ON->value)
                ->orderBy('user_groups.role_id', 'asc')
                ->join('user_groups', 'user_user_groups.user_group_id', '=', 'user_groups.user_group_id')
                ->join('users', 'user_user_groups.user_id', '=', 'users.user_id')
                ->orderBy('users.family_name', 'asc')
                ->get(['user_groups.user_group_id', 'users.user_id']);

            // group unique user ids by UserGroup id
            $userIdsByUserGroupId = $users->groupBy('user_group_id')->map(function ($group) {
                return $group->pluck('user_id')->unique()->toArray();
            })->toArray();

            return $userIdsByUserGroupId;
        });
    }

    /**
     * Clear editorial masthead cache for a given context
     *
     * @param int $contextId
     * @return void
     */
    public static function forgetEditorialMastheadCache(int $contextId): void
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
     * @param int $contextId
     * @return void
     */
    public static function forgetEditorialHistoryCache(int $contextId): void
    {
        $cacheKey = "PKP\userGroup\Repository::getMastheadUserIdsByRoleIdsEditorialHistory{$contextId}" . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        Cache::forget($cacheKey);
    }
}