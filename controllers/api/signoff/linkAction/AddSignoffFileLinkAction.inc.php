<?php

/**
 * @file controllers/api/signoff/linkAction/AddSignoffFileLinkAction.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AddSignoffFileLinkAction
 * @ingroup controllers_api_signoff_linkAction
 *
 * @brief Class for signoff file upload actions.
 */

import('lib.pkp.classes.linkAction.LinkAction');

// Bring in SUBMISSION_FILE_* constants
import('lib.pkp.classes.submission.SubmissionFile');

class AddSignoffFileLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionId integer The submission the file should be
	 *  uploaded to.
	 * @param $stageId integer The workflow stage in which the file
	 *  uploader is being instantiated (one of the WORKFLOW_STAGE_ID_*
	 *  constants).
	 * @param $symbolic string The symbolic name of the signoff
	 * @param signoffId The optional ID of the signoff
	 * @param $modalTitle string The modal title
	 * @param $buttonLabel string The link action's button label (null for cell action)
	 */
	function AddSignoffFileLinkAction($request, $submissionId, $stageId, $symbolic, $signoffId = null,
			$modalTitle, $buttonLabel = null) {

		// Create the actionArgs array
		$actionArgs = array();
		$actionArgs['submissionId'] = $submissionId;
		$actionArgs['stageId'] = $stageId;
		$actionArgs['symbolic'] = $symbolic;
		$actionArgs['signoffId'] = $signoffId;
		$actionArgs['fileStage'] = SUBMISSION_FILE_SIGNOFF;

		// Instantiate the file upload modal.
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.WizardModal');
		$modal = new AjaxModal(
			$dispatcher->url(
				$request, ROUTE_COMPONENT, null,
				'informationCenter.SignoffInformationCenterHandler', 'viewNotes',
				null, $actionArgs
			),
			$modalTitle, 'modal_add_file'
		);

		// Configure the link action.
		parent::LinkAction('addSignoff', $modal, $buttonLabel, 'add');
	}
}

?>
