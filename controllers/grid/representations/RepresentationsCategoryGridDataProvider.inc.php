<?php

/**
 * @file controllers/grid/representations/RepresentationsCategoryGridDataProvider.inc.php
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


import('lib.pkp.controllers.grid.files.SubmissionFilesCategoryGridDataProvider');

class RepresentationsCategoryGridDataProvider extends SubmissionFilesCategoryGridDataProvider {
	/**
	 * Constructor
	 */
	function RepresentationsCategoryGridDataProvider() {
		parent::SubmissionFilesCategoryGridDataProvider(SUBMISSION_FILE_PROOF);
		$this->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
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

	/**
	 * Get the submission associated with this grid
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
	 * @see GridHandler::loadData
	 */
	function loadData($request, $filter = null) {
		$submission = $this->getSubmission();
		$publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
		$publicationFormats = $publicationFormatDao->getBySubmissionId($submission->getId());
		return $publicationFormats->toAssociativeArray();
	}

	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadCategoryData($request, $categoryDataElement, $filter = null) {
		assert(is_a($categoryDataElement, 'PublicationFormat'));

		// Retrieve all submission files for the given file stage.
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisionsByAssocId(
			ASSOC_TYPE_REPRESENTATION,
			$categoryDataElement->getId(),
			$submission->getId(),
			$this->getFileStage()
		);

		return $this->getDataProvider()->prepareSubmissionFileData($submissionFiles, false, $filter);
	}
}

?>
