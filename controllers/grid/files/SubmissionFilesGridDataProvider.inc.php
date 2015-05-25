<?php
/**
 * @file controllers/grid/files/SubmissionFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFilesGridDataProvider
 * @ingroup controllers_grid_files
 *
 * @brief Provide access to submission file data for grids.
 */


import('lib.pkp.controllers.grid.files.FilesGridDataProvider');

class SubmissionFilesGridDataProvider extends FilesGridDataProvider {

	/** @var integer */
	var $_stageId;

	/** @var integer */
	var $_fileStage;


	/**
	 * Constructor
	 * @param $fileStage integer One of the SUBMISSION_FILE_* constants.
	 * @param $viewableOnly boolean True iff only viewable files should be included.
	 */
	function SubmissionFilesGridDataProvider($fileStage, $viewableOnly = false) {
		assert(is_numeric($fileStage) && $fileStage > 0);
		$this->_fileStage = (int)$fileStage;
		parent::FilesGridDataProvider();

		$this->setViewableOnly($viewableOnly);
	}


	//
	// Getters and setters.
	//
	/**
	 * Set the workflow stage.
	 * @param $stageId int WORKFLOW_STAGE_ID_...
	 */
	function setStageId($stageId) {
		$this->_stageId = $stageId;
	}

	/**
	 * Get the workflow stage.
	 * @return integer WORKFLOW_STAGE_ID_...
	 */
	function getStageId() {
		return $this->_stageId;
	}


	//
	// Implement template methods from GridDataProvider
	//
	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId(),
			'fileStage' => $this->getFileStage()
		);
	}

	/**
	 * Get the file stage.
	 * @return integer SUBMISSION_FILE_...
	 */
	function getFileStage() {
		return $this->_fileStage;
	}

	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadData() {
		// Retrieve all submission files for the given file stage.
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $this->getFileStage());
		return $this->prepareSubmissionFileData($submissionFiles, $this->_viewableOnly);
	}

	//
	// Implement template methods from GridDataProvider
	//
	/**
	 * @copydoc GridDataProvider::getAuthorizationPolicy()
	 */
	function getAuthorizationPolicy($request, $args, $roleAssignments) {
		$this->setUploaderRoles($roleAssignments);

		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		return new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->getStageId());
	}

	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc FilesGridDataProvider::getAddFileAction()
	 */
	function getAddFileAction($request) {
		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		$submission = $this->getSubmission();
		return new AddFileLinkAction(
			$request, $submission->getId(),
			$this->getStageId(), $this->getUploaderRoles(),
			$this->getUploaderGroupIds(), $this->getFileStage()
		);
	}
}

?>
