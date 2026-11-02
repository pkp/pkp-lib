<?php

/**
 * @file api/v1/reviews/formRequests/EditAuthorReviewResponseConfirmation.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditAuthorReviewResponseConfirmation
 *
 * @brief Handle API requests validation for changing the confirmation status of a review round author response (confirmed or reverted).
 *
 */

namespace PKP\API\v1\reviews\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use PKP\submission\reviewRound\ReviewRound;

class EditAuthorReviewResponseConfirmation extends FormRequest
{
    protected ?ReviewRound $reviewRound = null;
    protected ?AuthorResponse $existingResponse = null;
    protected ?Submission $submission = null;

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
            $this->submission = $submission;

            $this->existingResponse = AuthorResponse::withReviewRoundIds([$reviewRound->getId()])
                ->find((int) $this->route('responseId'));
        }

        return [
            'markAs' => ['required', 'string', Rule::in([AuthorResponse::STATUS_CONFIRMED, AuthorResponse::STATUS_REVERTED])],
            'reviewRoundId' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->reviewRound) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                },
            ],
            'responseId' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->existingResponse) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                },
            ],
            'submissionId' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->submission) {
                        $fail(__('api.404.resourceNotFound'));
                    }
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function passedValidation()
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        // Only assigned editors, managers, or admins may change the completion status of an author response.
        $canUpdate = $this->reviewRound->canUserAccessAuthorResponses($user, [Role::ROLE_ID_SUB_EDITOR], $request->getContext());

        if (!$canUpdate) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN));
        }

        // Only allow request to revert if the response is:
        // - Currently confirmed; or
        // - Already reverted. In this case the API will not change anything.
        if ($this->input('markAs') === AuthorResponse::STATUS_REVERTED && !$this->existingResponse->isConfirmed && !$this->existingResponse->isReverted) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.reviewRound.422.authorResponse.revert'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    /** @inheritdoc */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reviewRoundId' => $this->route('reviewRoundId'),
            'submissionId' => $this->route('submissionId'),
            'responseId' => $this->route('responseId'),
        ]);
    }

    /** @inheritdoc */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        $validated['existingResponse'] = $this->existingResponse;

        return $validated;
    }
}
