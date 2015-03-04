<?php

/**
 * @file controllers/api/file/PKPManageFileApiHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPManageFileApiHandler
 * @ingroup controllers_api_file
 *
 * @brief Class defining an AJAX API for file manipulation.
 */

// Import the base handler.
import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

abstract class PKPManageFileApiHandler extends Handler {

	/**
	 * Constructor.
	 */
	function PKPManageFileApiHandler() {
		parent::Handler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('deleteFile', 'editMetadata', 'saveMetadata')
		);
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		import('classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));

		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Public handler methods
	//
	/**
	 * Delete a file or revision
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function deleteFile($args, $request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $request->getUserVar('stageId');
		if ($stageId) {
			// validate the stage id.
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$user = $request->getUser();
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $stageId, null, $user->getId());
		}

		assert($submissionFile && $submission); // Should have been validated already

		$noteDao = DAORegistry::getDAO('NoteDAO');
		$noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId());

		// Delete all signoffs related with this file.
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoffFactory = $signoffDao->getAllByAssocType(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId());
		$signoffs = $signoffFactory->toArray();
		$notificationMgr = new NotificationManager();

		foreach ($signoffs as $signoff) {
			$signoffDao->deleteObject($signoff);

			// Delete for all users.
			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_AUDITOR_REQUEST, NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT),
				null,
				ASSOC_TYPE_SIGNOFF,
				$signoff->getId()
			);

			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF),
				array($signoff->getUserId()),
				ASSOC_TYPE_SUBMISSION,
				$submission->getId()
			);
		}

		// Delete the submission file.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */

		// check to see if we need to remove review_round_file associations
		if (!$stageAssignments->wasEmpty()) {
			$submissionFileDao->deleteReviewRoundAssignment($submission->getId(), $stageId, $submissionFile->getFileId(), $submissionFile->getRevision());
		}
		$success = (boolean)$submissionFileDao->deleteRevisionById($submissionFile->getFileId(), $submissionFile->getRevision(), $submissionFile->getFileStage(), $submission->getId());

		if ($success) {
			if ($submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_REVISION) {
				// Get a list of author user IDs
				$authorUserIds = array();
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
				while ($assignment = $submitterAssignments->next()) {
					$authorUserIds[] = $assignment->getUserId();
				}

				// Update the notifications
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS),
					$authorUserIds,
					ASSOC_TYPE_SUBMISSION,
					$submission->getId()
				);

				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_ALL_REVISIONS_IN),
					null,
					ASSOC_TYPE_REVIEW_ROUND,
					$lastReviewRound->getId()
				);
			}

			$this->indexSubmissionFiles($submission, $submissionFile);
			$fileManager = $this->getFileManager($submission->getContextId(), $submission->getId());
			$fileManager->deleteFile($submissionFile->getFileId(), $submissionFile->getRevision());

			$this->setupTemplate($request);
			$user = $request->getUser();
			if (!$request->getUserVar('suppressNotification')) NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedFile')));

			$this->logDeletionEvent($request, $submission, $submissionFile, $user);

			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Edit submission file metadata.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editMetadata($args, $request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$stageId = $request->getUserVar('stageId');
		$metadataForm = $submissionFile->getMetadataForm($stageId, $reviewRound);
		$metadataForm->setShowButtons(true);
		return new JSONMessage(true, $metadataForm->fetch($request));
	}

	/**
	 * Save the metadata of the latest revision of
	 * the requested submission file.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function saveMetadata($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$stageId = $request->getUserVar('stageId');
		$metadataForm = $submissionFile->getMetadataForm($stageId, $reviewRound);
		$metadataForm->readInputData();
		if ($metadataForm->validate()) {
			$metadataForm->execute($args, $request);
			$submissionFile = $metadataForm->getSubmissionFile();

			// Get a list of author user IDs
			$authorUserIds = array();
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
			while ($assignment = $submitterAssignments->next()) {
				$authorUserIds[] = $assignment->getUserId();
			}

			// Update the notifications
			$notificationMgr = new NotificationManager(); /* @var $notificationMgr NotificationManager */
			$notificationMgr->updateNotification(
				$request,
				$this->getUpdateNotifications(),
				$authorUserIds,
				ASSOC_TYPE_SUBMISSION,
				$submission->getId()
			);

			if ($reviewRound) {
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_ALL_REVISIONS_IN),
					null,
					ASSOC_TYPE_REVIEW_ROUND,
					$reviewRound->getId()
				);

				// Delete any 'revision requested' notifications since all revisions are now in.
				$context = $request->getContext();
				$notificationDao = DAORegistry::getDAO('NotificationDAO');
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
				while ($assignment = $submitterAssignments->next()) {
					$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), $assignment->getUserId(), NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS, $context->getId());
				}
			}

			// Log the upload event
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
			$user = $request->getUser();
			SubmissionLog::logEvent(
				$request, $submission,
				$submissionFile->getRevision()>1?SUBMISSION_LOG_FILE_REVISION_UPLOAD:SUBMISSION_LOG_FILE_UPLOAD,
				$submissionFile->getRevision()>1?'submission.event.fileRevised':'submission.event.fileUploaded',
				array('fileStage' => $submissionFile->getFileStage(), 'fileId' => $submissionFile->getFileId(), 'fileRevision' => $submissionFile->getRevision(), 'originalFileName' => $submissionFile->getOriginalFileName(), 'submissionId' => $submissionFile->getSubmissionId(), 'username' => $user->getUsername())
			);

			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false, $metadataForm->fetch($request));
		}
	}

	/**
	 * Indexes the files associated with a submission.
	 * Must be implemented by sub classes.
	 * @param $submission Submission
	 * @param $submissionFile SubmissionFile
	 */
	abstract function indexSubmissionFiles($submission, $submissionFile);

	/**
	 * indexes the files associated with a submission.
	 * @param $contextId int the context id.
	 * @param $submissionId int the submission id.
	 * @return SubmissionFileManager
	 */
	function getFileManager($contextId, $submissionId) {
		import('lib.pkp.classes.file.SubmissionFileManager');
		return new SubmissionFileManager($contextId, $submissionId);
	}

	/**
	 * Logs the deletion event using app-specific logging classes.
	 * Must be implemented by subclasses.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $submissionFile SubmissionFile
	 * @param $user PKPUser
	 */
	abstract function logDeletionEvent($request, $submission, $submissionFile, $user);

	/**
	 * Get the list of notifications to be updated on metadata form submission.
	 * @return array
	 */
	protected function getUpdateNotifications() {
		return array(NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS);
	}
}

?>
