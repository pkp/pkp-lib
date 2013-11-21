<?php

/**
 * @file controllers/grid/files/fileSignoff/AuthorSignoffFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorSignoffFilesGridDataProvider
 * @ingroup controllers_grid_files_fileSignoff
 *
 * @brief Provide data for author signoff file grids.
 */

import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

// Import file stage constants.
import('lib.pkp.classes.submission.SubmissionFile');

class AuthorSignoffFilesGridDataProvider extends SubmissionFilesGridDataProvider {

	/** @var int */
	var $_userId;

	/* @var string */
	var $_symbolic;

	/**
	 * Constructor
	 */
	function AuthorSignoffFilesGridDataProvider($symbolic, $stageId) {
		parent::SubmissionFilesGridDataProvider(SUBMISSION_FILE_PROOF);

		$this->setStageId($stageId);
		$this->_symbolic = $symbolic;
	}

	/**
	 * Get symbolic.
	 * @return string
	 */
	function getSymbolic() {
		return $this->_symbolic;
	}

	/**
	 * Get user id.
	 * @return int
	 */
	function getUserId() {
		return $this->_userId;
	}

	/**
	 * Set user id.
	 * @param int
	 */
	function setUserId($userId) {
		$this->_userId = $userId;
	}

	//
	// Implement template methods from GridDataProvider
	//
	/**
	 * @see GridHandler::loadData
	 */
	function loadData() {
		$submissionFileSignoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$signoffs = $submissionFileSignoffDao->getAllBySubmission($submission->getId(), $this->getSymbolic(), $this->getUserId());

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$preparedData = array();
		while ($signoff = $signoffs->next()) {
			$submissionFile = $submissionFileDao->getLatestRevision($signoff->getAssocId(), null, $submission->getId());
			$preparedData[$signoff->getId()]['signoff'] = $signoff;
			$preparedData[$signoff->getId()]['submissionFile'] = $submissionFile;
		}

		return $preparedData;
	}

	//
	// Public methods.
	//
	/**
	 * Get link action to add a signoff file. If user has no incomplete
	 * signoffs, return false.
	 * @return mixed boolean or LinkAction
	 */
	function getAddSignoffFile($request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$signoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO'); /* @var $signoffDao SubmissionFileSignoffDAO */
		$signoffFactory = $signoffDao->getAllBySubmission($submission->getId(), $this->getSymbolic(), $this->getUserId(), null, true);

		$action = false;
		if (!$signoffFactory->wasEmpty()) {
			import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
			$action = new AddSignoffFileLinkAction(
				$request, $submission->getId(),
				$this->getStageId(), $this->getSymbolic(), null,
				__('submission.upload.signoff'), __('submission.upload.signoff')
			);
		}

		return $action;
	}
}

?>
