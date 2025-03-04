<?php

/**
 * @file classes/user/Repository.php
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
use APP\facades\Repo;
use APP\submission\Submission;
use Carbon\Carbon;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\context\SubEditorsDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileDAO;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\SubmissionCommentDAO;
use PKP\userGroup\relationships\UserUserGroup;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
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
    public function get(int $id, $allowDisabled = false): ?User
    {
        return $this->dao->get($id, $allowDisabled);
    }

    /**
     * Retrieve a user by API key.
     */
    public function getByApiKey(string $apiKey): ?User
    {
        return $this->getCollector()
            ->filterBySettings(['apiKey' => $apiKey])
            ->getMany()
            ->first();
    }

    /** @copydoc DAO::get() */
    public function getByUsername(string $username, bool $allowDisabled = false): ?User
    {
        return $this->dao->getByUsername($username, $allowDisabled);
    }

    /** @copydoc DAO::get() */
    public function getByEmail(string $email, bool $allowDisabled = false): ?User
    {
        return $this->dao->getByEmail($email, $allowDisabled);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping users to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /** @copydoc DAO::insert() */
    public function add(User $user): int
    {
        $id = $this->dao->insert($user);
        Hook::call('User::add', [$user]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(User $user, array $params = [])
    {
        $newUser = clone $user;
        $newUser->setAllData(array_merge($newUser->_data, $params));

        Hook::call('User::edit', [$newUser, $user, $params]);

        $this->dao->update($newUser);
    }

    /** @copydoc DAO::delete */
    public function delete(User $user)
    {
        Hook::call('User::delete::before', [&$user]);

        $this->dao->delete($user);

        Hook::call('User::delete', [&$user]);
    }

    /**
     * Can the current user view and edit the gossip field for a user
     *
     * @param int $userId The user who's gossip field should be accessed
     *
     * @return bool
     */
    public function canCurrentUserGossip(int $userId)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::SITE_CONTEXT_ID;
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
     * @param string $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param string $workflowType Accessing the editorial or author workflow? PKPApplication::WORKFLOW_TYPE_*
     * @param array $userAccessibleStages User's assignments to the workflow stages. Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES
     * @param array $userRoles User's roles in the context
     *
     * @return bool
     */
    public function canUserAccessStage($stageId, $workflowType, $userAccessibleStages, $userRoles)
    {
        $workflowRoles = Application::get()->getWorkflowTypeRoles()[$workflowType];

        if (array_key_exists($stageId, $userAccessibleStages)
            && !empty(array_intersect($workflowRoles, $userAccessibleStages[$stageId]))) {
            return true;
        }
        if (empty($userAccessibleStages) && count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve user roles which give access to (certain) submission workflow stages
     * returns [
     *   stage ID => [role IDs]
     * ]
     *
     */
    public function getAccessibleWorkflowStages(int $userId, int $contextId, Submission $submission, ?array $userRoleIds = null): array
    {
        if (is_null($userRoleIds)) {
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
            $userRoles = $roleDao->getByUserIdGroupedByContext($userId);

            $userRoleIds = [];
            if (array_key_exists($contextId, $userRoles)) {
                $contextRoles = $userRoles[$contextId];

                foreach ($contextRoles as $contextRole) { /** @var Role $userRole */
                    $userRoleIds[] = $contextRole->getRoleId();
                }
            }

            // Has admin role?
            if ($contextId != PKPApplication::SITE_CONTEXT_ID &&
                array_key_exists(PKPApplication::SITE_CONTEXT_ID, $userRoles) &&
                in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles[PKPApplication::SITE_CONTEXT_ID])
            ) {
                $userRoleIds[] = Role::ROLE_ID_SITE_ADMIN;
            }
        }

        $accessibleWorkflowStages = [];
        // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
        $stageAssignments = StageAssignment::with(['userGroup.userGroupStages'])
            ->withSubmissionIds([$submission->getId()])
            ->withUserId($userId)
            ->get();

        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = $stageAssignment->userGroup;
            $roleId = $userGroup->roleId;

            // Check global user roles within the context, e.g., user can be assigned in the role, which was revoked
            if (!in_array($roleId, $userRoleIds)) {
                continue;
            }

            $stageAssignment->userGroupStages->each(function ($userGroupStage) use (&$accessibleWorkflowStages, $roleId) {
                $accessibleWorkflowStages[$userGroupStage->stageId][] = $roleId;
            });
        }

        // Managers and admin have access if not assigned to the submission or are assigned in a revoked role
        $managerRoles = array_intersect($userRoleIds, [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER]);
        if (empty($accessibleWorkflowStages) && !empty($managerRoles)) {
            $workflowStages = Application::getApplicationStages();
            foreach ($workflowStages as $stageId) {
                $accessibleWorkflowStages[$stageId] = $managerRoles;
            }
        }

        return $accessibleWorkflowStages;
    }

    /**
     * Retrieves a filtered user report instance
     *
     * @param array $args
     * - @option int[] contextIds Context IDs (required)
     * - @option int[] userGroupIds List of user groups (all groups by default)
     *
     * @hook User::getReport [[$report]]
     */
    public function getReport(array $args): Report
    {
        $dataSource = $this->getCollector()
            ->filterByUserGroupIds($args['userGroupIds'] ?? null)
            ->filterByContextIds($args['contextIds'] ?? [])
            ->getMany();

        $report = new Report($dataSource);

        Hook::call('User::getReport', [$report]);

        return $report;
    }

    public function getRolesOverview(Collector $collector)
    {
        $result = [
            [
                'id' => 'total',
                'name' => 'stats.allUsers',
                'value' => $this->dao->getCount($collector),
            ],
        ];

        $roleNames = Application::get()->getRoleNames();

        foreach ($roleNames as $roleId => $roleName) {
            $result[] = [
                'id' => $roleId,
                'name' => $roleName,
                'value' => $this->dao->getCount($collector->filterByRoleIds([$roleId])),
            ];
        }

        return $result;
    }

    /**
     * Merge user accounts and delete the old user account.
     *
     * @param int $oldUserId The user ID to remove
     * @param int $newUserId The user ID to receive all "assets" (i.e. submissions) from old user
     *
     * @hook UserAction::mergeUsers [[&$oldUserId, &$newUserId]]
     */
    public function mergeUsers(int $oldUserId, int $newUserId)
    {
        // Need both user ids for merge
        if (empty($oldUserId) || empty($newUserId)) {
            return false;
        }

        Hook::call('UserAction::mergeUsers', [&$oldUserId, &$newUserId]);

        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByUploaderUserIds([$oldUserId])
            ->includeDependentFiles()
            ->getMany();

        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->edit($submissionFile, ['uploaderUserId' => $newUserId]);
        }

        Repo::note()->transfer($oldUserId, $newUserId);

        Repo::decision()->dao->reassignDecisions($oldUserId, $newUserId);

        $reviewAssignments = Repo::reviewAssignment()->getCollector()->filterByReviewerIds([$oldUserId])->getMany();
        foreach ($reviewAssignments as $reviewAssignment) {
            Repo::reviewAssignment()->edit($reviewAssignment, ['reviewerId' => $newUserId]);
        }

        Repo::emailLogEntry()->changeUser($oldUserId, $newUserId);
        Repo::eventLog()->dao->changeUser($oldUserId, $newUserId);

        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionComments = $submissionCommentDao->getByUserId($oldUserId);

        while ($submissionComment = $submissionComments->next()) { /** @var \PKP\submission\SubmissionComment $submissionComment */
            $submissionComment->setAuthorId($newUserId);
            $submissionCommentDao->updateObject($submissionComment);
        }

        Repo::notification()->transfer($oldUserId, $newUserId);

        // Delete the old user and associated info.
        Application::get()->getRequest()->getSessionGuard()->invalidateOtherSessions($oldUserId);

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFileDao->deleteByUserId($oldUserId);
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteByUserId($oldUserId);

        // Transfer old user's roles
        $userUserGroups = UserUserGroup::query()
            ->withUserId($oldUserId)
            ->get();

        // Transfer assignments to the new user
        foreach ($userUserGroups as $userUserGroup) {
            // Check if the new user is already assigned to this user group
            $exists = UserUserGroup::query()
                ->withUserId($newUserId)
                ->withUserGroupIds([$userUserGroup->userGroupId])
                ->exists();

            if (!$exists) {
                UserUserGroup::create([
                    'userId' => $newUserId,
                    'userGroupId' => $userUserGroup->userGroupId,
                    'dateStart' => $userUserGroup->dateStart,
                    'dateEnd' => $userUserGroup->dateEnd,
                    'masthead' => $userUserGroup->masthead,
                ]);
            }
        }

        // Delete all user group assignments for the old user
        UserUserGroup::query()
            ->withUserId($oldUserId)
            ->delete();

        // Transfer stage assignments.
        $stageAssignments = StageAssignment::withUserId($oldUserId)->get();
        foreach ($stageAssignments as $stageAssignment) {
            // Replaces StageAssignmentDAO::getBySubmissionAndStageId
            $duplicateAssignments = StageAssignment::withSubmissionIds([$stageAssignment->submissionId])
                ->withUserGroupId($stageAssignment->userGroupId)
                ->withUserId($newUserId)
                ->get();

            if ($duplicateAssignments->isEmpty()) {
                // If no similar assignments already exist, transfer this one.
                $stageAssignment->userId = $newUserId;
                $stageAssignment->save();
            } else {
                // There's already a stage assignment for the new user; delete.
                $stageAssignment->delete();
            }
        }

        $this->delete($this->get($oldUserId, true));

        return true;
    }

    /**
     * Create a user object from the Context contact details
     */
    public function getUserFromContextContact(Context $context): User
    {
        $contextUser = $this->newDataObject();
        $supportedLocales = $context->getSupportedFormLocales();
        $contextUser->setData('email', $context->getData('contactEmail'));
        $contextUser->setData('givenName', array_fill_keys($supportedLocales, $context->getData('contactName')));
        return $contextUser;
    }

    /**
     * Delete unvalidated expired users
     *
     * @param Carbon $dateTillValid The dateTime till before which user will consider expired
     * @param array $excludableUsersId  The users id to exclude form delete operation
     *
     * @return int The number rows affected by DB operation
     */
    public function deleteUnvalidatedExpiredUsers(Carbon $dateTillValid, array $excludableUsersId = [])
    {
        return $this->dao->deleteUnvalidatedExpiredUsers($dateTillValid, $excludableUsersId);
    }

    /** Get admin users */
    public function getAdminUsers(): LazyCollection
    {
        return $this->dao->getAdminUsers();
    }
}
