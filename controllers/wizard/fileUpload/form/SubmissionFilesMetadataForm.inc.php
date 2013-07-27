<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesMetadataForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesMetadataForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for editing a submission file's metadata
 */

import('lib.pkp.classes.form.Form');

class SubmissionFilesMetadataForm extends Form {

	/** @var SubmissionFile */
	var $_submissionFile;

	/** @var integer */
	var $_stageId;

	/** @var ReviewRound */
	var $_reviewRound;


	/**
	 * Constructor.
	 * @param $submissionFile SubmissionFile
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $reviewRound ReviewRound (optional) Current review round, if any.
	 */
	function SubmissionFilesMetadataForm(&$submissionFile, $stageId, $reviewRound = null) {
		parent::Form('controllers/wizard/fileUpload/form/metadataForm.tpl');

		// Initialize the object.
		$this->_submissionFile =& $submissionFile;
		$this->_stageId = $stageId;
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->_reviewRound =& $reviewRound;
		}

		// Add validation checks.
		$this->addCheck(new FormValidator($this, 'name', 'required', 'submission.submit.fileNameRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the submission file.
	 * @return SubmissionFile
	 */
	function &getSubmissionFile() {
		return $this->_submissionFile;
	}

	/**
	 * Get the workflow stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get review round.
	 * @return ReviewRound
	 */
	function getReviewRound() {
		return $this->_reviewRound;
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('name');
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('name', 'note'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		// Submission file.
		$submissionFile = $this->getSubmissionFile();
		$templateMgr->assign('submissionFile', $submissionFile);

		// Workflow stage.
		$templateMgr->assign('stageId', $this->getStageId());

		// Review round if we are in review stage.
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$templateMgr->assign('reviewRoundId', $reviewRound->getId());
		}

		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($args, $request) {
		// Update the submission file with data from the form.
		$submissionFile = $this->getSubmissionFile();
		$submissionFile->setName($this->getData('name'), AppLocale::getLocale());
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFileDao->updateObject($submissionFile);

		// Save the note if it exists.
		if ($this->getData('note')) {
			$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
			$note = $noteDao->newDataObject();

			$user = $request->getUser();
			$note->setUserId($user->getId());

			$note->setContents($this->getData('note'));
			$note->setAssocType(ASSOC_TYPE_SUBMISSION_FILE);
			$note->setAssocId($submissionFile->getFileId());

			$noteId = $noteDao->insertObject($note);

			// Mark the note as viewed by this user
			$user = $request->getUser();
			$viewsDao = DAORegistry::getDAO('ViewsDAO');
			$viewsDao->recordView(ASSOC_TYPE_NOTE, $noteId, $user->getId());
		}
	}
}

?>
