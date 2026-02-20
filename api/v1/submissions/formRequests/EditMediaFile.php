<?php

/**
 * @file api/v1/submissions/formRequests/EditMediaFile.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditMediaFile
 *
 * @brief Handle API request validation for editing a media submission file.
 */

namespace PKP\API\v1\submissions\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PKP\services\PKPSchemaService;

class EditMediaFile extends FormRequest
{
    use MediaFileValidationTrait;

    protected array $cleanedParams = [];

    protected function prepareForValidation(): void
    {
        $params = $this->getBaseApiController()->convertStringsToSchema(
            PKPSchemaService::SCHEMA_SUBMISSION_FILE,
            $this->input()
        );

        unset(
            $params['submissionId'],
            $params['fileId'],
            $params['uploaderUserId'],
            $params['createdAt'],
            $params['fileStage']
        );

        $this->cleanedParams = $params;
    }

    public function rules(): array
    {
        return [];
    }

    protected function passedValidation(): void
    {
        if (empty($this->cleanedParams)) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.submissionsFiles.400.noParams'),
            ], Response::HTTP_BAD_REQUEST));
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->count()) {
                    return;
                }

                $submission = $this->getSubmission();
                $submissionFile = $this->getSubmissionFile();
                $submissionLocale = $submission->getData('locale');
                $allowedLocales = Application::get()->getRequest()->getContext()->getSupportedSubmissionMetadataLocales();

                $errors = Repo::submissionFile()->validate(
                    $submissionFile,
                    $this->cleanedParams,
                    $allowedLocales,
                    $submissionLocale
                );

                if (!empty($errors)) {
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ((array) $fieldErrors as $error) {
                            $validator->errors()->add($field, $error);
                        }
                    }
                }
            },
        ];
    }

    public function validated($key = null, $default = null)
    {
        return [
            'params' => $this->cleanedParams,
            'submission' => $this->getSubmission(),
            'submissionFile' => $this->getSubmissionFile(),
        ];
    }
}
