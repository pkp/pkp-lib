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

use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\site\SiteDAO;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\validation\ValidatorFactory;
use PKP\xml\PKPXMLParser;

class Repository
{
    /**
     * A list of roles not able to change submissionMetadataEdit permission option.
     */
    public const NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES = [Role::ROLE_ID_MANAGER];

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
    public function get(int $id, int $contextId = null): ?UserGroup
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
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
     */
    public function validate($userGroup, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

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

        return $userGroup->getId();
    }

    public function edit(UserGroup $userGroup, array $params)
    {
        $newUserGroup = Repo::userGroup()->newDataObject(array_merge($userGroup->_data, $params));

        Hook::call('UserGroup::edit', [$newUserGroup, $userGroup, $params]);

        $this->dao->update($newUserGroup);

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
    *
    * @param int $contextId
    */
    public function deleteByContextId($contextId)
    {
        $userGroupIds = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getIds();

        foreach ($userGroupIds as $userGroupId) {
            $this->dao->deleteById($userGroupId);
        }
    }

    /**
    * return all user group ids given a certain role id
    *
    * @param int $roleId
    * @param int|null $contextId
    */
    public function getArrayIdByRoleId($roleId, $contextId = null): array
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
    * Return all user groups ids for a user id
    * @return LazyCollection<int,UserGroup>
    */
    public function userUserGroups(int $userId, ?int $contextId = null): LazyCollection
    {
        $collector = Repo::userGroup()
            ->getCollector()
            ->filterByUserIds([$userId]);

        if ($contextId) {
            $collector->filterByContextIds([$contextId]);
        }

        return $collector->getMany();
    }

    /**
    * return whether a user is in a user group
    */
    public function userInGroup(int $userId, int $userGroupId): bool
    {
        return UserUserGroup::withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->get()
            ->isNotEmpty();
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

    public function assignUserToGroup(int $userId, int $userGroupId): UserUserGroup
    {
        return UserUserGroup::create([
            'userId' => $userId,
            'userGroupId' => $userGroupId
        ]);
    }

    public function removeUserFromGroup($userId, $userGroupId, $contextId): bool
    {
        return UserUserGroup::withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->withContextId($contextId)
            ->delete();
    }

    public function deleteAssignmentsByUserId(int $userId, ?int $userGroupId = null): bool
    {
        $query = UserUserGroup::withUserId($userId);

        if ($userGroupId) {
            $query->withUserGroupId($userGroupId);
        }

        return $query->delete();
    }

    public function deleteAssignmentsByContextId(int $contextId, ?int $userId = null): bool
    {
        $userUserGroups = UserUserGroup::withContextId($contextId);

        if ($userId) {
            $userUserGroups->withUserId($userId);
        }

        return $userUserGroups->delete();
    }

    /**
    * Get the user groups assigned to each stage.
    *
    * @param null|mixed $roleId
    * @param null|mixed $count
    *
    * @return LazyCollection<int,UserGroup>
    */
    public function getUserGroupsByStage($contextId, $stageId, $roleId = null, $count = null): LazyCollection
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
    public function installSettings($contextId, $filename)
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
}
