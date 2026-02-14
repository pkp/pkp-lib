<?php

/**
 * @file api/v1/submissions/formRequests/AddMediaFiles.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddMediaFiles
 *
 * @brief Handle API request validation for adding media submission files.
 */

namespace PKP\API\v1\submissions\formRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\enums\MediaVariantType;

class AddMediaFiles extends FormRequest
{
    use MediaFileValidationTrait;

    protected array $cleanedFiles = [];

    protected function prepareForValidation(): void
    {
        $files = $this->input('files', []);

        foreach ($files as $fileEntry) {
            $temporaryFileId = $fileEntry['temporaryFileId'] ?? null;

            $params = $this->getBaseApiController()->convertStringsToSchema(
                PKPSchemaService::SCHEMA_SUBMISSION_FILE,
                collect($fileEntry)->except(['temporaryFileId'])->toArray()
            );

            $this->cleanedFiles[] = array_merge($params, ['temporaryFileId' => $temporaryFileId]);
        }
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*.temporaryFileId' => ['required'],
            'files.*.variantType' => [
                'required',
                Rule::in(array_column(MediaVariantType::cases(), 'value')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => __('api.400.missingRequired', ['param' => 'files']),
            'files.array' => __('api.400.missingRequired', ['param' => 'files']),
            'files.min' => __('api.400.missingRequired', ['param' => 'files']),
            'files.*.temporaryFileId.required' => __('api.400.missingRequired', ['param' => 'temporaryFileId']),
            'files.*.variantType.required' => __('api.400.missingRequired', ['param' => 'variantType']),
            'files.*.variantType.in' => __('api.400.invalidValue', ['param' => 'variantType']),
        ];
    }

    public function validated($key = null, $default = null)
    {
        return [
            'files' => $this->cleanedFiles,
        ];
    }
}
