<?php

/**
 * @file controllers/grid/files/signoff/SignoffFilesGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffFilesGridCellProvider
 * @ingroup controllers_grid_files_signoff
 *
 * @brief Cell provider for name column of a signoff (editor/auditor) grid (i.e. editorial/production).
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class SignoffFilesGridCellProvider extends GridCellProvider {
	/** @var int */
	var $_submissionId;

	/** @var int */
	var $_stageId;

	/**
	 * Constructor
	 */
	function SignoffFilesGridCellProvider($submissionId, $stageId) {
		$this->_submissionId = $submissionId;
		$this->_stageId = $stageId;
		parent::GridCellProvider();
	}

	//
	// Getters
	//
	function getSubmissionId() {
		return $this->_submissionId;
	}

	function getStageId() {
		return $this->_stageId;
	}


	//
	// Implemented methods from GridCellProvider.
	//
	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		$actions = array();
		$submissionFile = $row->getData();
		assert(is_a($submissionFile, 'SubmissionFile'));

		switch ($column->getId()) {
			case 'name':
				import('lib.pkp.controllers.grid.files.FileNameGridColumn');
				$fileNameColumn = new FileNameGridColumn(true, WORKFLOW_STAGE_ID_PRODUCTION, true);

				// Set the row data as expected in FileNameGridColumn object.
				$rowData = array('submissionFile' => $submissionFile);
				$row->setData($rowData);
				$actions = $fileNameColumn->getCellActions($request, $row);

				// Back the row data as expected by the signoff grid.
				$row->setData($submissionFile);
				break;
			case 'approved';
				$actions[] = $this->_getApprovedCellAction($request, $submissionFile, $this->getCellState($row, $column));
				break;
		}
		return $actions;
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$columnId = $column->getId();
		$rowData = $row->getData(); /* @var $rowData SubmissionFile */
		assert(is_a($rowData, 'SubmissionFile') && !empty($columnId));
		switch ($columnId) {
			case 'name':
				// The cell will contain only a link action. See getCellActions().
				return array('status' => '', 'label' => '');
			case 'approved':
				return array('status' => $this->getCellState($row, $column));
			default:
				return array('status' => '');
		}
	}


	function getCellState($row, $column) {
		$columnId = $column->getId();
		$rowData =& $row->getData();
		switch ($columnId) {
			case 'approved':
				return $rowData->getViewable()?'completed':'new';
			default:
				return '';
		}
	}

	/**
	 * Get the approved column cell action, based on stage id.
	 * @param $request Request
	 * @param $submissionFile SubmissionFile
	 */
	function _getApprovedCellAction($request, $submissionFile, $cellState) {
		$router = $request->getRouter();
		$actionArgs = array(
			'submissionId' => $submissionFile->getSubmissionId(),
			'fileId' => $submissionFile->getFileId(),
			'stageId' => $this->getStageId()
		);
		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

		if ($cellState == 'new') {
			$approveLabel = __('grid.action.approve');
		} else {
			$approveLabel = __('grid.action.disapprove');
		}

		switch ($this->getStageId()) {
			case WORKFLOW_STAGE_ID_PRODUCTION:
				$remoteActionUrl = $router->url(
					$request, null, 'modals.editorDecision.EditorDecisionHandler',
					'saveApproveProof', null, $actionArgs
				);

				if ($cellState == 'new') {
					$approveText = __('editor.submission.decision.approveProofsDescription');
				} else {
					$approveText = __('editor.submission.decision.disapproveProofsDescription');
				}

				$modal = new RemoteActionConfirmationModal($approveText, $approveLabel,
					$remoteActionUrl, 'modal_approve_file');

				$toolTip = ($cellState == 'completed') ? __('grid.action.pageProofApproved') : null;
				return new LinkAction('approveProof-' . $submissionFile->getFileId(),
					$modal, $approveLabel, 'task ' . $cellState);
			default:
				assert(false);
		}
	}
}

?>
