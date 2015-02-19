<?php

/**
 * @file controllers/grid/files/proof/AuthorProofingSignoffFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorProofingSignoffFilesGridHandler
 * @ingroup controllers_grid_files_proof
 *
 * @brief Display the files the author has been asked to sign off for proofing.
 */

import('lib.pkp.classes.controllers.grid.CategoryGridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class AuthorProofingSignoffFilesGridHandler extends CategoryGridHandler {
	/**
	 * Constructor
	 */
	function AuthorProofingSignoffFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.proof.AuthorProofingSignoffFilesCategoryGridDataProvider');
		parent::CategoryGridHandler(new AuthorProofingSignoffFilesCategoryGridDataProvider());

		$this->addRoleAssignment(
			array(ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchCategory', 'fetchRow')
		);
	}

	/**
	 * @see GridHandler::initialize($request, $args)
	 */
	function initialize($request, $args) {
		parent::initialize($request);

		$dataProvider = $this->getDataProvider();
		$user = $request->getUser();
		$dataProvider->setUserId($user->getId());

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$addSignoffFileLinkAction = $dataProvider->getAddSignoffFile($request);
		if ($addSignoffFileLinkAction) {
			$this->addAction($addSignoffFileLinkAction);
		}

		// The file name column is common to all file grid types.
		import('lib.pkp.controllers.grid.files.FileNameGridColumn');
		$this->addColumn(new FileNameGridColumn(true, WORKFLOW_STAGE_ID_PRODUCTION));

		import('lib.pkp.controllers.grid.files.fileSignoff.AuthorSignoffFilesGridCellProvider');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$cellProvider = new AuthorSignoffFilesGridCellProvider($submission, WORKFLOW_STAGE_ID_PRODUCTION);

		// Add a column to show whether the author uploaded a signoff.
		$this->addColumn(
			new GridColumn(
				'response',
				'submission.response',
				null,
				null,
				$cellProvider)
		);

		// Set the grid title.
		$this->setTitle('submission.pageProofs');
	}

	/**
	 * @see GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.files.fileSignoff.AuthorSignoffFilesGridRow');
		return new AuthorSignoffFilesGridRow(WORKFLOW_STAGE_ID_PRODUCTION);
	}

	/**
	 * @see CategoryGridHandler::getCategoryRowInstance()
	 */
	function getCategoryRowInstance() {
		import('lib.pkp.controllers.grid.files.proof.AuthorProofingGridCategoryRow');
		return new AuthorProofingGridCategoryRow();
	}
}

?>
