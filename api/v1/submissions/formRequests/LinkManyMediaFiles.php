<?php

/**
 * @file api/v1/submissions/formRequests/LinkManyMediaFiles.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LinkManyMediaFiles
 *
 * @brief Handle API request validation for batch linking media submission files.
 */

namespace PKP\API\v1\submissions\formRequests;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use PKP\submissionFile\enums\MediaVariantType;

class LinkManyMediaFiles extends FormRequest
{
    use MediaFileValidationTrait;

    public function rules(): array
    {
        return [
            'links' => ['required', 'array', 'min:1'],
            'links.*.primarySubmissionFileId' => ['required', 'integer'],
            'links.*.secondarySubmissionFileId' => ['sometimes', 'nullable', 'integer'],
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
                $links = $this->input('links');

                foreach ($links as $index => $linkEntry) {
                    $primaryFile = Repo::submissionFile()->get((int) $linkEntry['primarySubmissionFileId']);
                    if (!$primaryFile) {
                        $validator->errors()->add("links.{$index}.primarySubmissionFileId", __('api.404.resourceNotFound'));
                        continue;
                    }

                    if ($error = $this->validateMediaFileOwnership($primaryFile, $submission)) {
                        $validator->errors()->add("links.{$index}.primarySubmissionFileId", $error);
                        continue;
                    }

                    $secondaryFileId = $linkEntry['secondarySubmissionFileId'] ?? null;
                    if ($secondaryFileId !== null) {
                        if ((int) $secondaryFileId === (int) $linkEntry['primarySubmissionFileId']) {
                            $validator->errors()->add("links.{$index}.secondarySubmissionFileId", __('api.submissionFiles.400.cannotLinkToSelf'));
                            continue;
                        }

                        $secondaryFile = Repo::submissionFile()->get((int) $secondaryFileId);
                        if (!$secondaryFile) {
                            $validator->errors()->add("links.{$index}.secondarySubmissionFileId", __('api.404.resourceNotFound'));
                            continue;
                        }

                        if ($error = $this->validateMediaFileOwnership($secondaryFile, $submission)) {
                            $validator->errors()->add("links.{$index}.secondarySubmissionFileId", $error);
                            continue;
                        }

                        $primaryVariantType = MediaVariantType::tryFrom($primaryFile->getData('variantType') ?? '');
                        $secondaryVariantType = MediaVariantType::tryFrom($secondaryFile->getData('variantType') ?? '');

                        if (!$primaryVariantType || !$secondaryVariantType) {
                            $validator->errors()->add("links.{$index}", __('api.submissionFiles.400.variantTypeRequired'));
                            continue;
                        }

                        if ($primaryVariantType === $secondaryVariantType) {
                            $validator->errors()->add("links.{$index}", __('api.submissionFiles.400.cannotLinkSameVariantType'));
                        }
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'links.required' => __('api.400.missingRequired', ['param' => 'links']),
            'links.array' => __('api.400.missingRequired', ['param' => 'links']),
            'links.*.primarySubmissionFileId.required' => __('api.400.missingRequired', ['param' => 'primarySubmissionFileId']),
        ];
    }
}
