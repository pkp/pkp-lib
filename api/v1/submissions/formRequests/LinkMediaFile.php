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

    public function rules(): array
    {
        return [
            'targetSubmissionFileId' => ['required', 'integer'],
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
                $targetFileId = (int) $this->input('targetSubmissionFileId');

                if ($targetFileId === $sourceFile->getId()) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.400.cannotLinkToSelf'));
                    return;
                }

                $targetFile = Repo::submissionFile()->get($targetFileId);
                if (!$targetFile) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.404.resourceNotFound'));
                    return;
                }

                if ($targetFile->getData('submissionId') !== $submission->getId()) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.400.targetNotInSubmission'));
                    return;
                }

                if ($targetFile->getData('fileStage') !== SubmissionFile::SUBMISSION_FILE_MEDIA) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.400.targetNotMediaFile'));
                    return;
                }

                $sourceVariantType = MediaVariantType::tryFrom($sourceFile->getData('variantType') ?? '');
                $targetVariantType = MediaVariantType::tryFrom($targetFile->getData('variantType') ?? '');

                if (!$sourceVariantType || !$targetVariantType) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.400.variantTypeRequired'));
                    return;
                }

                if ($sourceVariantType === $targetVariantType) {
                    $validator->errors()->add('targetSubmissionFileId', __('api.submissionFiles.400.cannotLinkSameVariantType'));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'targetSubmissionFileId.required' => __('api.400.missingRequired', ['param' => 'targetSubmissionFileId']),
        ];
    }

    public function validated($key = null, $default = null)
    {
        return [
            'submission' => $this->getSubmission(),
            'sourceFile' => $this->getSubmissionFile(),
            'targetFile' => Repo::submissionFile()->get((int) $this->input('targetSubmissionFileId')),
        ];
    }
}
