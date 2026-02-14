<?php

/**
 * @file api/v1/submissions/formRequests/LinkMediaFile.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LinkMediaFile
 *
 * @brief Handle API request validation for linking a media submission file to another.
 */

namespace PKP\API\v1\submissions\formRequests;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use PKP\submissionFile\enums\MediaVariantType;
use PKP\submissionFile\SubmissionFile;

class LinkMediaFile extends FormRequest
{
    use MediaFileValidationTrait;

    private ?MediaVariantType $sourceVariantType = null;
    private ?MediaVariantType $targetVariantType = null;

    public function rules(): array
    {
        return [
            'targetSubmissionFileId' => ['integer', 'nullable'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->count()) {
                    return;
                }

                $submission = $this->getSubmission();
                $sourceFile = $this->getSubmissionFile();

                $targetFileIdInput = $this->input('targetSubmissionFileId');
                $targetFileId = $targetFileIdInput !== null ? (int) $targetFileIdInput : null;

                if ($targetFileId === $sourceFile->getId()) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.422.cannotLinkToSelf'));
                    return;
                }

                if ($targetFileId !== null) {
                    $targetFile = Repo::submissionFile()->get($targetFileId);
                    if ($targetFile->getData('submissionId') !== $submission->getId()) {
                        $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.422.targetNotInSubmission'));
                        return;
                    }

                    if ($targetFile->getData('fileStage') !== SubmissionFile::SUBMISSION_FILE_MEDIA) {
                        $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.422.targetNotMediaFile'));
                        return;
                    }
                    $this->targetVariantType = MediaVariantType::tryFrom($targetFile->getData('variantType') ?? '');

                    $this->sourceVariantType = MediaVariantType::tryFrom($sourceFile->getData('variantType') ?? '');

                    if (!$this->sourceVariantType || !$this->targetVariantType) {
                        $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.422.variantTypeRequired'));
                        return;
                    }

                    if ($this->sourceVariantType === $this->targetVariantType) {
                        $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.422.cannotLinkSameVariantType'));
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'targetSubmissionFileId.required' => __('api.422.missingRequired', ['param' => 'targetSubmissionFileId']),
        ];
    }

    public function validated($key = null, $default = null)
    {
        return [
            'submission' => $this->getSubmission(),
            'sourceFile' => $this->getSubmissionFile(),
            'targetFile' => $this->input('targetSubmissionFileId') !== null
                ? Repo::submissionFile()->get((int) $this->input('targetSubmissionFileId'))
                : null,
        ];
    }
}
