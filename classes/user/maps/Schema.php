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
use PKP\facades\Locale;


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
        $contextId = $this->context ? (int) $this->context->getId() : 0;
        $options['canSeeGossip'] = $this->canSeeGossipForContext($currentUserId, $contextId);

        // batch preload
        $interestsByUser = [];

        if (!empty($userIds)) {
            if ($contextId) {
                $groupsByUser = $this->preloadGroups($userIds, $contextId);
            }
            $interestsByUser = $this->preloadInterests($userIds);
        }

        $options['groupsByUser'] = $groupsByUser;
        $options['interestsByUser'] = $interestsByUser;

        return $users->map(fn (User $u) => $this->mapByProperties($props, $u, $options));
    }


    /**
     * build a permission map for a manager over a set of users
     */
    private function buildUserContextManagementMap(int $managerUserId, array $managedUserIds): array
    {
        // normalize once
        $managedUserIds = array_values(array_unique(array_map('intval', $managedUserIds)));
        if (empty($managedUserIds)) {
            return [];
        }

        // Users for whom there exists at least one context they're active in that the manager doesn't manage
        $unmanagedUserIds = DB::table('users as u')
            ->whereIn('u.user_id', $managedUserIds)
            ->whereExists(function ($q) use ($managerUserId) {
                $q->from('user_user_groups as uug_t')
                ->join('user_groups as ug_t', 'ug_t.user_group_id', '=', 'uug_t.user_group_id')
                ->whereColumn('uug_t.user_id', 'u.user_id')
                ->whereNotExists(function ($qq) use ($managerUserId) {
                    $qq->from('user_groups as ug_m')
                        ->join('user_user_groups as uug_m', 'ug_m.user_group_id', '=', 'uug_m.user_group_id')
                        ->where('ug_m.role_id', Role::ROLE_ID_MANAGER)
                        ->where('uug_m.user_id', $managerUserId)
                        // explicit context match
                        ->whereColumn('ug_m.context_id', 'ug_t.context_id');
                });
            })
            ->pluck('u.user_id')
            ->all();

        $permissionMap = array_fill_keys($managedUserIds, true);
        foreach ($unmanagedUserIds as $badId) {
            $permissionMap[(int) $badId] = false;
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
                    if (!empty($auxiliaryData['canSeeGossip'])) {
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
                    $groupsByUser = $auxiliaryData['groupsByUser'] ?? [];
                    $output[$prop] = $groupsByUser[(int)$user->getId()] ?? [];
                    break;

                case 'interests':
                    $interestsByUser = $auxiliaryData['interestsByUser'] ?? [];
                    $output[$prop] = $interestsByUser[(int)$user->getId()] ?? [];
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
     * Check if a user can view gossip notes for a context.
     * Site admins or users in Manager/Sub-editor groups for the given context
     * (or site-wide groups) are allowed.
     */
    private function canSeeGossipForContext(?int $currentUserId, int $contextId): bool
    {
        if ($currentUserId === null) {
            return false;
        }
        if (Validation::isSiteAdmin()) {
            return true;
        }
        // Managers/sub-editors in this context can see gossip
        return DB::table('user_groups as ug')
            ->join('user_user_groups as uug', 'ug.user_group_id', '=', 'uug.user_group_id')
            ->where('uug.user_id', $currentUserId)
            ->whereIn('ug.role_id', [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->where(function ($q) use ($contextId) {
                // context-specific or site-wide groups
                $q->where('ug.context_id', $contextId)
                ->orWhereNull('ug.context_id');
            })
            ->exists();
    }

   /**
     * Preload user groups for a set of users, scoped to a context.
     * Returns: [userId => [ ...group payload... ], ...]
     */
    private function preloadGroups(array $userIds, int $contextId): array
    {
        $locale = Locale::getLocale();

        $rows = UserUserGroup::query()
            ->withContextId($contextId)
            ->whereIn('user_user_groups.user_id', $userIds)
            ->join('user_groups as ug', 'ug.user_group_id', '=', 'user_user_groups.user_group_id')
            // bind the setting name and a single locale to guarantee 0/1 row
            ->leftJoin('user_group_settings as ugs_name', function ($j) use ($locale) {
                $j->on('ugs_name.user_group_id', '=', 'ug.user_group_id')
                ->where('ugs_name.setting_name', '=', 'name')
                ->where('ugs_name.locale', '=', $locale);
            })
            ->leftJoin('user_group_settings as ugs_abbrev', function ($j) use ($locale) {
                $j->on('ugs_abbrev.user_group_id', '=', 'ug.user_group_id')
                ->where('ugs_abbrev.setting_name', '=', 'abbrev')
                ->where('ugs_abbrev.locale', '=', $locale);
            })
            ->get([
                'user_user_groups.user_id',
                'ug.user_group_id as id',
                'ug.role_id as roleId',
                'ug.show_title as showTitle',
                'ug.permit_self_registration as permitSelfRegistration',
                'ug.permit_metadata_edit as permitMetadataEdit',
                'user_user_groups.date_start as dateStart',
                'user_user_groups.date_end as dateEnd',
                'user_user_groups.masthead as userMasthead',
                'ugs_name.setting_value as name',
                'ugs_abbrev.setting_value as abbrev',
            ]);

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->user_id][] = [
                'id' => (int)$r->id,
                'name' => $r->name,
                'abbrev' => $r->abbrev,
                'roleId' => (int)$r->roleId,
                'showTitle' => (bool)((int)$r->showTitle),
                'permitSelfRegistration' => (bool)((int)$r->permitSelfRegistration),
                'permitMetadataEdit' => (bool)((int)$r->permitMetadataEdit),
                'dateStart' => $r->dateStart,
                'dateEnd' => $r->dateEnd,
                'masthead' => (bool)((int)$r->userMasthead),
            ];
        }

        return $map;
    }

    /**
     * Preload interests for a set of users (ids only).
     */
    private function preloadInterests(array $userIds): array
    {
        $rows = DB::table('user_interests as ui')
            ->whereIn('ui.user_id', $userIds)
            ->get([
                'ui.user_id',
                'ui.controlled_vocab_entry_id as id',
            ]);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->user_id][] = [
                'id' => (int) $r->id,
            ];
        }

        return $map;
    }
    
    /**
     * Decide if the current user can "log in as" the target user
     */
    protected function getPropertyCanLoginAs(User $userToLoginAs, array $auxiliaryData = []): bool
    {
        $currentUserId = $auxiliaryData['currentUserId'] ?? null;
        if ($currentUserId === null || $currentUserId === (int) $userToLoginAs->getId()) {
            return false;
        }

        $isSiteAdmin = (bool) ($auxiliaryData['isSiteAdmin'] ?? Validation::isSiteAdmin());
        if ($isSiteAdmin) {
            return true;
        }

        // use only the batched map
        $permissionMap = $auxiliaryData['permissionMap'] ?? null;
        if ($permissionMap === null) {
            return false;
        }

        return (bool) ($permissionMap[(int) $userToLoginAs->getId()] ?? false);
    }

    /**
     * Determine if the current user can merge the target user
     */
    protected function getPropertyCanMergeUsers(User $userToMerge, array $auxiliaryData = []): bool
    {
        $currentUserId = $auxiliaryData['currentUserId'] ?? null;
        if ($currentUserId === null || $currentUserId === (int) $userToMerge->getId()) {
            return false;
        }

        $isSiteAdmin = (bool) ($auxiliaryData['isSiteAdmin'] ?? Validation::isSiteAdmin());
        if ($isSiteAdmin) {
            return true;
        }

        // use only the batched map
        $permissionMap = $auxiliaryData['permissionMap'] ?? null;
        if ($permissionMap === null) {
            return false;
        }
        return (bool) ($permissionMap[(int) $userToMerge->getId()] ?? false);
    }
}
