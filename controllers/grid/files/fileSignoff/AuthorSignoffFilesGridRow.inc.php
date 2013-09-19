<?php

/**
 * @file lib/pkp/controllers/grid/files/fileSignoff/AuthorSignoffFilesGridRow.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorSignoffFilesGridRow
 * @ingroup controllers_grid_files_fileSignoff
 *
 * @brief Author's view of files that they have been asked to signoff on.
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.files.SubmissionFilesGridRow');

class AuthorSignoffFilesGridRow extends SubmissionFilesGridRow {

	/**
	 * Constructor
	 * @param $stageId int
	 */
	function AuthorSignoffFilesGridRow($stageId) {
		parent::SubmissionFilesGridRow(false, false, $stageId);
	}


	//
	// Overridden template methods from GridRow
	//
	/**
	 * @see GridRow::initialize
	 */
	function initialize($request) {
		parent::initialize($request);

		// Get this row's signoff
		$rowData = $this->getData();
		$signoff = $rowData['signoff'];
		$submissionFile = $rowData['submissionFile'];

		// Get the current user
		$user = $request->getUser();

		// Grid only displays current users' signoffs.
		assert($user->getId() == $signoff->getUserId());

		import('lib.pkp.controllers.informationCenter.linkAction.ReadSignoffHistoryLinkAction');
		$this->addAction(new ReadSignoffHistoryLinkAction($request, $signoff->getId(), $submissionFile->getSubmissionId(), $this->getStageId()));

		if (!$signoff->getDateCompleted()) {
			import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
			$this->addAction(new AddSignoffFileLinkAction(
				$request, $submissionFile->getSubmissionId(),
				$this->getStageId(), $signoff->getSymbolic(), $signoff->getId(),
				__('submission.upload.signoff'), __('submission.upload.signoff')));
		}
	}
}

?>
