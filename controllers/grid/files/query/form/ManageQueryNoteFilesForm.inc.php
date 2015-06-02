<?php

/**
 * @file controllers/grid/files/query/form/ManageQueryNoteFilesForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageQueryNoteFilesForm
 * @ingroup controllers_grid_files_query
 *
 * @brief Form to add files to the query files grid
 */

import('lib.pkp.controllers.grid.files.form.ManageSubmissionFilesForm');

class ManageQueryNoteFilesForm extends ManageSubmissionFilesForm {
	/** @var int Query ID */
	var $_queryId;

	/** @var int Note ID */
	var $_noteId;

	/**
	 * Constructor.
	 * @param $submissionId int Submission ID.
	 * @param $queryId int Query ID.
	 * @param $noteId int Note ID.
	 */
	function ManageQueryNoteFilesForm($submissionId, $queryId, $noteId) {
		parent::ManageSubmissionFilesForm($submissionId, 'controllers/grid/files/query/manageQueryNoteFiles.tpl');
		$this->_queryId = $queryId;
		$this->_noteId = $noteId;
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'queryId' => $this->_queryId,
			'noteId' => $this->_noteId,
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save selection of query files
	 * @param $args array
	 * @param $request PKPRequest
	 * @return array a list of all submission files marked as available to queries.
	 */
	function execute($args, $request, $stageSubmissionFiles) {
		parent::execute($args, $request, $stageSubmissionFiles, SUBMISSION_FILE_QUERY);
	}

	/**
	 * Determine if a file is already present in the stage.
	 * @param $submissionFile SubmissionFile The submission file
	 * @param $stageSubmissionFiles array The list of submission files in the stage.
	 * @param $fileStage int FILE_STAGE_...
	 */
	protected function _fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage) {
		if (!parent::_fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)) return false;
		foreach ($stageSubmissionFiles[$submissionFile->getFileId()] as $stageFile) {
			if (
				$stageFile->getFileStage() == $submissionFile->getFileStage() &&
				$stageFile->getFileStage() == $fileStage &&
				($stageFile->getAssocType() != ASSOC_TYPE_NOTE || $stageFile->getAssocId() == $this->_noteId)
			) return true;
		}
		return false;
	}

	/**
	 * @copydoc ManageSubmissionFilesForm::_importFile()
	 */
	protected function _importFile($context, $submissionFile, $fileStage) {
		$submissionFile = parent::_importFile($context, $submissionFile, $fileStage);
		$submissionFile->setAssocType(ASSOC_TYPE_NOTE);
		$submissionFile->setAssocId($this->_noteId);
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFileDao->updateObject($submissionFile);
		return $submissionFile;
	}
}

?>
