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
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\user\User;

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
            $options['permissionMap'] = Repo::user()->permissionMapForManager((int)$currentUserId, $userIds);
        } else {
            $options['permissionMap'] = array_fill_keys($userIds, false);
        }
        $contextId = $this->context ? (int) $this->context->getId() : 0;
        $options['canSeeGossip'] = $currentUserId ? Repo::user()->canSeeGossip((int)$currentUserId, $contextId) : false;

        // batch preload
        $options['groupsByUser'] = (!empty($userIds) && $contextId) ? Repo::user()->preloadGroups($userIds, $contextId) : [];
        $options['interestsByUser'] = !empty($userIds) ? Repo::user()->preloadInterests($userIds) : [];

        // batch preload stage assignments, if submission+stage are provided
        if (!empty($userIds)
            && isset($options['submission'], $options['stageId'])
            && $options['submission'] instanceof Submission
            && is_numeric($options['stageId'])
        ) {
            $options['stageAssignmentsByUser'] = Repo::user()->stageAssignmentsForUsers(
                $userIds,
                (int)$options['submission']->getId(),
                (int)$options['stageId'],
                $contextId
            );
        } else {
            $options['stageAssignmentsByUser'] = [];
        }

        return $users->map(fn (User $u) => $this->mapByProperties($props, $u, $options));
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
                    $output[$prop] = array_values($interestsByUser[(int)$user->getId()] ?? []);
                    break;
                case 'stageAssignments':
                    $submission = $auxiliaryData['submission'] ?? null;
                    $stageId = $auxiliaryData['stageId'] ?? null;
                    $byUser = $auxiliaryData['stageAssignmentsByUser'] ?? [];

                    if (!($submission instanceof Submission) || !is_numeric($stageId)) {
                        $output['stageAssignments'] = [];
                        break;
                    }

                    // use preloaded map no per-user queries
                    $output['stageAssignments'] = $byUser[(int)$user->getId()] ?? [];
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
