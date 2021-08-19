<?php
/**
 * @file classes/user/Repository.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage users.
 */

namespace PKP\user;

use APP\core\Application;
use APP\i18n\AppLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;
use PKP\security\Role;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public $schemaMap = maps\Schema::class;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): User
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?User
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::getCount() */
    public function getCount(Collector $query): int
    {
        return $this->dao->getCount($query);
    }

    /** @copydoc DAO::getMany() */
    public function getMany(Collector $query): LazyCollection
    {
        return $this->dao->getMany($query);
    }

    /** @copydoc DAO::get() */
    public function getByUsername(string $username, bool $allowDisabled = true): ?User
    {
        return $this->dao->getByUsername($username, $allowDisabled);
    }

    /** @copydoc DAO::get() */
    public function getByEmail(string $email, bool $allowDisabled = true): ?User
    {
        return $this->dao->getByEmail($email, $allowDisabled);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping users to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /** @copydoc DAO::getIds() */
    public function getIds(Collector $query): Collection
    {
        return $this->dao->getIds($query);
    }

    /** @copydoc DAO::insert() */
    public function add(User $user): int
    {
        $id = $this->dao->insert($user);
        HookRegistry::call('User::add', [$user]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(User $user, array $params = [])
    {
        $newUser = clone $user;
        $newUser->setAllData(array_merge($newUser->_data, $params));

        HookRegistry::call('User::edit', [$newUser, $user, $params]);

        $this->dao->update($newUser);
    }

    /** @copydoc DAO::delete */
    public function delete(User $user)
    {
        HookRegistry::call('User::delete::before', [&$user]);

        $this->dao->delete($user);

        HookRegistry::call('User::delete', [&$user]);
    }

    /**
     * Can the current user view and edit the gossip field for a user
     *
     * @param $userId int The user who's gossip field should be accessed
     *
     * @return boolean
     */
    public function canCurrentUserGossip($userId)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        $currentUser = $request->getUser();

        // Logged out users can never view gossip fields
        if (!$currentUser) {
            return false;
        }

        // Users can never view their own gossip fields
        if ($currentUser->getId() === $userId) {
            return false;
        }

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        // Only reviewers have gossip fields
        if (!$roleDao->userHasRole($contextId, $userId, Role::ROLE_ID_REVIEWER)) {
            return false;
        }

        // Only admins, editors and subeditors can view gossip fields
        if (!$roleDao->userHasRole($contextId, $currentUser->getId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR])) {
            return false;
        }

        return true;
    }

    /**
     * Can this user access the requested workflow stage
     *
     * The user must have an assigned role in the specified stage or
     * be a manager or site admin that has no assigned role in the
     * submission.
     *
     * @param string $stageId One of the WORKFLOW_STAGE_ID_* contstants.
     * @param string $workflowType Accessing the editorial or author workflow? \PKPApplication::WORKFLOW_TYPE_*
     * @param array $userAccessibleStages User's assignments to the workflow stages. ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES
     * @param array $userRoles User's roles in the context
     *
     * @return Boolean
     */
    public function canUserAccessStage($stageId, $workflowType, $userAccessibleStages, $userRoles)
    {
        $workflowRoles = Application::get()->getWorkflowTypeRoles()[$workflowType];

        if (array_key_exists($stageId, $userAccessibleStages)
            && !empty(array_intersect($workflowRoles, $userAccessibleStages[$stageId]))) {
            return true;
        }
        if (empty($userAccessibleStages) && in_array(Role::ROLE_ID_MANAGER, $userRoles)) {
            return true;
        }
        return false;
    }

    /**
     * Check for roles that give access to the passed workflow stage.
     *
     * @param int $userId
     * @param int $contextId
     * @param Submission $submission
     * @param int $stageId
     *
     * @return array
     */
    public function getAccessibleStageRoles($userId, $contextId, &$submission, $stageId)
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $userId, $stageId);

        $accessibleStageRoles = [];

        // Assigned users have access based on their assignment
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        while ($stageAssignment = $stageAssignmentsResult->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $contextId);
            $accessibleStageRoles[] = $userGroup->getRoleId();
        }
        $accessibleStageRoles = array_unique($accessibleStageRoles);

        // If unassigned, only managers and admins have access
        if (empty($accessibleStageRoles)) {
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
            $userRoles = $roleDao->getByUserId($userId, $contextId);
            foreach ($userRoles as $userRole) {
                if (in_array($userRole->getId(), [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER])) {
                    $accessibleStageRoles[] = $userRole->getId();
                }
            }
            $accessibleStageRoles = array_unique($accessibleStageRoles);
        }

        return array_map('intval', $accessibleStageRoles);
    }

    /**
     * Retrieves a filtered user report instance
     *
     *		@option int contextId Context ID (required)
     *		@option int[] userGroupIds List of user groups (all groups by default)
     */
    public function getReport(array $args): Report
    {
        $dataSource = $this->getMany(
            $this->getCollector()
                ->filterByUserGroupids($args['userGroupIds'] ?? null)
                ->filterByContextIds($args['contextId'] ?? [])
        );
        $report = new Report($dataSource);

        HookRegistry::call('User::getReport', $report);

        return $report;
    }

    public function getRolesOverview($args = [])
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER);
        $collector = $this->getCollector()
            ->filterByContextIds(isset($args['contextId']) ? [$args['contextId']] : null)
            ->filterRegisteredBefore($args['registeredBefore'] ?? null)
            ->filterRegisteredAfter($args['registeredAfter'] ?? null);
        switch ($args['status'] ?? null) {
            case 'active': $collector->filterByStatus($collector::STATUS_ACTIVE); break;
            case 'disabled': $collector->filterByStatus($collector::STATUS_DISABLED); break;
        }
        $result = [
            [
                'id' => 'total',
                'name' => 'stats.allUsers',
                'value' => $this->dao->getCount($collector),
            ],
        ];

        $roleNames = Application::get()->getRoleNames();

        // Don't include the admin user if we are limiting the overview to one context
        if (!empty($args['contextId'])) {
            unset($roleNames[Role::ROLE_ID_SITE_ADMIN]);
        }

        foreach ($roleNames as $roleId => $roleName) {
            $result[] = [
                'id' => $roleId,
                'name' => $roleName,
                'value' => $this->dao->getCount($collector->filterByRoleIds([$roleId])),
            ];
        }

        return $result;
    }
}
