<?php

/**
 * @file controllers/grid/files/submissionDocuments/SubmissionDocumentsFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionDocumentsFilesGridDataProvider
 * @ingroup controllers_grid_files_submissionDocuments
 *
 * @brief The base data provider for the submission documents library files grid.
 */

import('lib.pkp.classes.controllers.grid.CategoryGridDataProvider');

class PKPSubmissionDocumentsFilesGridDataProvider extends CategoryGridDataProvider {

	/**
	 * Constructor
	 */
	function PKPSubmissionDocumentsFilesGridDataProvider() {
		parent::CategoryGridDataProvider();
	}


	//
	// Getters and Setters
	//

	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function &getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * @see GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission =& $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
		);
	}

	/**
	 * @see CategoryGridHandler::getCategoryData()
	 */
	function getCategoryData(&$fileType, $filter = null) {

		// Retrieve all library files for the given submission document category.
		$submission =& $this->getSubmission();
		import('lib.pkp.classes.context.LibraryFile');
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$libraryFiles =& $libraryFileDao->getBySubmissionId($submission->getId(), $fileType);

		return $libraryFiles->toAssociativeArray();
	}
}

?>
