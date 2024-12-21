<?php

/**
 * @file api/v1/reviewers/recommendations/formRequests/UpdateStatusReviewerRecommendation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateStatusReviewerRecommendation
 *
 * @brief Form request class to validation updating of resource status
 *
 */

namespace PKP\API\v1\reviewers\recommendations\formRequests;

use PKP\API\v1\reviewers\recommendations\formRequests\EditReviewerRecommendation;

class UpdateStatusReviewerRecommendation extends EditReviewerRecommendation
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'boolean'
            ],
        ];
    }
}
