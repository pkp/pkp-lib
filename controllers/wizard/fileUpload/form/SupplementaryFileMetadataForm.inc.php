<?php
/**
 * @devgroup controllers_wizard_fileUpload_form
 */

/**
 * @file controllers/wizard/fileUpload/form/SupplementaryFileMetadataForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SupplementaryFileMetadataForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for editing artwork file metadata.
 */

import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesMetadataForm');

class SupplementaryFileMetadataForm extends SubmissionFilesMetadataForm {
	/**
	 * Constructor.
	 * @param $submissionFile SubmissionFile
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $reviewRound ReviewRound (optional) Current review round, if any.
	 */
	function SupplementaryFileMetadataForm($submissionFile, $stageId, $reviewRound = null) {
		parent::SubmissionFilesMetadataForm($submissionFile, $stageId, $reviewRound, 'controllers/wizard/fileUpload/form/supplementaryFileMetadataForm.tpl');
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'creator',
		));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($args, $request) {
		// Update the sumbission file by reference.
		$submissionFile = $this->getSubmissionFile();
		$submissionFile->setCreator($this->getData('creator'), null); // Localized

		// Persist the submission file.
		parent::execute($args, $request);
	}
}

?>
