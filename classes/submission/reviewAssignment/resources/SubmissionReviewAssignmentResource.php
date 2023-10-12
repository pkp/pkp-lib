<?php
/**
 * @file classes/submission/reviewAssignment/resources/SubmissionReviewAssignmentResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionReviewAssignmentResource
 *
 * @brief Map review Assignments to the properties for submission grid
 */

namespace PKP\submission\reviewAssignment\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionReviewAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'reviewerFullName' => $this->getReviewerFullName(),
            'dateAssigned' => $this->getDateAssigned(),
            'dateDue' => $this->getDateDue(),
            'dateResponseDue' => $this->getDateResponseDue(),
            'dateCompleted' => $this->getDateCompleted(),
            'status' => $this->getStatus(),
            'statusKey' => $this->getStatusKey(),
            'reviewMethod' => $this->getReviewMethod(),
            'reviewMethodKey' => $this->getReviewMethodKey(),
        ];
    }
}
