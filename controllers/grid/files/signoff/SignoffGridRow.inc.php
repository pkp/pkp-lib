<?php

/**
 * @file controllers/grid/files/signoff/SignoffGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffGridRow
 * @ingroup controllers_grid_files_signoff
 *
 * @brief A row containing a Signoff as its data.
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class SignoffGridRow extends GridRow {
	/** @var integer */
	var $_stageId;

	/**
	 * Constructor
	 */
	function SignoffGridRow($stageId) {
		$this->_stageId = (int)$stageId;
		parent::GridRow();
	}

	//
	// Overridden template methods
	//
	/*
	 * Configure the grid row
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Is this a new row or an existing row?
		$rowId = $this->getId();

		// Get the signoff (the row)
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoff = $signoffDao->getById($rowId);

		// Get the id of the original file (the category header)
		$submissionFileId = $signoff->getAssocId();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFile = $submissionFileDao->getLatestRevision($submissionFileId);
		$submissionDao = Application::getSubmissionDAO();
		$submissionId = $submissionFile->getSubmissionId();
		$copyeditedFileId = $signoff->getFileId();

		$user = $request->getUser();

		if (!empty($rowId) && is_numeric($rowId)) {
			// Actions
			$router = $request->getRouter();
			$actionArgs = array_merge($this->getRequestArgs(),
				array('signoffId' => $rowId));

			// Add the history action.
			import('lib.pkp.controllers.informationCenter.linkAction.ReadSignoffHistoryLinkAction');
			$this->addAction(new ReadSignoffHistoryLinkAction($request, $rowId, $submissionId, $this->getStageId()));

			// Add the delete signoff if it isn't completed yet.
			if (!$signoff->getDateCompleted()) {
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
				$this->addAction(new LinkAction(
					'deleteSignoff',
					new RemoteActionConfirmationModal(
						__('common.confirmDelete'), __('common.delete'),
						$router->url(
							$request, null, null, 'deleteSignoff',
							null, array_merge(array(
								'submissionId' => $submissionId,
								'stageId' => $this->getStageId(),
								'signoffId' => $rowId,
								'fileId' => $copyeditedFileId
							), $this->getRequestArgs())
						),
						'modal_delete'
					),
					__('grid.copyediting.deleteSignoff'),
					'delete'
				));
			}

			// If signoff has not been completed, allow the user to upload if it is their signoff (i.e. their copyediting assignment)
			if (!$signoff->getDateCompleted() && $signoff->getUserId() == $user->getId()) {
				if ($signoff->getUserId() == $user->getId()) {
					import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
					$this->addAction(new AddSignoffFileLinkAction(
						$request, $submissionId,
						$this->getStageId(), $signoff->getSymbolic(), $signoff->getId(),
						__('submission.upload.signoff'), __('submission.upload.signoff')));
				}
			}
		}
	}

	//
	// Getters
	//
	/**
	 * Get the workflow stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}
}

?>
