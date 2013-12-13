<?php

/**
 * @file controllers/grid/files/fileSignoff/form/NewSignoffNoteForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NewSignoffNoteForm
 * @ingroup informationCenter_form
 *
 * @brief Form to display and post notes on a signoff.
 */

import('lib.pkp.controllers.informationCenter.form.NewNoteForm');

class NewSignoffNoteForm extends NewNoteForm {
	/** @var int The ID of the signoff to attach the note to */
	var $signoffId;

	/** @var int The ID of the signoff submission */
	var $_submissionId;

	/** @var int The signoff symbolic. */
	var $_symbolic;

	/** @var int The signoff stage id. */
	var $_stageId;

	/** @var array The fetch notes list action args. */
	var $_actionArgs;

	/**
	 * Constructor.
	 */
	function NewSignoffNoteForm($signoffId, $submissionId, $signoffSymbolic, $stageId) {
		parent::NewNoteForm();

		$this->signoffId = $signoffId;
		$this->_submissionId = $submissionId;
		$this->_symbolic = $signoffSymbolic;
		$this->_stageId = $stageId;
		$this->_actionArgs = array(
			'signoffId' => $signoffId,
			'submissionId' => $submissionId,
			'stageId' => $stageId
		);
	}

	/**
	 * Return the assoc type for this note.
	 * @return int
	 */
	function getAssocType() {
		return ASSOC_TYPE_SIGNOFF;
	}

	/**
	 * Return the assoc ID for this note.
	 * @return int
	 */
	function getAssocId() {
		return $this->signoffId;
	}

	/**
	 * @copydoc NewNoteForm::getNewNoteFormTemplate()
	 */
	function getNewNoteFormTemplate() {
		return 'controllers/informationCenter/newFileUploadNoteForm.tpl';
	}

	/**
	 * @copydoc NewNoteForm::getSubmitNoteLocaleKey()
	 */
	function getSubmitNoteLocaleKey() {
		return 'submission.task.addNote';
	}

	/**
	 * @copydoc NewNoteForm::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('signoffId', 'temporaryFileId'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate() {
		// FIXME: this should go in a FormValidator in the constructor.
		$signoffId = $this->signoffId;
		return (is_numeric($signoffId) && $signoffId > 0);
	}

	/**
	 * @copydoc NewNoteForm::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('linkParams', $this->_actionArgs);
		$templateMgr->assign('showEarlierEntries', false);
		$templateMgr->assign('signoffId', $this->signoffId);
		$templateMgr->assign('symbolic', $this->_symbolic);
		$templateMgr->assign('stageId', $this->_stageId);
		$templateMgr->assign('submissionId', $this->_submissionId);

		return parent::fetch($request);
	}

	function execute($request, $userRoles) {
		$user = $request->getUser();

		// Retrieve the signoff we're working with.
		$signoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
		$signoff = $signoffDao->getById($this->getData('signoffId'));
		assert(is_a($signoff, 'Signoff'));

		// Insert the note, if existing content and/or file.
		$temporaryFileId = $this->getData('temporaryFileId');
		if ($temporaryFileId || $this->getData('newNote')) {
			$noteDao = DAORegistry::getDAO('NoteDAO');
			$note = $noteDao->newDataObject();

			$note->setUserId($user->getId());
			$note->setContents($this->getData('newNote'));
			$note->setAssocType(ASSOC_TYPE_SIGNOFF);
			$note->setAssocId($signoff->getId());
			$noteId = $noteDao->insertObject($note);
			$note->setId($noteId);

			// Upload the file, if any, and associate it with the note.
			if ($temporaryFileId) {
				// Fetch the temporary file storing the uploaded library file
				$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
				$temporaryFile =& $temporaryFileDao->getTemporaryFile(
					$temporaryFileId,
					$user->getId()
				);

				// Upload the file.
				// Bring in the SUBMISSION_FILE_* constants
				import('lib.pkp.classes.submission.SubmissionFile');

				$context = $request->getContext();
				import('lib.pkp.classes.file.SubmissionFileManager');
				$submissionFileManager = new SubmissionFileManager($context->getId(), $this->_submissionId);

				// Get the submission file that is associated with the signoff.
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /** @var $submissionFileDao SubmissionFileDAO */
				$signoffFile =& $submissionFileDao->getLatestRevision($signoff->getAssocId());
				assert(is_a($signoffFile, 'SubmissionFile'));

				$noteFileId = $submissionFileManager->temporaryFileToSubmissionFile(
					$temporaryFile,
					SUBMISSION_FILE_NOTE, $signoff->getUserId(),
					$signoff->getUserGroupId(), null, $signoffFile->getGenreId(),
					ASSOC_TYPE_NOTE, $noteId
				);
			}

			if ($user->getId() == $signoff->getUserId() && !$signoff->getDateCompleted()) {
				// Considered as a signoff response.
				// Mark the signoff as completed (we have a note with content
				// or a file or both).
				$signoff->setDateCompleted(Core::getCurrentDate());
				$signoffDao->updateObject($signoff);

				$notificationMgr = new NotificationManager();
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_AUDITOR_REQUEST),
					array($signoff->getUserId()),
					ASSOC_TYPE_SIGNOFF,
					$signoff->getId()
				);

				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF),
					array($signoff->getUserId()),
					ASSOC_TYPE_SUBMISSION,
					$this->_submissionId
				);

				// Define the success trivial notification locale key.
				$successLocaleKey = 'notification.uploadedResponse';

				// log the event.
				import('lib.pkp.classes.log.SubmissionFileLog');
				import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
				$submissionDao = Application::getSubmissionDAO();
				$submission = $submissionDao->getById($this->_submissionId);
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				$submissionFile = $submissionFileDao->getLatestRevision($signoff->getFileId());

				if (isset($submissionFile)) {
					SubmissionFileLog::logEvent($request, $submissionFile, SUBMISSION_LOG_FILE_AUDIT_UPLOAD, 'submission.event.fileAuditUploaded', array('file' => $submissionFile->getOriginalFileName(), 'name' => $user->getFullName(), 'username' => $user->getUsername()));
				}
			} else {
				// Common note addition.
				if ($user->getId() !== $signoff->getUserId() &&
						array_intersect($userRoles, array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_SUB_EDITOR))) {
					// If the current user is a context/series/sub editor or assistant, open the signoff again.
					if ($signoff->getDateCompleted()) {
						$signoff->setDateCompleted(null);
						$signoffDao->updateObject($signoff);

						$notificationMgr = new NotificationManager();
						$notificationMgr->updateNotification(
							$request,
							array(NOTIFICATION_TYPE_AUDITOR_REQUEST),
							array($signoff->getUserId()),
							ASSOC_TYPE_SIGNOFF,
							$signoff->getId()
						);

						$notificationMgr->updateNotification(
							$request,
							array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF),
							array($signoff->getUserId()),
							ASSOC_TYPE_SUBMISSION,
							$this->_submissionId
						);
					}
				}
				$successLocaleKey = 'notification.addedNote';
			}

			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __($successLocaleKey)));

			return $signoff->getId();
		}
	}
}

?>
