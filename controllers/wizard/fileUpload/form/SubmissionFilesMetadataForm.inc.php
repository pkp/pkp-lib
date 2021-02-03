<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesMetadataForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param $stageId int One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $reviewRound ReviewRound (optional) Current review round, if any.
	 * @param $template string Path and filename to template file (optional).
	 */
	function __construct($submissionFile, $stageId, $reviewRound = null, $template = null) {
		if ($template === null) $template = 'controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl';
		parent::__construct($template);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_PKP_GRID);

		// Initialize the object.
		$this->_submissionFile = $submissionFile;
		$this->_stageId = $stageId;
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->_reviewRound = $reviewRound;
		}

		$submissionLocale = $submissionFile->getData('locale');
		$this->setDefaultFormLocale($submissionLocale);

		// Add validation checks.
		$this->addCheck(new FormValidatorLocale($this, 'name', 'required', 'submission.submit.fileNameRequired', $submissionLocale));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the submission file.
	 * @return SubmissionFile
	 */
	function getSubmissionFile() {
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

	/**
	 * Set the "show buttons" flag
	 * @param $showButtons boolean
	 */
	function setShowButtons($showButtons) {
		$this->setData('showButtons', $showButtons);
	}

	/**
	 * Get the "show buttons" flag
	 * @return boolean
	 */
	function getShowButtons() {
		return $this->getData('showButtons');
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
		$this->readUserVars(array('name', 'showButtons',
			'artworkCaption', 'artworkCredit', 'artworkCopyrightOwner',
			'artworkCopyrightOwnerContact', 'artworkPermissionTerms',
			'creator', 'subject', 'description', 'publisher', 'sponsor', 'source', 'language', 'dateCreated',
		));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$reviewRound = $this->getReviewRound();
		$genre = DAORegistry::getDAO('GenreDAO')->getById($this->getSubmissionFile()->getData('genreId'), $request->getContext()->getId());

		$templateMgr->assign(array(
			'submissionFile' => $this->getSubmissionFile(),
			'stageId' => $this->getStageId(),
			'reviewRoundId' => $reviewRound?$reviewRound->getId():null,
			'supportsDependentFiles' => Services::get('submissionFile')->supportsDependentFiles($this->getSubmissionFile()),
			'genre' => $genre,
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionParams) {

		$props = [
			'name' => $this->getData('name'),
		];

		// Artwork metadata
		$props = array_merge($props, [
			'caption' => $this->getData('artworkCaption'),
			'credit' => $this->getData('artworkCredit'),
			'copyrightOwner' => $this->getData('artworkCopyrightOwner'),
			'terms' => $this->getData('artworkPermissionTerms'),
		]);

		// Supplementary file metadata
		$props = array_merge($props, [
			'subject' => $this->getData('subject'),
			'creator' => $this->getData('creator'),
			'description' => $this->getData('description'),
			'publisher' => $this->getData('publisher'),
			'sponsor' => $this->getData('sponsor'),
			'source' => $this->getData('source'),
			'language' => $this->getData('language'),
			'dateCreated' => $this->getData('dateCreated'),
		]);

		$this->_submissionFile = Services::get('submissionFile')->edit($this->getSubmissionFile(), $props, Application::get()->getRequest());

		parent::execute(...$functionParams);
	}

}


