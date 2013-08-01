<?php

/**
 * @file controllers/grid/files/SubmissionFilesGridRow.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesGridRow
 * @ingroup controllers_grid_files
 *
 * @brief Handle submission file grid row requests.
 */

// Import grid base classes.
import('lib.pkp.classes.controllers.grid.GridRow');

class SubmissionFilesGridRow extends GridRow {

	/** @var boolean */
	var $_canDelete;

	/** @var boolean */
	var $_canViewNotes;

	/** @var int */
	var $_stageId;

	/**
	 * Constructor
	 * $canDelete boolean
	 * $canViewNotes boolean
	 * $stageId int (optional)
	 */
	function SubmissionFilesGridRow($canDelete, $canViewNotes, $stageId = null) {
		$this->_canDelete = $canDelete;
		$this->_canViewNotes = $canViewNotes;
		$this->_stageId = $stageId;
		parent::GridRow();
	}


	//
	// Getters and Setters
	//
	/**
	 * Can the user delete files from this grid?
	 * @return boolean
	 */
	function canDelete() {
		return $this->_canDelete;
	}

	/**
	 * Can the user view file notes on this grid?
	 * @return boolean
	 */
	function canViewNotes() {
		return $this->_canViewNotes;
	}

	/**
	 * Get the stage id, if any.
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	//
	// Overridden template methods from GridRow
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request, $template = 'controllers/grid/gridRow.tpl') {
		parent::initialize($request, $template);

		// Retrieve the submission file.
		$submissionFileData =& $this->getData();
		assert(isset($submissionFileData['submissionFile']));
		$submissionFile =& $submissionFileData['submissionFile']; /* @var $submissionFile SubmissionFile */
		assert(is_a($submissionFile, 'SubmissionFile'));

		// File grid row actions:
		// 1) Delete file action.
		if ($this->canDelete()) {
			import('lib.pkp.controllers.api.file.linkAction.DeleteFileLinkAction');
			$this->addAction(new DeleteFileLinkAction($request, $submissionFile, $this->getStageId()));
		}

		// 2) Information center action.
		if ($this->canViewNotes()) {
			import('lib.pkp.controllers.informationCenter.linkAction.FileInfoCenterLinkAction');
			$this->addAction(new FileInfoCenterLinkAction($request, $submissionFile, $this->getStageId()));
		}
	}
}

?>
