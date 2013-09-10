<?php

/**
 * @file controllers/grid/files/fileSignoff/AuthorSignoffFilesGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorSignoffFilesGridHandler
 * @ingroup controllers_grid_files_fileSignoff
 *
 * @brief Display the files that the user has been asked to signoff on
 *
 * N.B. This grid does NOT extend the SubmissionFilesGrid, but uses the FileNameGridColumn
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.files.fileSignoff.AuthorSignoffFilesGridRow');

class AuthorSignoffFilesGridHandler extends GridHandler {

	/**
	 * Constructor
	 * @param $symbolic string The signoff symbolic string
	 */
	function AuthorSignoffFilesGridHandler($stageId, $symbolic) {
		import('lib.pkp.controllers.grid.files.fileSignoff.AuthorSignoffFilesGridDataProvider');
		parent::GridHandler(new AuthorSignoffFilesGridDataProvider($symbolic, $stageId));

		$this->addRoleAssignment(
			array(ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow', 'signOffFiles')
		);
	}
	//
	// Getters
	//
	/**
	 * Get the Stage Id.
	 * @return int
	 */
	function getStageId() {
		$dataProvider = $this->getDataProvider();
		return $dataProvider->getStageId();
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$user = $request->getUser();

		$gridDataProvider = $this->getDataProvider(); /* @var $gridDataProvider AuthorSignoffFilesGridDataProvider */
		$gridDataProvider->setUserId($user->getId());

		$addSignoffFileLinkAction = $gridDataProvider->getAddSignoffFile($request);
		if ($addSignoffFileLinkAction) {
			$this->addAction($addSignoffFileLinkAction);
		}

		// The file name column is common to all file grid types.
		import('lib.pkp.controllers.grid.files.FileNameGridColumn');
		$this->addColumn(new FileNameGridColumn(true, $this->getStageId()));

		import('lib.pkp.controllers.grid.files.fileSignoff.AuthorSignoffFilesGridCellProvider');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$cellProvider = new AuthorSignoffFilesGridCellProvider($submission, $this->getStageId());

		// Add a column to show whether the author uploaded a copyedited version of the file
		$this->addColumn(
			new GridColumn(
				'response',
				'submission.response',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT))
		);
	}

	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		return new AuthorSignoffFilesGridRow($this->getStageId());
	}
}

?>
