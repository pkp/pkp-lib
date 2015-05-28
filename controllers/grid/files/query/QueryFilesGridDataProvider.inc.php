<?php

/**
 * @file controllers/grid/files/query/QueryFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryFilesGridDataProvider
 * @ingroup controllers_grid_files_query
 *
 * @brief Provide access to query files management.
 */


import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

class QueryFilesGridDataProvider extends SubmissionFilesGridDataProvider {
	/** @var integer Query ID */
	var $_queryId;

	/**
	 * Constructor
	 */
	function QueryFilesGridDataProvider() {
		parent::SubmissionFilesGridDataProvider(SUBMISSION_FILE_QUERY);
	}

	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc GridDataProvider::getAuthorizationPolicy()
	 */
	function getAuthorizationPolicy($request, $args, $roleAssignments) {
		$this->setUploaderRoles($roleAssignments);

		import('lib.pkp.classes.security.authorization.QueryAccessPolicy');
		return new QueryAccessPolicy($request, $args, $roleAssignments, $this->getStageId());
	}

	/**
	 * @copydoc FilesGridDataProvider::getSelectAction()
	 */
	function getSelectAction($request) {
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');
		return new SelectFilesLinkAction(
			$request,
			array(
				'submissionId' => $this->getSubmission()->getId(),
				'stageId' => $this->getStageId(),
				'queryId' => $query->getId(),
			),
			__('editor.submission.uploadSelectFiles')
		);
	}

	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadData() {
		// Retrieve all submission files for the given file query.
		$submission = $this->getSubmission();
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisionsByAssocId(ASSOC_TYPE_QUERY, $query->getId(), $submission->getId(), $this->getFileStage());
		return $this->prepareSubmissionFileData($submissionFiles, $this->_viewableOnly);
	}

	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		return array_merge(
			parent::getRequestArgs(),
			array(
				'assocType' => ASSOC_TYPE_QUERY,
				'assocId' => $query->getId(),
				'queryId' => $query->getId(),
			)
		);
	}

	/**
	 * @copydoc FilesGridDataProvider::getAddFileAction()
	 */
	function getAddFileAction($request) {
		$submission = $this->getSubmission();
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		return new AddFileLinkAction(
			$request, $submission->getId(), $this->getStageId(),
			$this->getUploaderRoles(), null, $this->getFileStage(),
			ASSOC_TYPE_QUERY, $query->getId()
		);
	}
}

?>
