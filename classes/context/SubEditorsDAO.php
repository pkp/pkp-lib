<?php

/**
 * @file classes/context/SubEditorsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubEditorsDAO
 *
 * @ingroup context
 *
 * @brief Base class associating sections, series and categories to sub editors.
 */

namespace PKP\context;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\EditorAssigned;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;

class SubEditorsDAO extends \PKP\db\DAO
{
    /**
     * Insert a new sub editor.
     *
     * @param int $assocId
     * @param int $userId
     */
    public function insertEditor(int $contextId, $assocId, $userId, $assocType, int $userGroupId)
    {
        return $this->update(
            'INSERT INTO subeditor_submission_group
				(context_id, assoc_id, user_id, assoc_type, user_group_id)
				VALUES
				(?, ?, ?, ?, ?)',
            [
                $contextId,
                (int) $assocId,
                (int) $userId,
                (int) $assocType,
                $userGroupId,
            ]
        );
    }

    /**
     * Delete a sub editor.
     *
     * @param int $assocId
     * @param int $userId
     * @param int $assocType Application::ASSOC_TYPE_SECTION or Application::ASSOC_TYPE_CATEGORY
     */
    public function deleteEditor(int $contextId, $assocId, $userId, $assocType)
    {
        $this->update(
            'DELETE FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_type = ?',
            [
                $contextId,
                (int) $assocId,
                (int) $userId,
                (int) $assocType,
            ]
        );
    }

    /**
     * Retrieve a list of all sub editors assigned to the specified submission group.
     *
     * @param int[] $assocIds Section or category ids
     * @param int $assocType Application::ASSOC_TYPE_SECTION or Application::ASSOC_TYPE_CATEGORY
     * @param bool $allowDisabled If true, include disabled users in the results. By default, only enabled users are included.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass> result rows with userId and userGroupId properties
     */
    public function getBySubmissionGroupIds(array $assocIds, int $assocType, int $contextId, bool $allowDisabled = false): Collection
    {
        return DB::table('subeditor_submission_group')
            ->where('subeditor_submission_group.assoc_type', '=', $assocType)
            ->where('subeditor_submission_group.context_id', '=', $contextId)
            ->whereIn('subeditor_submission_group.assoc_id', $assocIds)
            ->when(!$allowDisabled, function ($query) {
                return $query->join('users', 'subeditor_submission_group.user_id', '=', 'users.user_id')
                    ->where('users.disabled', '=', false);
            })->get([
                'subeditor_submission_group.user_id as userId',
                'subeditor_submission_group.user_group_id as userGroupId'
            ]);
    }

    /**
     * Delete all sub editors for a specified submission group in a context.
     *
     * @param int $assocId
     * @param int $assocType Application::ASSOC_TYPE_SECTION or Application::ASSOC_TYPE_CATEGORY
     */
    public function deleteBySubmissionGroupId($assocId, $assocType, ?int $contextId = null)
    {
        $params = [(int) $assocId, (int) $assocType];
        if ($contextId) {
            $params[] = (int) $contextId;
        }
        $this->update(
            'DELETE FROM subeditor_submission_group WHERE assoc_id = ? AND assoc_type = ?' .
            ($contextId ? ' AND context_id = ?' : ''),
            $params
        );
    }

    /**
     * Delete all submission group assignments for the specified user.
     */
    public function deleteByUserId(int $userId)
    {
        /**
         * This warning was added in 3.4 due to a change in the function signature.
         * It can be removed with the next LTS release.
         *
         * @deprecated 3.4
         */
        if (func_num_args() !== 1) {
            throw new Exception('Invalid number of arguments passed to ' . self::class . '::' . __FUNCTION__);
        }

        DB::table('subeditor_submission_group')
            ->where('user_id', '=', $userId)
            ->delete();
    }

    /**
     * Delete all submission group assignments for a user group
     */
    public function deleteByUserGroupId(int $userGroupId)
    {
        DB::table('subeditor_submission_group')
            ->where('user_group_id', '=', $userGroupId)
            ->delete();
    }

