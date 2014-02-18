<?php

/**
 * @file lib/pkp/controllers/grid/files/fileSignoff/AuthorSignoffFilesGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorSignoffFilesGridCellProvider
 * @ingroup controllers_grid_files_authorCopyeditingFiles
 *
 * @brief Cell provider for the response column of a file/signoff grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class AuthorSignoffFilesGridCellProvider extends GridCellProvider {
	/* @var Submission */
	var $_submission;

	/* @var int */
	var $_stageId;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $stageId int
	 */
	function AuthorSignoffFilesGridCellProvider($submission, $stageId) {
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		parent::GridCellProvider();
	}

	/**
	 * Get the submission this provider refers to.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the Stage id.
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the signoff for the row.
	 * @param $row GridRow
	 * @return Signoff
	 */
	function getSignoff($row) {
		$rowData = $row->getData();
		assert(is_a($rowData['signoff'], 'Signoff'));
		return $rowData['signoff'];
	}

	/**
	 * Get the file for the row.
	 * @param $row GridRow
	 * @return SubmissionFile
	 */
	function getSubmissionFile($row) {
		$rowData = $row->getData();
		assert(is_a($rowData['submissionFile'], 'SubmissionFile'));
		return $rowData['submissionFile'];
	}

	/**
	 * Get cell actions associated with this row/column combination
	 * Adds a link to the file if there is an uploaded file present
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		if ($column->getId() == 'response') {
			$signoff = $this->getSignoff($row);
			$submission = $this->getSubmission();
			if (!$signoff->getDateCompleted()) {
				import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
				$addFileAction = new AddSignoffFileLinkAction(
					$request, $submission->getId(),
					$this->getStageId(), $signoff->getSymbolic(), $signoff->getId(),
					__('submission.upload.signoff'), __('submission.upload.signoff')
				);

				// FIXME: This is not ideal.
				$addFileAction->_title = null;
				return array($addFileAction);
			}

			import('lib.pkp.controllers.informationCenter.linkAction.SignoffNotesLinkAction');
			return array(new SignoffNotesLinkAction($request, $signoff, $submission->getId(), $this->getStageId()));
		}

		return parent::getCellActions($request, $row, $column, $position);
	}
}

?>
