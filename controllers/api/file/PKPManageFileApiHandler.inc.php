<?php

/**
 * @file controllers/api/file/PKPManageFileApiHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('deleteFile', 'editMetadata', 'editMetadataTab', 'saveMetadata')
		);
		// Load submission-specific translations
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
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
		if (!$request->checkCSRF()) return new JSONMessage(false);

		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $request->getUserVar('stageId');

		assert(isset($submissionFile) && isset($submission)); // Should have been validated already

		$noteDao = DAORegistry::getDAO('NoteDAO');
		$noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId());

		// Retrieve the review round so it can be updated after the file is
		// deleted
		if ($submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_REVISION) {
			import('lib.pkp.classes.submission.reviewRound.ReviewRoundDAO');
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$reviewRound = $reviewRoundDao->getBySubmissionFileId($submissionFile->getFileId());
		}

		// Detach any dependent entities to this file deletion.
		$this->detachEntities($submissionFile, $submission->getId(), $stageId);

		// Delete the submission file.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		if (!$submissionFileDao->deleteRevisionById($submissionFile->getFileId(), $submissionFile->getRevision(), $submissionFile->getFileStage(), $submission->getId())) return new JSONMessage(false);

		$notificationMgr = new NotificationManager();
		switch ($submissionFile->getFileStage()) {
			case SUBMISSION_FILE_REVIEW_REVISION:
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

				// Update the ReviewRound status when revision is submitted
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRoundDao->updateStatus($reviewRound);
				break;

			case SUBMISSION_FILE_COPYEDIT:
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_ASSIGN_COPYEDITOR, NOTIFICATION_TYPE_AWAITING_COPYEDITS),
					null,
					ASSOC_TYPE_SUBMISSION,
					$submission->getId()
				);
				break;
		}

		$this->removeFileIndex($submission, $submissionFile);
		$fileManager = $this->getFileManager($submission->getContextId(), $submission->getId());
		$fileManager->deleteById($submissionFile->getFileId(), $submissionFile->getRevision());

		$this->setupTemplate($request);
		$user = $request->getUser();
		if (!$request->getUserVar('suppressNotification')) NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedFile')));

		$this->logDeletionEvent($request, $submission, $submissionFile, $user);

		return DAO::getDataChangedEvent();
	}

	/**
	 * Edit submission file metadata modal.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editMetadata($args, $request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		if ($submissionFile->getFileStage() == SUBMISSION_FILE_PROOF) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('submissionFile', $submissionFile);
			$templateMgr->assign('stageId', $request->getUserVar('stageId'));
			return new JSONMessage(true, $templateMgr->fetch('controllers/api/file/editMetadata.tpl'));
		} else {
			return $this->editMetadataTab($args, $request);
		}
	}

	/**
	 * Edit submission file metadata tab.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editMetadataTab($args, $request) {
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
			$metadataForm->execute();
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

				// Delete any 'revision requested' notifications since revisions are now in.
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
				array('fileStage' => $submissionFile->getFileStage(), 'fileId' => $submissionFile->getFileId(), 'fileRevision' => $submissionFile->getRevision(), 'originalFileName' => $submissionFile->getOriginalFileName(), 'submissionId' => $submissionFile->getSubmissionId(), 'username' => $user->getUsername(), 'name' => $submissionFile->getLocalizedName())
			);

			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(true, $metadataForm->fetch($request));
		}
	}

	/**
	 * Remove the submission file index.
	 * @param $submission Submission
	 * @param $submissionFile SubmissionFile
	 */
	abstract function removeFileIndex($submission, $submissionFile);

	/**
	 * Get the submission file manager.
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

	/**
	 * Detach any dependent entities to this file upload.
	 * @param $submissionFile SubmissionFile
	 * @param $submissionId integer
	 * @param $stageId integer
	 */
	 function detachEntities($submissionFile, $submissionId, $stageId) {
		switch ($submissionFile->getFileStage()) {
			case SUBMISSION_FILE_REVIEW_FILE:
			case SUBMISSION_FILE_REVIEW_ATTACHMENT:
			case SUBMISSION_FILE_REVIEW_REVISION:
				// check to see if we need to remove review_round_file associations
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				$submissionFileDao->deleteReviewRoundAssignment($submissionId, $stageId, $submissionFile->getFileId(), $submissionFile->getRevision());
		}
	}

}