    /**
     * Check if a user is assigned to a specified submission group.
     *
     * @param int $assocId
     * @param int $userId
     * @param int $assocType optional Application::ASSOC_TYPE_SECTION or Application::ASSOC_TYPE_CATEGORY
     *
     * @return bool
     */
    public function editorExists(int $contextId, $assocId, $userId, $assocType)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_id = ?',
            [$contextId, (int) $assocId, (int) $userId, (int) $assocType]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Assign editors to a submission
     *
     * Creates a stage assignment for each editorial user
     * configured in the section and category settings.
     *
     * @return Collection The user ids for editors that were assigned
     */
    public function assignEditors(Submission $submission, Context $context): Collection
    {
        $publication = $submission->getCurrentPublication();
        $sectionIdPropName = Application::getSectionIdPropName();

        $assignments = $this->getBySubmissionGroupIds(
            [$publication->getData($sectionIdPropName)],
            Application::ASSOC_TYPE_SECTION,
            $submission->getData('contextId')
        );

        if (!empty($publication->getData('categoryIds'))) {
            $assignedToCategory = $this->getBySubmissionGroupIds(
                $publication->getData('categoryIds'),
                Application::ASSOC_TYPE_CATEGORY,
                $submission->getData('contextId')
            );
            $assignments = $assignments->merge($assignedToCategory);
        }

        // Remove duplicate assignments for the same user in the
        // same user group by structuring the array with a key
        // that will cause duplicates to be overwritten
        $assignments = collect($assignments)->mapWithKeys(fn ($assignment, $key) => [$assignment->userId . '-' . $assignment->userGroupId => $assignment]);

        $userGroups = UserGroup::query()
            ->withContextIds([$submission->getData('contextId')])
            ->get();

        $userGroupIds = $userGroups->keys();

        $assignments = $assignments->filter(function ($assignment) use ($userGroupIds) {
            return Repo::userGroup()->userInGroup($assignment->userId, $assignment->userGroupId)
                && $userGroupIds->contains($assignment->userGroupId);
        });

        foreach ($assignments as $assignment) {
            $userGroup = $userGroups->first(fn (UserGroup $userGroup) => $userGroup->id == $assignment->userGroupId);
            Repo::stageAssignment()
                ->build(
                    $submission->getId(),
                    $assignment->userGroupId,
                    $assignment->userId,
                    $userGroup->recommendOnly,
                );
        }

        // Update assignment notifications
        $notificationManager = new NotificationManager();
        $notificationManager->updateNotification(
            Application::get()->getRequest(),
            $notificationManager->getDecisionStageNotifications(),
            null,
            Application::ASSOC_TYPE_SUBMISSION,
            $submission->getId()
        );

        // Send a notification to assigned users
        foreach ($assignments as $assignment) {
            $notificationManager->createNotification(
                $assignment->userId,
                Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                $submission->getData('contextId'),
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );
        }

        // Send an email to assigned editors
        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $editorAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
            ->get();

        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), EditorAssigned::getEmailTemplateKey());
        if ($editorAssignments->isNotEmpty() && $emailTemplate) {
            // Never notify the same user twice, even if they are assigned in multiple roles
            $notifiedEditors = [];

            /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
            $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
            $mailable = new EditorAssigned($context, $submission);
            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->subject($emailTemplate->getLocalizedData('subject') ?? '')
                ->body($emailTemplate->getLocalizedData('body') ?? '');

            foreach ($editorAssignments as $editorAssignment) {
                $unsubscribed = in_array(
                    Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                    $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                        NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                        $editorAssignment->userId,
                        $context->getId()
                    )
                );

                if ($unsubscribed || in_array($editorAssignment->userId, $notifiedEditors)) {
                    continue;
                }

                $notifiedEditors[] = $editorAssignment->userId;

                $recipient = Repo::user()->get($editorAssignment->userId);
                $mailable->recipients([$recipient]);

                Mail::send($mailable);
                Repo::emailLogEntry()->logMailable(
                    SubmissionEmailLogEventType::EDITOR_ASSIGN,
                    $mailable,
                    $submission
                );
            }
        }

        return $assignments->map(fn ($assignment) => $assignment->userId);
    }

    /**
     * Get the section assigned sub editor's associated user groups ids for given section
     *
     * @return  Collection Collection A list of user group IDs for each user, keyed by user ID.
     */
    public function getAssignedUserGroupIds(int $contextId, int $assocType, int $assocId, int|array $userIds): Collection
    {
        return DB::table('subeditor_submission_group')
            ->select(['user_id', 'user_group_id'])
            ->where('assoc_type', $assocType)
            ->where('context_id', $contextId)
            ->where('assoc_id', $assocId)
            ->whereIn('user_id', Arr::wrap($userIds))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($userGroups) => $userGroups->pluck('user_group_id'));
    }
}
