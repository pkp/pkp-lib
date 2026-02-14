<?php

/**
 * @file api/v1/submissions/formRequests/MediaFileValidationTrait.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait MediaFileValidationTrait
 *
 * @brief Shared validation helpers for media submission file FormRequests.
 */

namespace PKP\API\v1\submissions\formRequests;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use PKP\core\PKPBaseController;
use PKP\submissionFile\SubmissionFile;

trait MediaFileValidationTrait
{
    /**
     * Helper function for getting underlying API controller
     */
    protected function getBaseApiController(): PKPBaseController
    {
        return Application::get()->getRequest()->getRouter()->getHandler()->getApiController();
    }

    /**
     * Returns authorized context object Submission
     */
    protected function getSubmission(): ?Submission
    {
        return $this->getBaseApiController()->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Returns authorized context object SubmissionFile
     */
    protected function getSubmissionFile(): ?SubmissionFile
    {
        return $this->getBaseApiController()->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
    }

    /**
     * Validate that a file belongs to the submission and is in the media file stage.
     *
     * @return string|null Error message if invalid, null if valid
     */
    protected function validateMediaFileOwnership(SubmissionFile $file, $submission): ?string
    {
        if ($file->getData('submissionId') !== $submission->getId()) {
            return __('api.submissionFiles.400.targetNotInSubmission');
        }

        if ($file->getData('fileStage') !== SubmissionFile::SUBMISSION_FILE_MEDIA) {
            return __('api.submissionFiles.400.targetNotMediaFile');
        }

        return null;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], Response::HTTP_BAD_REQUEST));
    }
}
