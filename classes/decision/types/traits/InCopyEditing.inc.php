<?php

/**
 * @file classes/decision/types/traits/InExternalReviewRound.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions taken when backing out from the copy editing stage
 */

namespace PKP\decision\types\traits;

use APP\submission\Submission;
use PKP\components\fileAttachers\FileStage;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\submissionFile\SubmissionFile;

trait InCopyEditing
{
    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    /**
     * Get the submission file stages that are permitted to be attached to emails
     * sent in this decision
     *
     * @return array<int>
     */
    protected function getAllowedAttachmentFileStages(): array
    {
        return [
            SubmissionFile::SUBMISSION_FILE_FINAL,
        ];
    }

    /**
     * Get the file attacher components supported for emails in this decision
     */
    protected function getFileAttachers(Submission $submission, Context $context): array
    {
        $attachers = [
            new Upload(
                $context,
                __('common.upload.addFile'),
                __('common.upload.addFile.description'),
                __('common.upload.addFile')
            ),
        ];

        $attachers[] = (new FileStage(
            $context,
            $submission,
            __('submission.submit.submissionFiles'),
            __('email.addAttachment.submissionFiles.submissionDescription'),
            __('email.addAttachment.submissionFiles.attach')
        ))
            ->withFileStage(
                SubmissionFile::SUBMISSION_FILE_FINAL,
                __('submission.finalDraft')
            );

        $attachers[] = new Library(
            $context,
            $submission
        );

        return $attachers;
    }
}
