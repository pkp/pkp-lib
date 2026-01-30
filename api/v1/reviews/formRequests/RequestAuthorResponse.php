<?php

/**
 * @file api/v1/reviews/formRequests/RequestAuthorResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequestAuthorResponse
 *
 * @brief Handle API requests validation for requesting review response from authors.
 *
 */

namespace PKP\API\v1\reviews\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use PKP\context\LibraryFileDAO;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;

class RequestAuthorResponse extends FormRequest
{
    protected ?Submission $submission = null;
    protected ?ReviewRound $reviewRound = null;

    /** @inheritdoc  */
    public function rules(): array
    {
        $this->submission = Repo::submission()->get(
            $this->route('submissionId'),
            $this->get('context')->getId(),
        );

        $this->reviewRound = ReviewRound::find($this->route('reviewRoundId'));

        return [
            'submissionId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->submission || (int)$this->submission->getId() !== (int)$value) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                },
            ],
            'reviewRoundId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->reviewRound || $this->reviewRound->id !== (int)$value) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                }
            ],
            'subject' => [
                'required',
                'string',
            ],

            'body' => [
                'required',
                'string',
            ],

            'locale' => [
                'required',
                'string',
            ],

            'cc' => [
                'nullable',
                'array',
            ],

            'bcc' => [
                'nullable',
                'array',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Get the validation rules that apply after the initial validation.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $attachments = $this->input('attachments');

                foreach ($attachments as $attachment) {
                    $errorMessage = __('email.attachmentNotFound', ['fileName' => $attachment['name'] ?? '']);

                    if (isset($attachment['temporaryFileId'])) {
                        $uploaderId = Application::get()->getRequest()->getUser()->getId();
                        if (!$this->validateTemporaryFileAttachment($attachment['temporaryFileId'], $uploaderId)) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } elseif (isset($attachment['submissionFileId'])) {
                        if (!$this->validateSubmissionFileAttachment((int)$attachment['submissionFileId'], $this->submission, $this->getAllowedAttachmentFileStages())) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } elseif (isset($attachment['libraryFileId'])) {
                        if (!$this->validateLibraryAttachment($attachment['libraryFileId'], $this->submission)) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } else {
                        $validator->errors()->add('attachments', $errorMessage);
                    }
                }
            }
        ];
    }


    /**
     * Validate that a temporary file attachment exists and was uploaded by the given user.
     */
    protected function validateTemporaryFileAttachment(string $temporaryFileId, int $uploaderId): bool
    {
        $temporaryFileManager = new TemporaryFileManager();
        return (bool)$temporaryFileManager->getFile($temporaryFileId, $uploaderId);
    }

    /*
     * Validate that a submission file attachment exists, belongs to the given submission, and is of an allowed file stage.
    */
    protected function validateSubmissionFileAttachment(int $submissionFileId, Submission $submission, array $allowedFileStages): bool
    {
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        return $submissionFile
            && $submissionFile->getData('submissionId') === $submission->getId()
            && in_array($submissionFile->getData('fileStage'), $allowedFileStages);
    }


    /**
     * Get the allowed file stages file attachments.
     */
    protected function getAllowedAttachmentFileStages(): array
    {
        return [
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_SUBMISSION,
        ];
    }

    /**
     * Validate that a library file attachment exists and either is not associated with any submission or belongs to the given submission.
     */
    protected function validateLibraryAttachment(int $libraryFileId, Submission $submission): bool
    {
        /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
        $file = $libraryFileDao->getById($libraryFileId, $submission->getData('contextId'));

        if (!$file) {
            return false;
        }

        return !$file->getSubmissionId() || $file->getSubmissionId() === $submission->getId();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reviewRoundId' => $this->route('reviewRoundId'),
            'submissionId' => $this->route('submissionId'),
        ]);
    }

    /** @inheritdoc  */
    public function validated($key = null, $default = null)
    {
        $request = $this->validator->validated();
        $request['submission'] = $this->submission;
        $request['reviewRound'] = $this->reviewRound;

        return $request;
    }
}
