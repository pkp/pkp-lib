<?php

/**
 * @file controllers/grid/files/form/ManageSubmissionFilesForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageSubmissionFilesForm
 * @ingroup controllers_grid_files_form
 *
 * @brief Form for add or removing files from a review
 */

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.submission.SubmissionFile');

class ManageSubmissionFilesForm extends Form {
	/** @var int **/
	var $_submissionId;

	/**
	 * Constructor.
	 * @param $submissionId int Submission ID
	 * @param $template string Template filename
	 */
	function ManageSubmissionFilesForm($submissionId, $template) {
		parent::Form($template);
		$this->_submissionId = (int)$submissionId;

		$this->addCheck(new FormValidatorPost($this));
	}


	//
	// Getters / Setters
	//
	/**
	 * Get the submission id
	 * @return int
	 */
	function getSubmissionId() {
		return $this->_submissionId;
	}

	//
	// Overridden template methods
	//
	/**
	 * Initialize variables
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$this->setData('submissionId', $this->_submissionId);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('selectedFiles'));
	}

	/**
	 * Save review round files
	 * @param $args array
	 * @param $request PKPRequest
	 * @stageSubmissionFiles array The files that belongs to a file stage
	 * that is currently being used by a grid inside this form.
	 */
	function execute($args, $request, $stageSubmissionFiles, $fileStage) {
		$selectedFiles = (array)$this->getData('selectedFiles');
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFiles = $submissionFileDao->getLatestRevisions($this->getSubmissionId());

		foreach ($submissionFiles as $submissionFile) {
			// Get the viewable flag value.
			$isViewable = in_array(
				$submissionFile->getFileId(),
				$selectedFiles);

			// If this is a submission file that belongs to the current stage id...
			if (array_key_exists($submissionFile->getFileId(), $stageSubmissionFiles)) {
				// ...update the "viewable" flag accordingly.
				$submissionFile->setViewable($isViewable);
			} else {
				// If the viewable flag is set to true...
				if ($isViewable) {
					// Make a copy of the file to the current file stage.
					import('lib.pkp.classes.file.SubmissionFileManager');
					$context = $request->getContext();
					$submissionFileManager = new SubmissionFileManager($context->getId(), $submissionFile->getSubmissionId());
					// Split the file into file id and file revision.
					$fileId = $submissionFile->getFileId();
					$revision = $submissionFile->getRevision();
					list($newFileId, $newRevision) = $submissionFileManager->copyFileToFileStage($fileId, $revision, $fileStage, null, true);
					if ($fileStage == SUBMISSION_FILE_REVIEW_FILE) {
						$submissionFileDao->assignRevisionToReviewRound($newFileId, $newRevision, $this->getReviewRound());
					}
					$submissionFile = $submissionFileDao->getRevision($newFileId, $newRevision);
				}
			}
			$submissionFileDao->updateObject($submissionFile);
		}
	}
}

?>
