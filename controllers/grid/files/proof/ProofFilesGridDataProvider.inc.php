<?php

/**
 * @file controllers/grid/files/final/ProofFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProofFilesGridDataProvider
 * @ingroup controllers_grid_files_final
 *
 * @brief Provide access to proof files management.
 */


import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

class ProofFilesGridDataProvider extends SubmissionFilesGridDataProvider {
	/**
	 * Constructor
	 */
	function ProofFilesGridDataProvider() {
		parent::SubmissionFilesGridDataProvider(SUBMISSION_FILE_PROOF);
	}


	//
	// Getters/setters
	//
	/**
	 * Get the representation associated with this grid
	 * @return Representation
	 */
	function getRepresentation() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION);
	}


	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$representation = $this->getRepresentation();
		return array_merge(
			parent::getRequestArgs(),
			array(
				'representationId' => $representation->getId(),
				'assocType' => ASSOC_TYPE_REPRESENTATION,
				'assocId' => $representation->getId(),
			)
		);
	}

	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadData() {
		// Retrieve all submission files for the given file stage.
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisionsByAssocId(
			ASSOC_TYPE_REPRESENTATION,
			$this->getRepresentation()->getId(),
			$submission->getId(),
			$this->getFileStage()
		);

		return $this->prepareSubmissionFileData($submissionFiles, $this->_viewableOnly);
	}

	/**
	 * @copydoc FilesGridDataProvider::getSelectAction()
	 */
	function getSelectAction($request) {
		import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');
		return new SelectFilesLinkAction(
			$request,
			$this->getRequestArgs(),
			__('editor.submission.selectFiles')
		);
	}

	/**
	 * @copydoc FilesGridDataProvider::getAddFileAction()
	 */
	function getAddFileAction($request) {
		$submission = $this->getSubmission();
		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		return new AddFileLinkAction(
			$request, $submission->getId(), $this->getStageId(),
			$this->getUploaderRoles(), null, $this->getFileStage(),
			ASSOC_TYPE_REPRESENTATION, $this->getRepresentation()->getId()
		);
	}
}

?>
