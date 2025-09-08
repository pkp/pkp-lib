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

use APP\core\Application;
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

    // page scoped cache to check if current user manage all contexts of target user
    private array $managesAllMap = [];
    private ?int $currentUserId = null;

    /**
     * Map a publication
     *
     * Includes all properties in the user schema.
     */
    public function map(User $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a user
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarize(User $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Summarize a user with reviewer data
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarizeReviewer(User $item, array $auxiliaryData = []): array
    {
        return $this->mapByProperties(array_merge($this->getSummaryProps(), ['reviewsActive', 'reviewsCompleted', 'reviewsDeclined', 'reviewsCancelled', 'averageReviewCompletionDays', 'dateLastReviewAssignment', 'reviewerRating']), $item, $auxiliaryData);
    }

    /**
     * Map a collection of Users
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        return $this->mapCollectionWithPermissionCheck($collection, fn ($item) => $this->map($item));
    }

    /**
     * Summarize a collection of users
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        return $this->mapCollectionWithPermissionCheck($collection, fn ($item) => $this->summarize($item));
    }

    /**
     * Summarize a collection of reviewers
     *
     * @see self::summarizeReviewer
     */
    public function summarizeManyReviewers(Enumerable $collection): Enumerable
    {
        return $this->mapCollectionWithPermissionCheck($collection, fn ($item) => $this->summarizeReviewer($item));
    }

    // single entry point used by all "list of users" methods above
    private function mapCollectionWithPermissionCheck(Enumerable $collection, callable $perItem): Enumerable
    {
        $this->collection = $collection;
        $this->loadUserContextPermissions($collection); // one batched query per page
        return $collection->map($perItem);
    }

    // compute the page wide coverage map
    private function loadUserContextPermissions(Enumerable $collection): void
    {
        $request = Application::get()->getRequest();
        $currentUser = $request?->getUser();
        $this->currentUserId = $currentUser?->getId();

        $ids = $collection->map(fn ($u) => (int) $u->getId())->unique()->values()->all();

        if (empty($ids)) {
            $this->managesAllMap = [];
            return;
        }

        // if not logged in then no permissions
        if (!$this->currentUserId) {
            $this->managesAllMap = array_fill_keys($ids, false);
            return;
        }

        // site admin bypass
        if (Validation::isSiteAdmin()) {
            $this->managesAllMap = array_fill_keys($ids, true);
            return;
        }

        // compute results for this page
        $this->buildUserContextManagementMap($this->currentUserId, $ids);
    }

    // ensure cache has entries for ad hoc lookups
    private function loadUserContextMap(array $targetIds): void
    {
        $targetIds = array_values(array_unique(array_map('intval', $targetIds)));
        if (!$this->currentUserId || empty($targetIds)) {
            return;
        }

        // if admin then all targets are true
        if (Validation::isSiteAdmin()) {
            foreach ($targetIds as $tid) {
                $this->managesAllMap[$tid] = true;
            }
            return;
        }

        $known = array_keys($this->managesAllMap);
        $missing = array_values(array_diff($targetIds, $known));
        if (!empty($missing)) {
            $this->buildUserContextManagementMap($this->currentUserId, $missing);
        }
    }

    /**
     * extend $this->managesAllMap for the given manager and target ids
     */
    private function buildUserContextManagementMap(int $managerId, array $targetIds): void
    {
        if (empty($targetIds)) {
            return;
        }

        $rows = DB::table('users as u')
            ->select('u.user_id')
            ->selectRaw(
                // does target have any context the manager doesn't manage?
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
                [ (int) Role::ROLE_ID_MANAGER, (int) $managerId ]
            )
            ->whereIn('u.user_id', array_map('intval', $targetIds))
            ->get();

        foreach ($rows as $r) {
            $this->managesAllMap[(int) $r->user_id] = ((int) $r->manages_all) === 1;
        }
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
                    $output[$prop] = $this->getPropertyCanLoginAs($user);
                    break;
                case 'canMergeUsers':
                    $output[$prop] = $this->getPropertyCanMergeUsers($user);
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
    protected function getPropertyCanLoginAs(User $targetUser): bool
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        if (!$currentUser) {
            return false; // Not logged in
        }

        // Prevent logging in as self
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // site admin bypass
        if (Validation::isSiteAdmin()) {
            return true;
        }

        // ensure if cache is populated for this target
        if ($this->currentUserId !== (int) $currentUser->getId()) {
            $this->currentUserId = (int) $currentUser->getId();
        }
        $this->loadUserContextMap([(int) $targetUser->getId()]);

        return $this->managesAllMap[(int) $targetUser->getId()] ?? false;
    }

    /**
     * Determine if the current user can merge the target user
     */
    protected function getPropertyCanMergeUsers(User $targetUser): bool
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();

        // ensure a user is logged in
        if (!$currentUser) {
            return false;
        }

        // prevent merging oneself
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // keep behaviour strict to site admins only
        return Validation::isSiteAdmin();
    }
}
