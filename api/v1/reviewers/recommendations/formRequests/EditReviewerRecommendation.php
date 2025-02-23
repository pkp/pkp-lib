<?php

// TODO : update once pkp/pkp-lib#4787 merged
/**
 * @file api/v1/reviewers/recommendations/formRequests/EditReviewerRecommendation.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewerRecommendation
 *
 * @brief Form request class to validation updating of resource
 *
 */

namespace PKP\API\v1\reviewers\recommendations\formRequests;

use PKP\API\v1\reviewers\recommendations\formRequests\AddReviewerRecommendation;

class EditReviewerRecommendation extends AddReviewerRecommendation
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
