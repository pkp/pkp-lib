<?php

/**
 * @file api/v1/reviews/formRequests/ChangeResponseCompletion.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeResponseCompletion
 *
 * @brief Handle API requests validation for changing the completion status of a review round author response (mark completed or revert).
 *
 */

namespace PKP\API\v1\reviews\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use PKP\submission\reviewRound\ReviewRound;

class ChangeResponseCompletion extends FormRequest
{
    protected ?ReviewRound $reviewRound = null;
    protected ?AuthorResponse $existingResponse = null;

    public function rules(): array
    {
        $context = Application::get()->getRequest()->getContext();
        $submission = Repo::submission()->get((int) $this->route('submissionId'), $context->getId());

        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDao->getById((int) $this->route('reviewRoundId'));

        // The review round must belong to a submission in the current context.
        if ($submission && $reviewRound && $reviewRound->getSubmissionId() === $submission->getId()) {
            $this->reviewRound = $reviewRound;
            $this->existingResponse = AuthorResponse::withReviewRoundIds([$reviewRound->getId()])
                ->where('response_id', (int) $this->route('responseId'))
                ->first();
        }

        return [
            'reviewRoundId' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->reviewRound || !$this->existingResponse) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                },
            ],
        ];
    }

    /**
     * Only assigned editors, managers, or admins may change the completion status of an author response.
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

        $canUpdate = $isEditor ||
            $user->hasRole([Role::ROLE_ID_MANAGER], $request->getContext()->getId()) ||
            $user->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);

        if (!$canUpdate) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN));
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reviewRoundId' => $this->route('reviewRoundId')]);
    }

    /** @inheritdoc */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        $validated['existingResponse'] = $this->existingResponse;

        return $validated;
    }
}
