<?php

/**
 * @file api/v1/reviews/formRequests/AddResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddResponse
 *
 * @brief Handle API requests validation for adding review round responses.
 *
 */

namespace PKP\API\v1\reviews\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use PKP\submission\reviewRound\ReviewRound;

class AddResponse extends FormRequest
{
    use ReviewRoundAuthorResponseCommonValidator;

    protected ReviewRound $reviewRound;

    public function rules(): array
    {
        return $this->commonRules();
    }

    /**
     * Perform additional form specific validations after initial check was passed.
     */
    public function after(): array
    {
        return $this->commonAfter();
    }

    /**
     * Further validations not tied to the form data
     */
    protected function passedValidation()
    {
        $user = Application::get()->getRequest()->getUser();

        $isAssignedAuthor = StageAssignment::withSubmissionIds([$this->reviewRound->getSubmissionId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$this->reviewRound->getStageId()])
            ->withUserId($user->getId())
            ->exists();

        if (!$isAssignedAuthor) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN));
        }

        $hasExistingResponse = AuthorResponse::withReviewRoundIds([$this->reviewRound->getId()])->exists();
        if ($hasExistingResponse) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT));
        }

        // Check that review round is in state where author response is applicable(revisions required, submission accepted), or that review response response was requested
        if (
            !in_array($this->reviewRound->getStatus(), [ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED, ReviewRound::REVIEW_ROUND_STATUS_ACCEPTED])
            && !$this->reviewRound->getData('isAuthorResponseRequested')
        ) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN));
        }
    }

    /** @inheritdoc  */
    public function validated($key = null, $default = null)
    {
        return $this->commonValidated();
    }
}
