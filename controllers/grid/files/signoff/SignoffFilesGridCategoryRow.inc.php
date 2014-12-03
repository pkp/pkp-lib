<?php

/**
 * @file controllers/grid/files/signoff/SignoffFilesGridCategoryRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffFilesGridCategoryRow
 * @ingroup controllers_grid_files_signoff
 *
 * @brief A category row containing the file that users are asked to signoff on.
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

class SignoffFilesGridCategoryRow extends GridCategoryRow {

	/** @var int */
	var $_stageId;

	/**
	 * Constructor
	 * @param $stageId int (optional)
	 */
	function SignoffFilesGridCategoryRow($stageId = null) {
		$this->_stageId = $stageId;
		parent::GridCategoryRow();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridCategoryRow::initialize()
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		// Do the default initialization
		parent::initialize($request);

		// Is this a new row or an existing row?
		$fileId = $this->getId();
		if (!empty($fileId) && is_numeric($fileId)) {
			$submissionFile = $this->getData();

			// Add the row actions.
			$actionArgs = array(
				'submissionId' => $submissionFile->getSubmissionId(),
				'fileId' => $submissionFile->getFileId(),
				'stageId' => $this->_stageId
			);

			$router = $request->getRouter();

			$this->addAction(
				new LinkAction(
					'history',
					new AjaxModal(
						$router->url($request, null, 'informationCenter.FileInformationCenterHandler', 'viewHistory', null, $actionArgs),
						__('submission.history'),
						'modal_information',
						true
					),
					__('submission.history'),
					'more_info'
				)
			);

			import('lib.pkp.controllers.api.file.linkAction.DeleteFileLinkAction');
			$this->addAction(new DeleteFileLinkAction($request, $submissionFile, $this->_getStageId()));

			import('lib.pkp.controllers.api.file.linkAction.EditFileLinkAction');
			$this->addAction(new EditFileLinkAction($request, $submissionFile, $this->_getStageId()));
		}

		// Set the no-row locale key
		$this->setEmptyCategoryRowText('editor.submission.noAuditRequested');
	}


	//
	// Private helper methods.
	//
	/**
	 * Get stage id.
	 * @return int
	 */
	function _getStageId() {
		return $this->_stageId;
	}
}

?>
