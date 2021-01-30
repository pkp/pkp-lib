<?php

/**
 * @file controllers/api/file/linkAction/AddFileLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddFileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to add a submission file.
 */

import('lib.pkp.controllers.api.file.linkAction.BaseAddFileLinkAction');

class AddFileLinkAction extends BaseAddFileLinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionId integer The submission the file should be
	 *  uploaded to.
	 * @param $stageId integer The workflow stage in which the file
	 *  uploader is being instantiated (one of the WORKFLOW_STAGE_ID_*
	 *  constants).
	 * @param $uploaderRoles array The ids of all roles allowed to upload
	 *  in the context of this action.
	 * @param $fileStage integer The file stage the file should be
	 *  uploaded to (one of the SUBMISSION_FILE_* constants).
	 * @param $assocType integer The type of the element the file should
	 *  be associated with (one fo the ASSOC_TYPE_* constants).
	 * @param $assocId integer The id of the element the file should be
	 *  associated with.
	 * @param $reviewRoundId int The current review round ID (if any)
	 * @param $revisedFileId int Revised file ID, if any
	 * @param $dependentFilesOnly bool whether to only include dependent
	 *  files in the Genres dropdown.
	 * @param $queryId int The query id. Use when the assoc details point
	 *  to a note
	 */
	function __construct($request, $submissionId, $stageId, $uploaderRoles,
			$fileStage, $assocType = null, $assocId = null, $reviewRoundId = null, $revisedFileId = null, $dependentFilesOnly = false, $queryId = null) {

		// Create the action arguments array.
		$actionArgs = array('fileStage' => $fileStage, 'reviewRoundId' => $reviewRoundId);
		if (is_numeric($assocType) && is_numeric($assocId)) {
			$actionArgs['assocType'] = (int)$assocType;
			$actionArgs['assocId'] = (int)$assocId;
		}
		if ($revisedFileId) {
			$actionArgs['revisedFileId'] = $revisedFileId;
			$actionArgs['revisionOnly'] = true;
		}
		if ($dependentFilesOnly) $actionArgs['dependentFilesOnly'] = true;

		if ($queryId) {
			$actionArgs['queryId'] = $queryId;
		}

		// Identify text labels based on the file stage.
		$textLabels = AddFileLinkAction::_getTextLabels($fileStage);

		// Call the parent class constructor.
		parent::__construct(
			$request, $submissionId, $stageId, $uploaderRoles, $actionArgs,
			__($textLabels['wizardTitle']), __($textLabels['buttonLabel'])
		);
	}


	//
	// Private methods
	//
	/**
	 * Static method to return text labels
	 * for upload to different file stages.
	 *
	 * @param $fileStage integer One of the
	 *  SUBMISSION_FILE_* constants.
	 * @return array
	 */
	static function _getTextLabels($fileStage) {
		static $textLabels = array(
			SUBMISSION_FILE_SUBMISSION => array(
				'wizardTitle' => 'submission.submit.uploadSubmissionFile',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_REVIEW_FILE => array(
				'wizardTitle' => 'editor.submissionReview.uploadFile',
				'buttonLabel' => 'editor.submissionReview.uploadFile'
			),
			SUBMISSION_FILE_INTERNAL_REVIEW_FILE => array(
				'wizardTitle' => 'editor.submissionReview.uploadFile',
				'buttonLabel' => 'editor.submissionReview.uploadFile'
			),
			SUBMISSION_FILE_REVIEW_ATTACHMENT => array(
				'wizardTitle' => 'editor.submissionReview.uploadAttachment',
				'buttonLabel' => 'editor.submissionReview.uploadAttachment'
			),
			SUBMISSION_FILE_ATTACHMENT => array(
				'wizardTitle' => 'editor.submissionReview.uploadFile',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_REVIEW_REVISION => array(
				'wizardTitle' => 'editor.submissionReview.uploadFile',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_INTERNAL_REVIEW_REVISION => array(
				'wizardTitle' => 'editor.submissionReview.uploadFile',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_FINAL => array(
				'wizardTitle' => 'submission.upload.finalDraft',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_COPYEDIT => array(
				'wizardTitle' => 'submission.upload.copyeditedVersion',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_PRODUCTION_READY => array(
				'wizardTitle' => 'submission.upload.productionReady',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_PROOF => array(
				'wizardTitle' => 'submission.upload.proof',
				'buttonLabel' => 'submission.changeFile'
			),
			SUBMISSION_FILE_DEPENDENT => array(
				'wizardTitle' => 'submission.upload.dependent',
				'buttonLabel' => 'submission.addFile'
			),
			SUBMISSION_FILE_QUERY => array(
				'wizardTitle' => 'submission.upload.query',
				'buttonLabel' => 'submission.addFile'
			),
		);

		assert(isset($textLabels[$fileStage]));
		return $textLabels[$fileStage];
	}
}


