<?php

/**
 * @file classes/user/PKPUserAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserAction
 * @ingroup user
 *
 * @see User
 *
 * @brief PKPUserAction class.
 */

namespace PKP\user;

use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;

class PKPUserAction
{
    /**
     * Merge user accounts and delete the old user account.
     *
     * @param $oldUserId int The user ID to remove
     * @param $newUserId int The user ID to receive all "assets" (i.e. submissions) from old user
     */
    public function mergeUsers($oldUserId, $newUserId)
    {
        // Need both user ids for merge
        if (empty($oldUserId) || empty($newUserId)) {
            return false;
        }

        HookRegistry::call('UserAction::mergeUsers', [&$oldUserId, &$newUserId]);

        $collector = Repo::submissionFiles()
            ->getCollector()
            ->filterByUploaderUserIds([$oldUserId])
            ->filterByIncludeDependentFiles(true);
        $submissionFilesIterator = Repo::submissionFiles()->getMany($collector);
        foreach ($submissionFilesIterator as $submissionFile) {
            Repo::submissionFiles()
                ->edit(
                    $submissionFile,
                    ['uploaderUserId' => $newUserId]
                );
        }

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $notes = $noteDao->getByUserId($oldUserId);
        while ($note = $notes->next()) {
            $note->setUserId($newUserId);
            $noteDao->updateObject($note);
        }

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /** @var EditDecisionDAO $editDecisionDao */
        $editDecisionDao->transferEditorDecisions($oldUserId, $newUserId);

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        foreach ($reviewAssignmentDao->getByUserId($oldUserId) as $reviewAssignment) {
            $reviewAssignment->setReviewerId($newUserId);
            $reviewAssignmentDao->updateObject($reviewAssignment);
        }

        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmailLogDao->changeUser($oldUserId, $newUserId);
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /** @var SubmissionEventLogDAO $submissionEventLogDao */
        $submissionEventLogDao->changeUser($oldUserId, $newUserId);

        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionComments = $submissionCommentDao->getByUserId($oldUserId);

        while ($submissionComment = $submissionComments->next()) {
            $submissionComment->setAuthorId($newUserId);
            $submissionCommentDao->updateObject($submissionComment);
        }

        $accessKeyDao = DAORegistry::getDAO('AccessKeyDAO'); /** @var AccessKeyDAO $accessKeyDao */
        $accessKeyDao->transferAccessKeys($oldUserId, $newUserId);

        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationDao->transferNotifications($oldUserId, $newUserId);

        // Delete the old user and associated info.
        $sessionDao = DAORegistry::getDAO('SessionDAO'); /** @var SessionDAO $sessionDao */
        $sessionDao->deleteByUserId($oldUserId);
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFileDao->deleteByUserId($oldUserId);
        $userSettingsDao = DAORegistry::getDAO('UserSettingsDAO'); /** @var UserSettingsDAO $userSettingsDao */
        $userSettingsDao->deleteSettings($oldUserId);
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteByUserId($oldUserId);

        // Transfer old user's roles
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByUserId($oldUserId);
        while ($userGroup = $userGroups->next()) {
            if (!$userGroupDao->userInGroup($newUserId, $userGroup->getId())) {
                $userGroupDao->assignUserToGroup($newUserId, $userGroup->getId());
            }
        }
        $userGroupDao->deleteAssignmentsByUserId($oldUserId);

        // Transfer stage assignments.
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getByUserId($oldUserId);
        while ($stageAssignment = $stageAssignments->next()) {
            $duplicateAssignments = $stageAssignmentDao->getBySubmissionAndStageId($stageAssignment->getSubmissionId(), null, $stageAssignment->getUserGroupId(), $newUserId);
            if (!$duplicateAssignments->next()) {
                // If no similar assignments already exist, transfer this one.
                $stageAssignment->setUserId($newUserId);
                $stageAssignmentDao->updateObject($stageAssignment);
            } else {
                // There's already a stage assignment for the new user; delete.
                $stageAssignmentDao->deleteObject($stageAssignment);
            }
        }

        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $userDao->deleteUserById($oldUserId);

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\PKPUserAction', '\PKPUserAction');
}
