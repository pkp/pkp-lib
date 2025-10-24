<?php

/**
 * @file classes/user/maps/Schema.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map users to the properties defined in the user schema
 */

namespace PKP\user\maps;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;
use Illuminate\Support\Facades\DB;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_USER;

    /**
     * Map a user
     *
     * Includes all properties in the user schema.
     */
    public function map(User $item, array $auxiliaryData = []): array
    {
        return $this->mapByProperties($this->getProps(), $item, $auxiliaryData);
    }

    /**
     * Summarize a user
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarize(User $item, array $auxiliaryData = []): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item, $auxiliaryData);
    }

    /**
     * Summarize a user with reviewer data
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarizeReviewer(User $item, array $auxiliaryData = []): array
    {
        return $this->mapByProperties($this->getReviewerSummaryProps(), $item, $auxiliaryData);
    }

    /**
     * Map a collection of users with optional context
     * @see self::map
     */
    public function mapManyWithOptions(Enumerable $collection, array $options = []): Enumerable
    {
        return $this->mapUsersWithPermissions($collection, $this->getProps(), $options);
    }

    /**
     * Map a collection of Users
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        return $this->mapUsersWithPermissions($collection, $this->getProps(), []);
    }

    /**
     * Get the list of properties used for reviewer summaries
     */
    private function getReviewerSummaryProps(): array
    {
        return array_merge($this->getSummaryProps(), [
            'reviewsActive','reviewsCompleted','reviewsDeclined','reviewsCancelled',
            'averageReviewCompletionDays','dateLastReviewAssignment','reviewerRating',
        ]);
    }

    /**
     * Summarize a collection of users
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection, array $options = []): Enumerable
    {
        return $this->mapUsersWithPermissions($collection, $this->getSummaryProps(), $options);
    }

    /**
     * Summarize a collection of reviewers
     *
     * @see self::summarizeReviewer
     */
    public function summarizeManyReviewers(Enumerable $collection, array $options = []): Enumerable
    {
        return $this->mapUsersWithPermissions($collection, $this->getReviewerSummaryProps(), $options);
    }

    /**
     * Shared implementation for mapping/summarizing collections
     */
    private function mapUsersWithPermissions(Enumerable $collection, array $props, array $options): Enumerable
    {
        // handles lazy collection
        $users = collect($collection->all());

        $this->collection = $users;

        $userIds = $users
            ->map(fn (User $u) => (int) $u->getId())
            ->unique()
            ->values()
            ->all();

        $currentUserId = $options['currentUserId'] ?? null;
        $isSiteAdmin   = (bool)($options['isSiteAdmin'] ?? Validation::isSiteAdmin());
        $options['isSiteAdmin'] = $isSiteAdmin;

        if (empty($userIds)) {
            $options['permissionMap'] = [];
        } elseif ($isSiteAdmin) {
            $options['permissionMap'] = array_fill_keys($userIds, true);
        } elseif ($currentUserId !== null) {
            $options['permissionMap'] = $this->buildUserContextManagementMap((int)$currentUserId, $userIds);
        } else {
            $options['permissionMap'] = array_fill_keys($userIds, false);
        }

        return $users->map(fn (User $u) => $this->mapByProperties($props, $u, $options));
    }


    /**
     * build a permission map for a manager over a set of users
     */
    private function buildUserContextManagementMap(int $managerUserId, array $managedUserIds): array
    {
        if (empty($managedUserIds)) {
            return [];
        }

        $rows = DB::table('users as u')
            ->select('u.user_id')
            ->selectRaw(
                'CASE WHEN EXISTS (
                    SELECT 1
                    FROM user_user_groups uug_t
                    JOIN user_groups ug_t ON ug_t.user_group_id = uug_t.user_group_id
                    WHERE uug_t.user_id = u.user_id
                      AND NOT EXISTS (
                        SELECT 1
                        FROM user_groups ug_m
                        JOIN user_user_groups uug_m ON ug_m.user_group_id = uug_m.user_group_id
                        WHERE ug_m.role_id = ?
                          AND uug_m.user_id = ?
                          AND ug_m.context_id = ug_t.context_id
                      )
                )
                THEN 0 ELSE 1 END AS manages_all',
                [ (int) Role::ROLE_ID_MANAGER, (int) $managerUserId ]
            )
            ->whereIn('u.user_id', array_map(intval(...), $managedUserIds))
            ->get();

        $permissionMap = array_fill_keys(array_map(intval(...), $managedUserIds), false);

        foreach ($rows as $row) {
            $permissionMap[(int) $row->user_id] = (bool) ((int) $row->manages_all);
        }
        return $permissionMap;
    }

    /**
     * Map schema properties of a user to an assoc array
     *
     * @param array $auxiliaryData - Associative array used to provide supplementary data needed to populate properties on the response.
     *
     * @hook UserSchema::getProperties::values [[$this, &$output, $user, $props]]
     */
    protected function mapByProperties(array $props, User $user, array $auxiliaryData = []): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'id':
                    $output[$prop] = (int) $user->getId();
                    break;
                case 'fullName':
                    $output[$prop] = $user->getFullName();
                    break;
                case 'gossip':
                    if (Repo::user()->canCurrentUserGossip($user->getId())) {
                        $output[$prop] = $user->getGossip();
                    }
                    break;
                case 'canLoginAs':
                    $output[$prop] = $this->getPropertyCanLoginAs($user, $auxiliaryData);
                    break;
                case 'canMergeUsers':
                    $output[$prop] = $this->getPropertyCanMergeUsers($user, $auxiliaryData);
                    break;
                case 'reviewsActive':
                    $output[$prop] = $user->getData('incompleteCount');
                    break;
                case 'reviewsCompleted':
                    $output[$prop] = $user->getData('completeCount');
                    break;
                case 'reviewsDeclined':
                    $output[$prop] = $user->getData('declinedCount');
                    break;
                case 'reviewsCancelled':
                    $output[$prop] = $user->getData('cancelledCount');
                    break;
                case 'averageReviewCompletionDays':
                    $output[$prop] = $user->getData('averageTime');
                    break;
                case 'dateLastReviewAssignment':
                    $output[$prop] = $user->getData('lastAssigned');
                    break;
                case 'disabledReason':
                    $output[$prop] = $user->getDisabledReason();
                    break;
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'users/' . $user->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'groups':
                    $output[$prop] = null;
                    if ($this->context) {
                        // Fetch user groups where the user is assigned in the current context
                        $userGroups = UserGroup::query()
                            ->withContextIds($this->context->getId())
                            ->whereHas('userUserGroups', function ($query) use ($user) {
                                $query->withUserId($user->getId());
                            })
                            ->get();

                        $output[$prop] = [];
                        foreach ($userGroups as $userGroup) {
                            $userUserGroup = UserUserGroup::withUserId($user->getId())
                                ->withUserGroupIds([$userGroup->id])->get()->toArray();
                            foreach ($userUserGroup as $userUserGroupItem) {
                                $output[$prop][] = [
                                    'id' => (int) $userGroup->id,
                                    'name' => $userGroup->getLocalizedData('name'),
                                    'abbrev' => $userGroup->getLocalizedData('abbrev'),
                                    'roleId' => (int) $userGroup->roleId,
                                    'showTitle' => (bool) $userGroup->showTitle,
                                    'permitSelfRegistration' => (bool) $userGroup->permitSelfRegistration,
                                    'permitMetadataEdit' => (bool) $userGroup->permitMetadataEdit,
                                    'recommendOnly' => (bool) $userGroup->recommendOnly,
                                    'dateStart' => $userUserGroupItem['dateStart'],
                                    'dateEnd' => $userUserGroupItem['dateEnd'],
                                    'masthead' => $userUserGroupItem['masthead']
                                ];
                            }
                        }
                    }
                    break;
                case 'interests':
                    $output[$prop] = [];
                    if ($this->context) {
                        $interests = collect(Repo::userInterest()->getInterestsForUser($user))
                            ->map(fn ($value, $index) => ['id' => $index, 'interest' => $value])
                            ->values()
                            ->toArray();

                        foreach ($interests as $interest) {
                            $output[$prop][] = $interest;
                        }
                    }
                    break;
                case 'stageAssignments':
                    $submission = $auxiliaryData['submission'] ?? null;
                    $stageId = $auxiliaryData['stageId'] ?? null;

                    if (
                        !($submission instanceof Submission) ||
                        !is_numeric($stageId)
                    ) {
                        $output['stageAssignments'] = [];
                        break;
                    }

                    // Get User's stage assignments for submission.
                    $stageAssignments = StageAssignment::with(['userGroup'])
                        ->withSubmissionIds([$submission->getId()])
                        ->withStageIds([$stageId])
                        ->withUserId($user->getId())
                        ->withContextId($this->context->getId())
                        ->get();

                    $results = [];

                    foreach ($stageAssignments as $stageAssignment) {
                        $userGroup = $stageAssignment->userGroup;

                        // Only prepare data for non-reviewer participants
                        if ($userGroup && $userGroup->roleId !== Role::ROLE_ID_REVIEWER) {
                            $entry = [
                                'stageAssignmentId' => $stageAssignment->id,
                                'stageAssignmentUserGroup' => [
                                    'id' => (int) $userGroup->id,
                                    'name' => $userGroup->getLocalizedData('name'),
                                    'abbrev' => $userGroup->getLocalizedData('abbrev'),
                                    'roleId' => (int) $userGroup->roleId,
                                    'showTitle' => (bool) $userGroup->showTitle,
                                    'permitSelfRegistration' => (bool) $userGroup->permitSelfRegistration,
                                    'permitMetadataEdit' => (bool) $userGroup->permitMetadataEdit,
                                    'recommendOnly' => (bool) $userGroup->recommendOnly,
                                ],
                                'stageAssignmentStageId' => $stageId,
                                'recommendOnly' => (bool) $stageAssignment->recommendOnly,
                                'canChangeMetadata' => (bool) $stageAssignment->canChangeMetadata,
                            ];

                            $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
                            $entry['stageAssignmentStage'] = [
                                'id' => $stageId,
                                'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
                            ];

                            $results[] = $entry;
                        }
                    }

                    $output['stageAssignments'] = $results;
                    break;
                case 'displayInitials':
                    $output['displayInitials'] = $user->getDisplayInitials();
                    break;
                case 'orcidDisplayValue':
                    $output[$prop] = $user->getOrcidDisplayValue();
                    break;
                default:
                    $output[$prop] = $user->getData($prop);
                    break;
            }
        }
        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());

        Hook::call('UserSchema::getProperties::values', [$this, &$output, $user, $props]);

        ksort($output);

        return $output;
    }

    /**
     * Decide if the current user can "log in as" the target user
     */
    protected function getPropertyCanLoginAs(User $userToLoginAs, array $auxiliaryData = []): bool
    {
        $currentUserId = $auxiliaryData['currentUserId'] ?? null;
        if ($currentUserId === null || $currentUserId === (int)$userToLoginAs->getId()) {
            return false;
        }

        $isSiteAdmin = (bool)($auxiliaryData['isSiteAdmin'] ?? Validation::isSiteAdmin());
        if ($isSiteAdmin) {
            return true;
        }

        $permissionMap = $auxiliaryData['permissionMap']
            ?? $this->buildUserContextManagementMap($currentUserId, [(int)$userToLoginAs->getId()]);

        return (bool)($permissionMap[(int)$userToLoginAs->getId()] ?? false);
    }

    /**
     * Determine if the current user can merge the target user
     */
    protected function getPropertyCanMergeUsers(User $userToMerge, array $auxiliaryData = []): bool
    {
        $currentUserId = $auxiliaryData['currentUserId'] ?? null;
        if ($currentUserId === null || $currentUserId === (int)$userToMerge->getId()) {
            return false;
        }

        $isSiteAdmin = (bool)($auxiliaryData['isSiteAdmin'] ?? Validation::isSiteAdmin());
        if ($isSiteAdmin) {
            return true;
        }

        // allow merge if the manager manages all of that context.
        $permissionMap = $auxiliaryData['permissionMap']
            ?? $this->buildUserContextManagementMap($currentUserId, [(int)$userToMerge->getId()]);

        return (bool)($permissionMap[(int)$userToMerge->getId()] ?? false);
    }
}
