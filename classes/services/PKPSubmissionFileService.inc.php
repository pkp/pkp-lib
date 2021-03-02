<?php
/**
 * @file classes/services/PKPSubmissionFileService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\Services;

use \Application;
use \Core;
use \DAOResultFactory;
use \DAORegistry;
use \HookRegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder;

class PKPSubmissionFileService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($id) {
		return DAORegistry::getDAO('SubmissionFileDAO')->getById($id);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getCount()
	 */
	public function getCount($args = []) {
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getIds()
	 */
	public function getIds($args = []) {
		return $this->getQueryBuilder($args)->getIds();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMany()
	 */
	public function getMany($args = null) {
		$submissionFileQO = $this
			->getQueryBuilder($args)
			->getQuery()
			->join('submissions as s', 's.submission_id', '=', 'sf.submission_id')
			->join('files as f', 'f.file_id', '=', 'sf.file_id')
			->select(['sf.*', 'f.*', 's.locale as locale']);
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$result = $submissionFileDao->retrieve($submissionFileQO->toSql(), $submissionFileQO->getBindings());
		$queryResults = new DAOResultFactory($result, $submissionFileDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = null) {
		return $this->getCount($args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getQueryBuilder()
	 */
	public function getQueryBuilder($args = []) {

		$defaultArgs = [
			'assocTypes' => [],
			'assocIds' => [],
			'fileIds' => [],
			'fileStages' => [],
			'genreIds' => [],
			'includeDependentFiles' => false,
			'reviewIds' => [],
			'reviewRoundIds' => [],
			'submissionIds' => [],
			'uploaderUserIds' => [],
		];

		$args = array_merge($defaultArgs, $args);

		$submissionFileQB = new PKPSubmissionFileQueryBuilder();
		$submissionFileQB
			->filterByAssoc($args['assocTypes'], $args['assocIds'])
			->filterByFileIds($args['fileIds'])
			->filterByFileStages($args['fileStages'])
			->filterByGenreIds($args['genreIds'])
			->filterByReviewIds($args['reviewIds'])
			->filterByReviewRoundIds($args['reviewRoundIds'])
			->filterBySubmissionIds($args['submissionIds'])
			->filterByUploaderUserIds($args['uploaderUserIds'])
			->includeDependentFiles($args['includeDependentFiles']);

		HookRegistry::call('SubmissionFile::getMany::queryBuilder', [&$submissionFileQB, $args]);

		return $submissionFileQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($submissionFile, $props, $args = null) {
		$request = $args['request'];
		$submission = $args['submission'];
		$dispatcher = $request->getDispatcher();

		$values = [];

		foreach ($props as $prop) {
			switch ($prop) {
				case '_href':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_API,
						$request->getContext()->getData('urlPath'),
						'submissions/' . $submission->getId() . '/files/' . $submissionFile->getId()
					);
					break;
				case 'dependentFiles':
					$dependentFilesIterator = Services::get('submissionFile')->getMany([
						'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
						'assocIds' => [$submissionFile->getId()],
						'submissionIds' => [$submission->getId()],
						'fileStages' => [SUBMISSION_FILE_DEPENDENT],
						'includeDependentFiles' => true,
					]);
					$dependentFiles = [];
					foreach ($dependentFilesIterator as $dependentFile) {
						$dependentFiles[] = $this->getFullProperties($dependentFile, $args);
					}
					$values[$prop] = $dependentFiles;
					break;
				case 'documentType':
					$values[$prop] = Services::get('file')->getDocumentType($submissionFile->getData('mimetype'));
					break;
				case 'revisions':
					$files = [];
					$revisions = DAORegistry::getDAO('SubmissionFileDAO')->getRevisions($submissionFile->getId());
					foreach ($revisions as $revision) {
						if ($revision->fileId === $submissionFile->getData('fileId')) {
							continue;
						}
						$files[] = [
							'documentType' => Services::get('file')->getDocumentType($revision->mimetype),
							'fileId' => $revision->fileId,
							'mimetype' => $revision->mimetype,
							'path' => $revision->path,
							'url' => $dispatcher->url(
								$request,
								ROUTE_COMPONENT,
								$request->getContext()->getData('urlPath'),
								'api.file.FileApiHandler',
								'downloadFile',
								null,
								[
									'fileId' => $revision->fileId,
									'submissionFileId' => $submissionFile->getId(),
									'submissionId' => $submissionFile->getData('submissionId'),
									'stageId' => $this->getWorkflowStageId($submissionFile),
								]
							),
						];
					}
					$values[$prop] = $files;
					break;
				case 'url':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_COMPONENT,
						$request->getContext()->getData('urlPath'),
						'api.file.FileApiHandler',
						'downloadFile',
						null,
						[
							'submissionFileId' => $submissionFile->getId(),
							'submissionId' => $submissionFile->getData('submissionId'),
							'stageId' => $this->getWorkflowStageId($submissionFile),
						]
					);
					break;
				default:
					$values[$prop] = $submissionFile->getData($prop);
					break;
			}
		}

		$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_SUBMISSION_FILE, $values, $request->getContext()->getSupportedFormLocales());

		HookRegistry::call('SubmissionFile::getProperties', [&$values, $submissionFile, $props, $args]);

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($submissionFile, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_SUBMISSION_FILE);

		return $this->getProperties($submissionFile, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($submissionFile, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_SUBMISSION_FILE);

		return $this->getProperties($submissionFile, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		\AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_SUBMISSION_FILE, $allowedLocales)
		);

		// Check required fields if we're adding a context
		\ValidatorFactory::required(
			$validator,
			$action,
			$schemaService->getRequiredProps(SCHEMA_SUBMISSION_FILE),
			$schemaService->getMultilingualProps(SCHEMA_SUBMISSION_FILE),
			$allowedLocales,
			$primaryLocale
		);

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_SUBMISSION_FILE), $allowedLocales);

		// Do not allow the uploaderUserId or createdAt properties to be modified
		if ($action === VALIDATE_ACTION_EDIT) {
			$validator->after(function($validator) use ($props) {
				if (!empty($props['uploaderUserId']) && !$validator->errors()->get('uploaderUserId')) {
					$validator->errors()->add('uploaderUserId', __('submission.file.notAllowedUploaderUserId'));
				}
				if (!empty($props['createdAt']) && !$validator->errors()->get('createdAt')) {
					$validator->errors()->add('createdAt', __('api.files.400.notAllowedCreatedAt'));
				}
			});
		}

		// Make sure that file stage and assocType match
		if (!empty($props['assocType'])) {
			$validator->after(function($validator) use ($props) {
				if (empty($props['fileStage'])) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.noFileStageId'));
				} elseif ($props['assocType'] === ASSOC_TYPE_REVIEW_ROUND	&& !in_array($props['fileStage'], [SUBMISSION_FILE_REVIEW_FILE, SUBMISSION_FILE_REVIEW_REVISION, SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION])) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.badReviewRoundAssocType'));
				} elseif ($props['assocType'] === ASSOC_TYPE_REVIEW_ASSIGNMENT && $props['fileStage'] !== SUBMISSION_FILE_REVIEW_ATTACHMENT) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.badReviewAssignmentAssocType'));
				} elseif ($props['assocType'] === ASSOC_TYPE_SUBMISSION_FILE && $props['fileStage'] !== SUBMISSION_FILE_DEPENDENT) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.badDependentFileAssocType'));
				} elseif ($props['assocType'] === ASSOC_TYPE_NOTE && $props['fileStage'] !== SUBMISSION_FILE_NOTE) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.badNoteAssocType'));
				} elseif ($props['assocType'] === ASSOC_TYPE_REPRESENTATION && $props['fileStage'] !== SUBMISSION_FILE_PROOF) {
					$validator->errors()->add('assocType', __('api.submissionFiles.400.badRepresentationAssocType'));
				}
			});
		}

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_SUBMISSION_FILE), $allowedLocales);
		}

		HookRegistry::call('SubmissionFile::validate', array(&$errors, $action, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($submissionFile, $request) {
		$submissionFile->setData('createdAt', Core::getCurrentDate());
		$submissionFile->setData('updatedAt', Core::getCurrentDate());
		$id = DAORegistry::getDAO('SubmissionFileDAO')->insertObject($submissionFile);
		$submissionFile = $this->get($id);

		$submission = Services::get('submission')->get($submissionFile->getData('submissionId'));

		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		\SubmissionFileLog::logEvent(
			$request,
			$submissionFile,
			SUBMISSION_LOG_FILE_UPLOAD,
			'submission.event.fileUploaded',
			[
				'fileStage' => $submissionFile->getData('fileStage'),
				'sourceSubmissionFileId' => $submissionFile->getData('sourceSubmissionFileId'),
				'submissionFileId' => $submissionFile->getId(),
				'fileId' => $submissionFile->getData('fileId'),
				'submissionId' => $submissionFile->getData('submissionId'),
				'originalFileName' => $submissionFile->getLocalizedData('name'),
				'username' => $request->getUser()->getUsername(),
			]
		);

		import('lib.pkp.classes.log.SubmissionLog');
		import('classes.log.SubmissionEventLogEntry');
		$user = $request->getUser();
		\SubmissionLog::logEvent(
			$request, $submission,
			SUBMISSION_LOG_FILE_REVISION_UPLOAD,
			'submission.event.fileRevised',
			[
				'fileStage' => $submissionFile->getFileStage(),
				'submissionFileId' => $submissionFile->getId(),
				'fileId' => $submissionFile->getData('fileId'),
				'submissionId' => $submissionFile->getData('submissionId'),
				'username' => $user->getUsername(),
				'name' => $submissionFile->getLocalizedData('name'),
			]
		);

		// Update status and notifications when revisions have been uploaded
		if (in_array($submissionFile->getData('fileStage'), [SUBMISSION_FILE_REVIEW_REVISION, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION])) {
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewRound = $reviewRoundDao->getById($submissionFile->getData('assocId'));
			if (!$reviewRound) {
				throw new \Exception('Submission file added to review round that does not exist.');
			}

			$reviewRoundDao->updateStatus($reviewRound);

			// Update author notifications
			$authorUserIds = [];
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
			$authorAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submissionFile->getData('submissionId'), ROLE_ID_AUTHOR);
			while ($assignment = $authorAssignments->next()) {
				if ($assignment->getStageId() == $reviewRound->getStageId()) {
					$authorUserIds[] = (int) $assignment->getUserId();
				}
			}
			$notificationMgr = new \NotificationManager();
			$notificationMgr->updateNotification(
				$request,
				[NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS],
				$authorUserIds,
				ASSOC_TYPE_SUBMISSION,
				$submissionFile->getData('submissionId')
			);

			// Notify editors if the file is uploaded by an author
			if (in_array($submissionFile->getData('uploaderUserId'), $authorUserIds)) {
				if (!$submission) {
					throw new \Exception('Submission file added to submission that does not exist.');
				}

				$context = $request->getContext();
				if ($context->getId() != $submission->getData('contextId')) {
					$context = Services::get('context')->get($submission->getData('contextId'));
				}

				$uploader = $request->getUser();
				if ($uploader->getId() != $submissionFile->getData('uploaderUserId')) {
					$uploader = Services::get('user')->get($submissionFile->getData('uploaderUserId'));
				}

				// Fetch the latest notification email timestamp
				import('lib.pkp.classes.log.SubmissionEmailLogEntry'); // Import email event constants
				$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /* @var $submissionEmailLogDao SubmissionEmailLogDAO */
				$submissionEmails = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_AUTHOR_NOTIFY_REVISED_VERSION);
				$lastNotification = null;
				$sentDates = [];
				if ($submissionEmails){
					while ($email = $submissionEmails->next()) {
						if ($email->getDateSent()){
							$sentDates[] = $email->getDateSent();
						}
					}
					if (!empty($sentDates)){
						$lastNotification = max(array_map('strtotime', $sentDates));
					}
				}

				import('lib.pkp.classes.mail.SubmissionMailTemplate');
				$mail = new \SubmissionMailTemplate($submission, 'REVISED_VERSION_NOTIFY');
				$mail->setEventType(SUBMISSION_EMAIL_AUTHOR_NOTIFY_REVISED_VERSION);
				$mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
				// Get editors assigned to the submission, consider also the recommendOnly editors
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $reviewRound->getStageId());
				foreach ($editorsStageAssignments as $editorsStageAssignment) {
					$editor = $userDao->getById($editorsStageAssignment->getUserId());
					 // IF no prior notification exists
					 // OR if editor has logged in after the last revision upload
					 // OR the last upload and notification was sent more than a day ago,
					 // THEN send a new notification
					if (is_null($lastNotification) || strtotime($editor->getDateLastLogin()) > $lastNotification || strtotime('-1 day') > $lastNotification){
						$mail->addRecipient($editor->getEmail(), $editor->getFullName());
					}
				}
				// Get uploader name
				$mail->assignParams(array(
					'authorName' => $uploader->getFullName(),
					'editorialContactSignature' => $context->getData('contactName'),
					'submissionUrl' => $request->getDispatcher()->url(
						$request,
						ROUTE_PAGE,
						null,
						'workflow',
						'index',
						[
							$submission->getId(),
							$reviewRound->getStageId(),
						]
					),
				));

				if ($mail->getRecipients()){
					if (!$mail->send($request)) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new \NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
					}
				}
			}
		}

		HookRegistry::call('SubmissionFile::add', [&$submissionFile, $request]);

		return $submissionFile;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($submissionFile, $params, $request) {
		$newFileUploaded = !empty($params['fileId']) && $params['fileId'] !== $submissionFile->getData('fileId');
		$submissionFile->_data = array_merge($submissionFile->_data, $params);
		$submissionFile->setData('updatedAt', Core::getCurrentDate());

		HookRegistry::call('SubmissionFile::edit', [&$submissionFile, $submissionFile, $params, $request]);

		DAORegistry::getDAO('SubmissionFileDAO')->updateObject($submissionFile);

		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		\SubmissionFileLog::logEvent(
			$request,
			$submissionFile,
			$newFileUploaded ? SUBMISSION_LOG_FILE_REVISION_UPLOAD : SUBMISSION_LOG_FILE_EDIT,
			$newFileUploaded ? 'submission.event.revisionUploaded' : 'submission.event.fileEdited',
			[
				'fileStage' => $submissionFile->getData('fileStage'),
				'sourceSubmissionFileId' => $submissionFile->getData('sourceSubmissionFileId'),
				'submissionFileId' => $submissionFile->getId(),
				'fileId' => $submissionFile->getData('fileId'),
				'submissionId' => $submissionFile->getData('submissionId'),
				'originalFileName' => $submissionFile->getLocalizedData('name'),
				'username' => $request->getUser()->getUsername(),
			]
		);

		import('lib.pkp.classes.log.SubmissionLog');
		import('classes.log.SubmissionEventLogEntry');
		$user = $request->getUser();
		$submission = Services::get('submission')->get($submissionFile->getData('submissionId'));
		\SubmissionLog::logEvent(
			$request, $submission,
			$newFileUploaded ? SUBMISSION_LOG_FILE_REVISION_UPLOAD : SUBMISSION_LOG_FILE_EDIT,
			$newFileUploaded ? 'submission.event.revisionUploaded' : 'submission.event.fileEdited',
			[
				'fileStage' => $submissionFile->getFileStage(),
				'sourceSubmissionFileId' => $submissionFile->getData('sourceSubmissionFileId'),
				'submissionFileId' => $submissionFile->getId(),
				'fileId' => $submissionFile->getData('fileId'),
				'submissionId' => $submissionFile->getData('submissionId'),
				'username' => $user->getUsername(),
				'originalFileName' => $submissionFile->getLocalizedData('name'),
				'name' => $submissionFile->getLocalizedData('name'),
			]
		);

		return $this->get($submissionFile->getId());
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($submissionFile) {
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */

		HookRegistry::call('SubmissionFile::delete::before', [&$submissionFile]);

		// Delete dependent files
		$dependentFilesIterator = $this->getMany([
			'includeDependentFiles' => true,
			'fileStages' => [SUBMISSION_FILE_DEPENDENT],
			'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
			'assocIds' => [$submissionFile->getId()],
		]);
		foreach ($dependentFilesIterator as $dependentFile) {
			$this->delete($dependentFile);
		}

		// Delete review round associations
		if ($submissionFile->getData('fileStage') === SUBMISSION_FILE_REVIEW_REVISION) {
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewRound = $reviewRoundDao->getBySubmissionFileId($submissionFile->getId());
			$submissionFileDao->deleteReviewRoundAssignment($submissionFile->getId());
			$reviewRoundDao->updateStatus($reviewRound);
		}

		// Delete notes for this submission file
		$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
		$noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getId());

		// Update tasks
		import('classes.notification.NotificationManager');
		$notificationMgr = new \NotificationManager();
		switch ($submissionFile->getData('fileStage')) {
			case SUBMISSION_FILE_REVIEW_REVISION:
				$authorUserIds = [];
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
				$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submissionFile->getData('submissionId'), ROLE_ID_AUTHOR);
				while ($assignment = $submitterAssignments->next()) {
					$authorUserIds[] = $assignment->getUserId();
				}
				$notificationMgr->updateNotification(
					Application::get()->getRequest(),
					[NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS],
					$authorUserIds,
					ASSOC_TYPE_SUBMISSION,
					$submissionFile->getData('submissionId')
				);
				break;

			case SUBMISSION_FILE_COPYEDIT:
				$notificationMgr->updateNotification(
					Application::get()->getRequest(),
					[NOTIFICATION_TYPE_ASSIGN_COPYEDITOR, NOTIFICATION_TYPE_AWAITING_COPYEDITS],
					null,
					ASSOC_TYPE_SUBMISSION,
					$submissionFile->getData('submissionId')
				);
				break;
		}

		// Get all revision files before they are deleted in SubmissionFileDAO::deleteObject
		$revisions = $submissionFileDao->getRevisions($submissionFile->getId());

		// Delete the submission file
		$submissionFileDao->deleteObject($submissionFile);

		// Delete all files not referenced by other files
		foreach ($revisions as $revision) {
			$countFileShares = $this->getCount([
				'fileIds' => [$revision->fileId],
				'includeDependentFiles' => true,
			]);
			if (!$countFileShares) {
				Services::get('file')->delete($revision->fileId);
			}
		}

		// Log the deletion
		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		\SubmissionFileLog::logEvent(
			Application::get()->getRequest(),
			$submissionFile,
			SUBMISSION_LOG_FILE_DELETE,
			'submission.event.fileDeleted',
			[
				'fileStage' => $submissionFile->getData('fileStage'),
				'sourceSubmissionFileId' => $submissionFile->getData('sourceSubmissionFileId'),
				'submissionFileId' => $submissionFile->getId(),
				'submissionId' => $submissionFile->getData('submissionId'),
				'username' => Application::get()->getRequest()->getUser()->getUsername(),
			]
		);

		HookRegistry::call('SubmissionFile::delete', [&$submissionFile]);
	}

	/**
	 * Get the file stage ids that a user can access based on their
	 * stage assignments
	 *
	 * This does not return file stages for ROLE_ID_REVIEWER or ROLE_ID_READER.
	 * These roles are not granted stage assignments and this method should not
	 * be used for these roles.
	 *
	 * This method does not define access to review attachments, discussion
	 * files or dependent files. Access to these files are not determined by
	 * stage assignment.
	 *
	 * In some cases it may be necessary to apply additional restrictions. For example,
	 * authors are granted write access to submission files or revisions only when other
	 * conditions are met. This method only considers these an assigned file stage for
	 * authors when read access is requested.
	 *
	 * @param array $stageAssignments The stage assignments of this user.
	 *   Each key is a workflow stage and value is an array of assigned roles
	 * @param int $action Read or write to file stages. One of SUBMISSION_FILE_ACCESS_
	 * @return array List of file stages (SUBMISSION_FILE_*)
	 */
	public function getAssignedFileStages($stageAssignments, $action) {
		$allowedRoles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR];
		$notAuthorRoles = array_diff($allowedRoles, [ROLE_ID_AUTHOR]);

		$allowedFileStages = [];

		if (array_key_exists(WORKFLOW_STAGE_ID_SUBMISSION, $stageAssignments)
				&& !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]))) {
			$hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]));
			// Authors only have read access
			if ($action === SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_SUBMISSION;
			}
		}

		if (array_key_exists(WORKFLOW_STAGE_ID_INTERNAL_REVIEW, $stageAssignments)) {
			$hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_INTERNAL_REVIEW]));
			// Authors can only write revision files under specific conditions
			if ($action === SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_INTERNAL_REVIEW_REVISION;
			}
			// Authors can never access review files
			if ($hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_INTERNAL_REVIEW_FILE;
			}
		}

		if (array_key_exists(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, $stageAssignments)) {
			$hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW]));
			// Authors can only write revision files under specific conditions
			if ($action === SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_REVIEW_REVISION;
				$allowedFileStages[] = SUBMISSION_FILE_ATTACHMENT;
			}
			// Authors can never access review files
			if ($hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_REVIEW_FILE;
			}
		}

		if (array_key_exists(WORKFLOW_STAGE_ID_EDITING, $stageAssignments)
				&& !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_EDITING]))) {
			$hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_EDITING]));
			// Authors only have read access
			if ($action === SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_COPYEDIT;
			}
			if ($hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_FINAL;
			}
		}

		if (array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $stageAssignments)
				&& !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION]))) {
			$hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION]));
			// Authors only have read access
			if ($action === SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_PROOF;
			}
			if ($hasEditorialAssignment) {
				$allowedFileStages[] = SUBMISSION_FILE_PRODUCTION_READY;
			}
		}

		HookRegistry::call('SubmissionFile::assignedFileStages', [&$allowedFileStages, $stageAssignments, $action]);

		return $allowedFileStages;
	}

	/**
	 * Get all valid file stages
	 *
	 * @return array
	 */
	public function getFileStages() {
		import('lib.pkp.classes.submission.SubmissionFile');
		$stages = [
			SUBMISSION_FILE_SUBMISSION,
			SUBMISSION_FILE_NOTE,
			SUBMISSION_FILE_REVIEW_FILE,
			SUBMISSION_FILE_REVIEW_ATTACHMENT,
			SUBMISSION_FILE_FINAL,
			SUBMISSION_FILE_COPYEDIT,
			SUBMISSION_FILE_PROOF,
			SUBMISSION_FILE_PRODUCTION_READY,
			SUBMISSION_FILE_ATTACHMENT,
			SUBMISSION_FILE_REVIEW_REVISION,
			SUBMISSION_FILE_DEPENDENT,
			SUBMISSION_FILE_QUERY,
		];

		HookRegistry::call('SubmissionFile::fileStages', [&$stages]);

		return $stages;
	}

	/**
	 * Get the path to a submission's file directory
	 *
	 * This returns the relative path from the files_dir set in the config.
	 *
	 * @param int $contextId
	 * @param int $submissionId
	 * @return string
	 */
	public function getSubmissionDir($contextId, $submissionId) {
		$dirNames = Application::getFileDirectories();
		return sprintf(
			'%s/%d/%s/%d',
			str_replace('/', '', $dirNames['context']),
			$contextId,
			str_replace('/', '', $dirNames['submission']),
			$submissionId
		);
	}

	/**
	 * Get the workflow stage for a submission file
	 *
	 * @param SubmissionFile $submissionFile
	 * @return int|null WORKFLOW_STAGE_ID_*
	 */
	public function getWorkflowStageId($submissionFile) {
		switch ($submissionFile->getData('fileStage')) {
			case SUBMISSION_FILE_SUBMISSION:
				return WORKFLOW_STAGE_ID_SUBMISSION;
			case SUBMISSION_FILE_FINAL:
			case SUBMISSION_FILE_COPYEDIT:
				return WORKFLOW_STAGE_ID_EDITING;
			case SUBMISSION_FILE_PROOF:
			case SUBMISSION_FILE_PRODUCTION_READY:
				return WORKFLOW_STAGE_ID_PRODUCTION;
			case SUBMISSION_FILE_DEPENDENT:
				$parentFile = Services::get('submissionFile')->get($submissionFile->getData('assocId'));
				return $this->getWorkflowStageId($parentFile);
			case SUBMISSION_FILE_REVIEW_FILE:
			case SUBMISSION_FILE_INTERNAL_REVIEW_FILE:
			case SUBMISSION_FILE_REVIEW_ATTACHMENT:
			case SUBMISSION_FILE_REVIEW_REVISION:
			case SUBMISSION_FILE_ATTACHMENT:
			case SUBMISSION_FILE_INTERNAL_REVIEW_REVISION:
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
				$reviewRound = $reviewRoundDao->getBySubmissionFileId($submissionFile->getId());
				return $reviewRound->getStageId();
			case SUBMISSION_FILE_QUERY:
				$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
				$note = $noteDao->getById($submissionFile->getData('assocId'));
				$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
				$query = $queryDao->getById($note->getAssocId());
				return $query ? $query->getStageId() : null;
		}
		throw new \Exception('Could not determine the workflow stage id from submission file ' . $submissionFile->getId() . ' with file stage ' . $submissionFile->getData('fileStage'));
	}

	/**
	 * Check if a submission file supports dependent files
	 *
	 * @param SubmissionFile $submissionFile
	 * @return boolean
	 */
	public function supportsDependentFiles($submissionFile) {
		$fileStage = $submissionFile->getData('fileStage');
		$excludedFileStages = [
			SUBMISSION_FILE_DEPENDENT,
			SUBMISSION_FILE_QUERY,
		];
		$allowedMimetypes = [
			'text/html',
			'application/xml',
			'text/xml',
		];

		$result = !in_array($fileStage, $excludedFileStages) && in_array($submissionFile->getData('mimetype'), $allowedMimetypes);

		HookRegistry::call('SubmissionFile::supportsDependentFiles', [&$result, $submissionFile]);

		return $result;
	}
}
