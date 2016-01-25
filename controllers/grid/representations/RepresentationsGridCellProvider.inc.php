<?php

/**
 * @file controllers/grid/representations/RepresentationsGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationsGridCellProvider
 * @ingroup controllers_grid_representations
 *
 * @brief Base class for a cell provider that can retrieve cell contents for the representations grid.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

// Import class which contains the SUBMISSION_FILE_* constants.
import('lib.pkp.classes.submission.SubmissionFile');

class RepresentationsGridCellProvider extends DataObjectGridCellProvider {

	/** @var int Submission ID */
	var $_submissionId;

	/**
	 * Constructor
	 * @param $submissionId int Submission ID
	 */
	function RepresentationsGridCellProvider($submissionId) {
		parent::DataObjectGridCellProvider();
		$this->_submissionId = $submissionId;
	}


	//
	// Getters and setters.
	//
	/**
	 * Get submission ID.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->_submissionId;
	}


	//
	// Template methods from GridCellProvider
	//
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$data = $row->getData();
		if (is_a($data, 'Representation')) switch ($column->getId()) {
			case 'indent': return array();
			case 'name':
				return array('label' => htmlspecialchars($data->getLocalizedName()));
			case 'isComplete':
				return array('status' => $data->getIsApproved()?'completed':'new');
		} else {
			assert(is_array($data) && isset($data['submissionFile']));
			$proofFile = $data['submissionFile'];
			switch ($column->getId()) {
				case 'name':
					import('lib.pkp.controllers.grid.files.FileNameGridColumn');
					$fileNameGridColumn = new FileNameGridColumn(true, WORKFLOW_STAGE_ID_PRODUCTION);
					return $fileNameGridColumn->getTemplateVarsFromRow($row);
				case 'isComplete':
					return array('status' => $proofFile->getViewable()?'completed':'new');
			}
		}
		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * @see GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column) {
		$data = $row->getData();
		$router = $request->getRouter();
		if (is_a($data, 'Representation')) {
			switch ($column->getId()) {
				case 'name':
					import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
					import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');
					AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);
					return array(
						new AddFileLinkAction(
							$request, $data->getSubmissionId(), WORKFLOW_STAGE_ID_PRODUCTION,
							array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT), null, SUBMISSION_FILE_PROOF,
							ASSOC_TYPE_REPRESENTATION, $data->getId()
						),
						new SelectFilesLinkAction(
							$request,
							array(
								'submissionId' => $data->getSubmissionId(),
								'assocType' => ASSOC_TYPE_REPRESENTATION,
								'assocId' => $data->getId(),
								'representationId' => $data->getId(),
								'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
								'fileStage' => SUBMISSION_FILE_PROOF,
							),
							__('editor.submission.selectFiles')
						)
					);
				case 'isComplete':
					return array(new LinkAction(
						'approveRepresentation',
						new RemoteActionConfirmationModal(
							__($data->getIsApproved()?'grid.catalogEntry.approvedRepresentation.removeMessage':'grid.catalogEntry.approvedRepresentation.message'),
							__('grid.catalogEntry.approvedRepresentation.title'),
							$router->url(
								$request, null, null, 'setApproved', null,
								array(
									'representationId' => $data->getId(),
									'newApprovedState' => $data->getIsApproved()?0:1,
									'submissionId' => $data->getSubmissionId(),
								)
							),
							'modal_approve'
						),
						$data->getIsApproved()?__('submission.catalogEntry'):__('submission.noCatalogEntry'),
						$data->getIsApproved()?'complete':'incomplete',
						__('grid.action.setApproval')
					));
			}
		} else {
			assert(is_array($data) && isset($data['submissionFile']));
			$submissionFile = $data['submissionFile'];
			switch ($column->getId()) {
				case 'name':
					import('lib.pkp.controllers.grid.files.FileNameGridColumn');
					$fileNameColumn = new FileNameGridColumn(true, WORKFLOW_STAGE_ID_PRODUCTION, true);
					return $fileNameColumn->getCellActions($request, $row);
				case 'isComplete':
					AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);
					import('lib.pkp.classes.linkAction.request.AjaxAction');
					return array(new LinkAction(
						$submissionFile->getViewable()?'approved':'not_approved',
						new RemoteActionConfirmationModal(
							__($submissionFile->getViewable()?'editor.submission.proofreading.confirmRemoveCompletion':'editor.submission.proofreading.confirmCompletion'),
							__($submissionFile->getViewable()?'editor.submission.proofreading.revokeProofApproval':'editor.submission.proofreading.approveProof'),
							$router->url(
								$request, null, null, 'setProofFileCompletion',
								null,
								array(
									'submissionId' => $submissionFile->getSubmissionId(),
									'fileId' => $submissionFile->getFileId(),
									'revision' => $submissionFile->getRevision(),
									'approval' => !$submissionFile->getViewable(),
								)
							),
							'modal_approve'
						),
						$submissionFile->getViewable()?__('grid.catalogEntry.availableRepresentation.approved'):__('grid.catalogEntry.availableRepresentation.notApproved')
					));
			}
		}
		return parent::getCellActions($request, $row, $column);
	}
}

?>
