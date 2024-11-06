<?php

// TODO : update once pkp/pkp-lib#4787 merged
/**
 * @file api/v1/reviewers/recommendations/formRequests/EditReviewerRecommendation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewerRecommendation
 *
 * @brief 
 *
 */

namespace PKP\API\v1\reviewers\recommendations\formRequests;

use Illuminate\Foundation\Http\FormRequest;

class EditReviewerRecommendation extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
            ],
            'status' => [
                'required',
                'boolean'
            ],
        ];
    }
}
