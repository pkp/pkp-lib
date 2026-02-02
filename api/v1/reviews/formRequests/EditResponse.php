<?php

/**
 * @file api/v1/reviews/formRequests/EditResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditResponse
 *
 * @brief Handle API requests validation for editing review round responses.
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

class EditResponse extends FormRequest
{
    use ReviewRoundAuthorResponseCommonValidator;

    protected ?ReviewRound $reviewRound = null;
    protected ?AuthorResponse $existingResponse = null;
    public function rules(): array
    {
        $this->existingResponse = AuthorResponse::withReviewRoundIds([$this->route('reviewRoundId')])
            ->where('response_id', $this->route('responseId'))
            ->first();

        return array_merge(
            [
                'responseId' => [
                    'required',
                    'integer',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        if (!$this->existingResponse || (int) $this->existingResponse->id !== (int) $value) {
                            $fail(__('api.404.resourceNotFound'));
                        }
                    },
                ]
            ],
            $this->commonRules()
        );
    }

    /**
     * Perform additional form specific validations after initial check was passed.
     */
    public function after(): array
    {
        return $this->commonAfter();
    }

    /**
     * Further validations not tied to the form data.
     */
    protected function passedValidation()
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $isEditor = StageAssignment::withSubmissionIds([$this->reviewRound->getSubmissionId()])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([$this->reviewRound->getStageId()])
            ->withUserId($user->getId())
            ->exists();

        // Only assigned editors, managers, or admins are allowed to edit author responses.
        $canEdit = $isEditor ||
            $user->hasRole([Role::ROLE_ID_MANAGER], $request->getContext()->getId()) ||
            $user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::SITE_CONTEXT_ID);


        if (!$canEdit) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED));
        }
    }

    /** @inheritdoc  */
    public function validated($key = null, $default = null)
    {
        $request = $this->commonValidated();
        $request['existingResponse'] = $this->existingResponse;

        return $request;
    }
}
