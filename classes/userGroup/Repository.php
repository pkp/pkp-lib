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
use stdClass;

class Repository
{
    /**
     * A list of roles not able to change submissionMetadataEdit permission option.
     */
    public const NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES = [Role::ROLE_ID_MANAGER];

    /** @var string Max lifetime for the Editorial Masthead and Editorial History users cache. */
    public const MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME = '1 year';

    /** @var DAO */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService<UserGroup> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): UserGroup
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?UserGroup
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
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

    public function add(UserGroup $userGroup): int
    {
        $userGroupId = $this->dao->insert($userGroup);
        $userGroup = Repo::userGroup()->get($userGroupId);

        Hook::call('UserGroup::add', [$userGroup]);

        // Clear editorial masthead cache if the new role should be added to the masthead
        // Because it is a new role, no need to clear editorial history chache
        if ($userGroup->getMasthead()) {
            self::forgetEditorialMastheadCache($userGroup->getContextId());
        }
        return $userGroup->getId();
    }

    public function edit(UserGroup $userGroup, array $params)
    {
        $newUserGroup = Repo::userGroup()->newDataObject(array_merge($userGroup->_data, $params));

        Hook::call('UserGroup::edit', [$newUserGroup, $userGroup, $params]);

        $this->dao->update($newUserGroup);

        // Clear editorial masthead and history cache if the role is on the masthead
        if ($userGroup->getMasthead()) {
            self::forgetEditorialMastheadCache($userGroup->getContextId());
            self::forgetEditorialHistoryCache($userGroup->getContextId());
        }

        Repo::userGroup()->get($newUserGroup->getId());
    }

    public function delete(UserGroup $userGroup)
    {
        Hook::call('UserGroup::delete::before', [$userGroup]);

        $this->dao->delete($userGroup);

        Hook::call('UserGroup::delete', [$userGroup]);
    }

    /**
    * Delete all user groups assigned to a certain context by contextId
    */
    public function deleteByContextId(int $contextId)
    {
        $userGroupIds = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getIds();

        foreach ($userGroupIds as $userGroupId) {
            $this->dao->deleteById($userGroupId);
        }
    }

    /**
    * Return all user group ids given a certain role id
    *
    * @param int $roleId
    */
    public function getArrayIdByRoleId($roleId, ?int $contextId = null): array
    {
        $collector = Repo::userGroup()->getCollector()
            ->filterByRoleIds([$roleId]);

        if ($contextId) {
            $collector->filterByContextIds([$contextId]);
        }

        return $collector->getIds()->toArray();
    }

    /**
    * Return all user group ids given a certain role id
    *
    * @param ?bool $default Give null for all user groups, else define whether it is default
    *
    * @return LazyCollection<int,UserGroup>
    */
    public function getByRoleIds(array $roleIds, int $contextId, ?bool $default = null): LazyCollection
    {
        $collector = Repo::userGroup()
            ->getCollector()
            ->filterByRoleIds($roleIds)
            ->filterByContextIds([$contextId])
            ->filterByIsDefault($default);

        return $collector->getMany();
    }

    /**
    * Return all, active or ended user groups ids for a user id
    *
    * @return LazyCollection<int,UserGroup>
    */
    public function userUserGroups(int $userId, ?int $contextId = null, ?UserUserGroupStatus $userUserGroupStatus = UserUserGroupStatus::STATUS_ACTIVE): LazyCollection
    {
        $collector = Repo::userGroup()
            ->getCollector()
            ->filterByUserIds([$userId])
            ->filterByUserUserGroupStatus($userUserGroupStatus);

        if ($contextId) {
            $collector->filterByContextIds([$contextId]);
        }

        return $collector->getMany();
    }

    /**
    * Return all context IDs for masthead user groups the given user is or was assigned to
    *
    * @return Collection of context IDs
    */
    public function getUserUserGroupsContextIds(int $userId): Collection
    {
        return Repo::userGroup()
            ->getCollector()
            ->filterByUserIds([$userId])
            ->filterByUserUserGroupStatus(UserUserGroupStatus::STATUS_ALL)
            ->filterByMasthead(true)
            ->getQueryBuilder()
            ->pluck('context_id')
            ->unique();
    }

    /**
    * return whether a user is in a user group
    */
    public function userInGroup(int $userId, int $userGroupId): bool
    {
        return UserUserGroup::withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->withActive()
            ->get()
            ->isNotEmpty();
    }

    /**
    * return whether an active user in a user group should be displayed on the masthead
    */
    public function userOnMasthead(int $userId, ?int $userGroupId): bool
    {
        if ($userGroupId) {
            $userGroup = Repo::userGroup()->get($userGroupId);
            if (!$userGroup->getMasthead()) {
                return false;
            }
        }
        $query = UserUserGroup::withUserId($userId)
            ->withActive()
            ->withMasthead();
        if ($userGroupId) {
            $query->withUserGroupId($userGroupId);
        }
        return $query->get()->isNotEmpty();
    }

    /**
     * Get user masthead status for a user group the user is currently active in
     */
    public function getUserUserGroupMastheadStatus(int $userId, int $userGroupId): UserUserGroupMastheadStatus
    {
        $masthead = UserUserGroup::withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->withActive()
            ->pluck('masthead');
        switch ($masthead[0]) {
            case 1:
                return UserUserGroupMastheadStatus::STATUS_ON;
            case 0:
                return UserUserGroupMastheadStatus::STATUS_OFF;
            case null:
                return UserUserGroupMastheadStatus::STATUS_NULL;
        }
    }

    /**
    * return whether a context has a specific user group
    */
    public function contextHasGroup(int $contextId, int $userGroupId): bool
    {
        return Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByUserGroupIds([$userGroupId])
            ->getCount() > 0;
    }

    /**
     * Assign a user to a role
     *
     * @param string|null $startDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     * @param string|null $endDate The date in ISO (YYYY-MM-DD HH:MM:SS) format
     */
    public function assignUserToGroup(int $userId, int $userGroupId, ?string $startDate = null, ?string $endDate = null, ?UserUserGroupMastheadStatus $mastheadStatus = null): ?UserUserGroup
    {
        if ($endDate && !Carbon::parse($endDate)->isFuture()) {
            return null;
        }

        $dateStart = $startDate ?? Core::getCurrentDate();
        $userGroup = Repo::userGroup()->get($userGroupId);
        // user_user_group's masthead does not inherit from the user_group's masthead,
        // it needs to be specified (when accepting an invitations).
        switch ($mastheadStatus) {
            case UserUserGroupMastheadStatus::STATUS_ON:
                $masthead = 1;
                break;
            case UserUserGroupMastheadStatus::STATUS_OFF:
                $masthead = 0;
                break;
            default:
                $masthead = $userGroup->getMasthead() ? 1 : null;
        }
        // Clear editorial masthead cache if a new user is assigned to a masthead role
        if ($userGroup->getMasthead()) {
            self::forgetEditorialMastheadCache($userGroup->getContextId());
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
     * Remove all user role assignments. This should be used only when merging i.e. fully deleting an user.
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

        $query = UserUserGroup::withUserId($userId);

        if ($userGroupId) {
            $query->withUserGroupId($userGroupId);
            $userGroup = $this->get($userGroupId);
            if ($userGroup->getMasthead()) {
                self::forgetEditorialMastheadCache($contextId);
                self::forgetEditorialHistoryCache($contextId);
            }
        }

        return $query->delete();
    }

    public function endAssignments(int $contextId, int $userId, ?int $userGroupId = null): void
    {
        // Clear editorial masthead and history cache if the user was displayed on the masthead for the given role
        if ($this->userOnMasthead($userId, $userGroupId)) {
            self::forgetEditorialMastheadCache($contextId);
            self::forgetEditorialHistoryCache($contextId);
        }

        $dateEnd = Core::getCurrentDate();
        $query = UserUserGroup::withContextId($contextId)
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
    * @param null|mixed $roleId
    * @param null|mixed $count
    *
    * @return LazyCollection<int,UserGroup>
    */
    public function getUserGroupsByStage(int $contextId, $stageId, $roleId = null, $count = null): LazyCollection
    {
        $userGroups = $this->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStageIds([$stageId]);

        if ($roleId) {
            $userGroups->filterByRoleIds([$roleId]);
        }

        $userGroups->orderBy(Collector::ORDERBY_ROLE_ID);

        return $userGroups->getMany();
    }

    /**
    * Remove a user group from a stage
    */
    public function removeGroupFromStage(int $contextId, int $userGroupId, int $stageId): bool
    {
        return UserGroupStage::withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->withStageId($stageId)
            ->delete();
    }

    /**
     * Get all stages assigned to one user group in one context.
     *
     * @param int $contextId The context ID.
     * @param int $userGroupId The user group ID
     *
     */
    public function getAssignedStagesByUserGroupId(int $contextId, int $userGroupId): Collection
    {
        return UserGroupStage::withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->pluck('stage_id');
    }

    /**
     * Retrieves a keyed Collection (key = user_group_id, value = count) with the amount of active users for each user group
     */
    public function getUserCountByContextId(?int $contextId = null): Collection
    {
        return $this->dao->getUserCountByContextId($contextId);
    }

    /**
     * Get the user group a new author may be assigned to
     * when they make their first submission, if they are
     * not already assigned to an author user group.
     *
     * This returns the first user group with ROLE_ID_AUTHOR
     * that permits self-registration.
     */
    public function getFirstSubmitAsAuthorUserGroup(int $contextId): ?UserGroup
    {
        return Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([Role::ROLE_ID_AUTHOR])
            ->filterByPermitSelfRegistration(true)
            ->limit(1)
            ->getMany()
            ->first();
    }

    /**
     * Load the XML file and move the settings to the DB
     *
     * @param int $contextId
     * @param string $filename
     *
     * @return bool true === success
     */
    public function installSettings(?int $contextId, $filename)
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
            $permitSelfRegistration = $setting->getAttribute('permitSelfRegistration');
            $permitMetadataEdit = $setting->getAttribute('permitMetadataEdit');
            $masthead = $setting->getAttribute('masthead');

            // If has manager role then permitMetadataEdit can't be overridden
            if (in_array($roleId, [Role::ROLE_ID_MANAGER])) {
                $permitMetadataEdit = $setting->getAttribute('permitMetadataEdit');
            }

            $defaultStages = explode(',', (string) $setting->getAttribute('stages'));

            // create a role associated with this user group
            $userGroup = $this->newDataObject();
            $userGroup->setRoleId($roleId);
            $userGroup->setContextId($contextId);
            $userGroup->setPermitSelfRegistration($permitSelfRegistration ?? false);
            $userGroup->setPermitMetadataEdit($permitMetadataEdit ?? false);
            $userGroup->setDefault(true);
            $userGroup->setShowTitle(true);
            $userGroup->setMasthead($masthead ?? false);

            // insert the group into the DB
            $userGroupId = $this->add($userGroup);

            // Install default groups for each stage
            if (is_array($defaultStages)) { // test for groups with no stage assignments
                foreach ($defaultStages as $stageId) {
                    if (!empty($stageId) && $stageId <= WORKFLOW_STAGE_ID_PRODUCTION && $stageId >= WORKFLOW_STAGE_ID_SUBMISSION) {
                        UserGroupStage::create([
                            'contextId' => $contextId,
                            'userGroupId' => $userGroupId,
                            'stageId' => $stageId
                        ]);
                    }
                }
            }

            // add the i18n keys to the settings table so that they
            // can be used when a new locale is added/reloaded
            $newUserGroup = $this->get($userGroupId);
            $this->edit($newUserGroup, [
                'nameLocaleKey' => $nameKey,
                'abbrevLocaleKey' => $abbrevKey
            ]);

            // install the settings in the current locale for this context
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
     */
    public function installLocale($locale, ?int $contextId = null)
    {
        $userGroupsCollector = $this->getCollector();

        if (isset($contextId)) {
            $userGroupsCollector->filterByContextIds([$contextId]);
        }

        $userGroups = $userGroupsCollector->getMany();

        foreach ($userGroups as $userGroup) {
            $nameKey = $userGroup->getData('nameLocaleKey');
            $userGroup->setData('name', __($nameKey, [], $locale), $locale);
            $abbrevKey = $userGroup->getData('abbrevLocaleKey');
            $userGroup->setData('abbrev', __($abbrevKey, [], $locale), $locale);

            $this->edit($userGroup, []);
        }
    }

    /**
     * Cache/get cached array of masthead user IDs grouped by masthead role IDs [user_group_id => [user_ids]]
     *
     * @param array $mastheadRoles Masthead roles, filtered by the given context ID, and sorted as they should appear on the Editorial Masthead and Editorial History page
     *
     */
    public function getMastheadUserIdsByRoleIds(array $mastheadRoles, int $contextId, UserUserGroupStatus $userUserGroupStatus = UserUserGroupStatus::STATUS_ACTIVE): array
    {
        $key = __METHOD__;
        switch ($userUserGroupStatus) {
            case UserUserGroupStatus::STATUS_ACTIVE:
                $key .= 'EditorialMasthead';
                break;
            case UserUserGroupStatus::STATUS_ENDED:
                $key .= 'EditorialHistory';
                break;
        }
        $key .= $contextId . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        $expiration = DateInterval::createFromDateString(static::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
        $allUsersIdsGroupedByUserGroupId = Cache::remember($key, $expiration, function () use ($mastheadRoles, $contextId, $userUserGroupStatus) {
            $mastheadRolesIds = array_map(
                function (UserGroup $item) use ($contextId) {
                    if ($item->getContextId() == $contextId) {
                        return $item->getId();
                    }
                },
                $mastheadRoles
            );
            // Query that gets all users that are or were active in the given masthead roles
            // and that have accepted to be displayed on the masthead for the roles.
            // Sort the results by role ID and user family name.
            $usersCollector = Repo::user()->getCollector();
            $usersQuery = $usersCollector
                ->filterByContextIds([$contextId])
                ->filterByUserGroupIds($mastheadRolesIds)
                ->filterByUserUserGroupStatus($userUserGroupStatus)
                ->filterByUserUserGroupMastheadStatus(UserUserGroupMastheadStatus::STATUS_ON)
                ->orderBy($usersCollector::ORDERBY_FAMILYNAME, $usersCollector::ORDER_DIR_ASC, [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()])
                ->orderByUserGroupIds($mastheadRolesIds)
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
        return $allUsersIdsGroupedByUserGroupId;
    }

    /**
     * Clear editorial masthead cache
     */
    public static function forgetEditorialMastheadCache(int $contextId)
    {
        Cache::forget('PKP\userGroup\Repository::getMastheadUserIdsByRoleIdsEditorialMasthead' . $contextId . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
    }

    /**
     * Clear editorial history cache
     */
    public static function forgetEditorialHistoryCache(int $contextId)
    {
        Cache::forget('PKP\userGroup\Repository::getMastheadUserIdsByRoleIdsEditorialHistory' . $contextId . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
    }

}
