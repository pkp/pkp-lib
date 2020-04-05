<?php

/**
 * @file classes/user/PKPUserAction.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserAction
 * @ingroup user
 * @see User
 *
 * @brief PKPUserAction class.
 */

class PKPUserAction {
	/**
	 * Merge user accounts and delete the old user account.
	 * @param $oldUserId int The user ID to remove
	 * @param $newUserId int The user ID to receive all "assets" (i.e. submissions) from old user
	 */
	public function mergeUsers($oldUserId, $newUserId) {
		// Need both user ids for merge
		if (empty($oldUserId) || empty($newUserId)) {
			return false;
		}

		HookRegistry::call('UserAction::mergeUsers', array(&$oldUserId, &$newUserId));

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFileDao->transferOwnership($oldUserId, $newUserId);

		$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
		$notes = $noteDao->getByUserId($oldUserId);
		while ($note = $notes->next()) {
			$note->setUserId($newUserId);
			$noteDao->updateObject($note);
		}

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$editDecisionDao->transferEditorDecisions($oldUserId, $newUserId);

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		foreach ($reviewAssignmentDao->getByUserId($oldUserId) as $reviewAssignment) {
			$reviewAssignment->setReviewerId($newUserId);
			$reviewAssignmentDao->updateObject($reviewAssignment);
		}

		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /* @var $submissionEmailLogDao SubmissionEmailLogDAO */
		$submissionEmailLogDao->changeUser($oldUserId, $newUserId);
		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /* @var $submissionEventLogDao SubmissionEventLogDAO */
		$submissionEventLogDao->changeUser($oldUserId, $newUserId);

		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /* @var $submissionCommentDao SubmissionCommentDAO */
		$submissionComments = $submissionCommentDao->getByUserId($oldUserId);

		while ($submissionComment = $submissionComments->next()) {
			$submissionComment->setAuthorId($newUserId);
			$submissionCommentDao->updateObject($submissionComment);
		}

		$accessKeyDao = DAORegistry::getDAO('AccessKeyDAO'); /* @var $accessKeyDao AccessKeyDAO */
		$accessKeyDao->transferAccessKeys($oldUserId, $newUserId);

		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationDao->transferNotifications($oldUserId, $newUserId);

		// Delete the old user and associated info.
		$sessionDao = DAORegistry::getDAO('SessionDAO'); /* @var $sessionDao SessionDAO */
		$sessionDao->deleteByUserId($oldUserId);
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFileDao->deleteByUserId($oldUserId);
		$userSettingsDao = DAORegistry::getDAO('UserSettingsDAO'); /* @var $userSettingsDao UserSettingsDAO */
		$userSettingsDao->deleteSettings($oldUserId);
		$subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /* @var $subEditorsDao SubEditorsDAO */
		$subEditorsDao->deleteByUserId($oldUserId);

		// Transfer old user's roles
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroups = $userGroupDao->getByUserId($oldUserId);
		while(!$userGroups->eof()) {
			$userGroup = $userGroups->next();
			if (!$userGroupDao->userInGroup($newUserId, $userGroup->getId())) {
				$userGroupDao->assignUserToGroup($newUserId, $userGroup->getId());
			}
		}
		$userGroupDao->deleteAssignmentsByUserId($oldUserId);

		// Transfer stage assignments.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
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

		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$userDao->deleteUserById($oldUserId);

		return true;
	}
}

