<?php

// TODO : update once pkp/pkp-lib#4787 merged
/**
 * @file api/v1/reviewers/recommendations/formRequests/AddReviewerRecommendation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddReviewerRecommendation
 *
 * @brief 
 *
 */

namespace PKP\API\v1\reviewers\recommendations\formRequests;

use APP\core\Application;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AddReviewerRecommendation extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $contextDao = Application::getContextDAO();

        return [
            'contextId' => [
                'required',
                'integer',
                Rule::exists($contextDao->tableName, $contextDao->primaryKeyColumn),
            ],
            'title' => [
                'required',
            ],
            'status' => [
                'required',
                'boolean'
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'contextId' => $this->route('contextId'),
            'removable' => 1,
        ]);
    }
}
