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

namespace PKP\controllers\api\file\linkAction;

use PKP\submissionFile\SubmissionFile;

class AddFileLinkAction extends BaseAddFileLinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId The submission the file should be
     *  uploaded to.
     * @param int $stageId The workflow stage in which the file
     *  uploader is being instantiated (one of the WORKFLOW_STAGE_ID_*
     *  constants).
     * @param array $uploaderRoles The ids of all roles allowed to upload
     *  in the context of this action.
     * @param int $fileStage The file stage the file should be
     *  uploaded to (one of the SubmissionFile::SUBMISSION_FILE_* constants).
     * @param int $assocType The type of the element the file should
     *  be associated with (one fo the ASSOC_TYPE_* constants).
     * @param int $assocId The id of the element the file should be
     *  associated with.
     * @param int $reviewRoundId The current review round ID (if any)
     * @param int $revisedFileId Revised file ID, if any
     * @param bool $dependentFilesOnly whether to only include dependent
     *  files in the Genres dropdown.
     * @param int $queryId The query id. Use when the assoc details point
     *  to a note
     */
    public function __construct(
        $request,
        $submissionId,
        $stageId,
        $uploaderRoles,
        $fileStage,
        $assocType = null,
        $assocId = null,
        $reviewRoundId = null,
        $revisedFileId = null,
        $dependentFilesOnly = false,
        $queryId = null
    ) {

        // Create the action arguments array.
        $actionArgs = ['fileStage' => $fileStage, 'reviewRoundId' => $reviewRoundId];
        if (is_numeric($assocType) && is_numeric($assocId)) {
            $actionArgs['assocType'] = (int)$assocType;
            $actionArgs['assocId'] = (int)$assocId;
        }
        if ($revisedFileId) {
            $actionArgs['revisedFileId'] = $revisedFileId;
            $actionArgs['revisionOnly'] = true;
        }
        if ($dependentFilesOnly) {
            $actionArgs['dependentFilesOnly'] = true;
        }

        if ($queryId) {
            $actionArgs['queryId'] = $queryId;
        }

        // Identify text labels based on the file stage.
        $textLabels = AddFileLinkAction::_getTextLabels($fileStage);

        // Call the parent class constructor.
        parent::__construct(
            $request,
            $submissionId,
            $stageId,
            $uploaderRoles,
            $actionArgs,
            __($textLabels['wizardTitle']),
            __($textLabels['buttonLabel'])
        );
    }


    //
    // Private methods
    //
    /**
     * Static method to return text labels
     * for upload to different file stages.
     *
     * @param int $fileStage One of the
     *  SubmissionFile::SUBMISSION_FILE_* constants.
     *
     * @return array
     */
    public static function _getTextLabels($fileStage)
    {
        static $textLabels = [
            SubmissionFile::SUBMISSION_FILE_SUBMISSION => [
                'wizardTitle' => 'submission.submit.uploadSubmissionFile',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE => [
                'wizardTitle' => 'editor.submissionReview.uploadFile',
                'buttonLabel' => 'editor.submissionReview.uploadFile'
            ],
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE => [
                'wizardTitle' => 'editor.submissionReview.uploadFile',
                'buttonLabel' => 'editor.submissionReview.uploadFile'
            ],
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT => [
                'wizardTitle' => 'editor.submissionReview.uploadAttachment',
                'buttonLabel' => 'editor.submissionReview.uploadAttachment'
            ],
            SubmissionFile::SUBMISSION_FILE_ATTACHMENT => [
                'wizardTitle' => 'editor.submissionReview.uploadFile',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION => [
                'wizardTitle' => 'editor.submissionReview.uploadFile',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION => [
                'wizardTitle' => 'editor.submissionReview.uploadFile',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_FINAL => [
                'wizardTitle' => 'submission.upload.finalDraft',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_COPYEDIT => [
                'wizardTitle' => 'submission.upload.copyeditedVersion',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY => [
                'wizardTitle' => 'submission.upload.productionReady',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_PROOF => [
                'wizardTitle' => 'submission.upload.proof',
                'buttonLabel' => 'submission.changeFile'
            ],
            SubmissionFile::SUBMISSION_FILE_DEPENDENT => [
                'wizardTitle' => 'submission.upload.dependent',
                'buttonLabel' => 'submission.addFile'
            ],
            SubmissionFile::SUBMISSION_FILE_QUERY => [
                'wizardTitle' => 'submission.upload.query',
                'buttonLabel' => 'submission.addFile'
            ],
        ];

        assert(isset($textLabels[$fileStage]));
        return $textLabels[$fileStage];
    }
}
