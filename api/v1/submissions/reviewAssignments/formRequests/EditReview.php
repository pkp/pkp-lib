<?php

/**
 * @file api/v1/submissions/reviewAssignments/formRequests/EditReview.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReview
 *
 * @brief Handle API request validation for editing the review submitted for a review assignment.
 */

namespace PKP\API\v1\submissions\reviewAssignments\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\RecommendationOption;

class EditReview extends FormRequest
{
    /** The review assignment being edited. */
    protected ?ReviewAssignment $reviewAssignment = null;

    /** @copydoc FormRequest::prepareForValidation() */
    protected function prepareForValidation(): void
    {
        $this->reviewAssignment = Repo::reviewAssignment()->get(
            (int)$this->route('reviewAssignmentId'),
            (int)$this->route('submissionId')
        );

        if (!$this->reviewAssignment) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND));
        }

        if ($this->reviewAssignment->getCancelled()) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.submissions.reviews.422.reviewNotEditable.cancelled')
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($this->reviewAssignment->getDeclined()) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.submissions.reviews.422.reviewNotEditable.declined'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    /**
     * The validation rules.
     */
    public function rules(): array
    {
        return [
            'reviewerRecommendationId' => [
                // Only allow `reviewerRecommendationId` in the request if the application supports having reviewer recommendations.
                Rule::when(
                    Application::get()->hasCustomizableReviewerRecommendation(),
                    [
                        'required',
                        'integer',
                        Rule::exists('reviewer_recommendations', 'reviewer_recommendation_id')
                            ->where(function (Builder $query) {
                                $query->where('context_id', Application::get()->getRequest()->getContext()->getId())
                                    ->where(function (Builder $query) {
                                        $query->where('status', RecommendationOption::ACTIVE->criteria())
                                            ->orWhere('reviewer_recommendation_id', $this->reviewAssignment->getData('reviewerRecommendationId'));
                                    });
                            })

                    ],
                    'prohibited'
                )
            ],
            'comments' => [
                'sometimes',
                // If the review has a form, then no comments can be added.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!$this->reviewAssignment->getReviewFormId()) {
                        return;
                    }

                    if ($this->input('comments')) {
                        $fail(__('api.submissions.reviews.422.commentsNotAllowed'));
                    }
                },
            ],
            // Only allow `reviewFormResponses` if the review has a form
            'reviewFormResponses' => [
                'bail',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->reviewAssignment->getReviewFormId()) {
                        return;
                    }

                    if ($this->input('reviewFormResponses')) {
                        $fail(__('api.submissions.reviews.422.formSubmissionNotAllowed'));
                    }
                },
                // Ensure the value is either an object (assoc array) or a valid JSON string
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_array($value)) {
                        return;
                    }

                    if (is_string($value)) {
                        $decodedValue = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValue)) {
                            return;
                        }
                    }

                    $fail(__('api.submissions.reviews.422.invalidReviewFormSubmitted'));
                },
                // When the review has a form, validate the submitted responses against the form's
                // elements: every required element must have a non-empty response, and every submitted
                // response must be valid for its element type.
                function (string $attribute, mixed $value, Closure $fail) {
                    $submittedReviewFormResponses = $value;

                    if (!is_array($submittedReviewFormResponses)) {
                        // Parse the submitted responses from JSON into an associative array keyed by review form element ID.
                        $submittedReviewFormResponses = json_decode($submittedReviewFormResponses, true);
                    }

                    /** @var ReviewFormElementDAO $reviewFormElementDao */
                    $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
                    $reviewFormElements = $reviewFormElementDao->getByReviewFormId($this->reviewAssignment->getReviewFormId());

                    $reviewAssignmentFormElementIds = [];
                    while ($reviewFormElement = $reviewFormElements->next()) {
                        $reviewFormElementId = $reviewFormElement->getId();
                        $reviewAssignmentFormElementIds[] = $reviewFormElementId;

                        if (
                            $reviewFormElement->getRequired()
                            && (!isset($submittedReviewFormResponses[$reviewFormElementId]) || $submittedReviewFormResponses[$reviewFormElementId] === '')
                        ) {
                            $fail(__('api.submissions.reviews.422.reviewFormQuestionResponseRequired', ['elementId' => $reviewFormElementId]));
                            continue;
                        }

                        if (
                            isset($submittedReviewFormResponses[$reviewFormElementId])
                            && !$this->isValidReviewFormFieldResponse($reviewFormElement, $submittedReviewFormResponses[$reviewFormElementId])
                        ) {
                            $fail(__('api.submissions.reviews.422.invalidReviewFormResponse', ['elementId' => $reviewFormElementId]));
                        }
                    }

                    foreach (array_keys($submittedReviewFormResponses) as $submittedReviewElementId) {
                        if (!in_array($submittedReviewElementId, $reviewAssignmentFormElementIds)) {
                            $fail(__('api.submissions.reviews.422.invalidReviewFormElementSubmitted', ['elementId' => $submittedReviewElementId]));
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Check whether a submitted response is valid for the given review form element's type.
     */
    protected function isValidReviewFormFieldResponse(ReviewFormElement $reviewFormElement, mixed $response): bool
    {
        switch ($reviewFormElement->getElementType()) {
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA:
                return is_string($response);

            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
                // Expect an array of indices, each pointing to a possible response.
                if (!is_array($response)) {
                    return false;
                }
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                if (!is_array($possibleResponses)) {
                    return false;
                }
                foreach ($response as $index) {
                    if (!array_key_exists((int)$index, $possibleResponses)) {
                        return false;
                    }
                }
                return true;

            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
                // Expect a single index pointing to a possible response.
                if (!is_numeric($response)) {
                    return false;
                }
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                return is_array($possibleResponses) && array_key_exists((int)$response, $possibleResponses);

            default:
                return false;
        }
    }

    /** @copydoc FormRequest::messages() */
    public function messages(): array
    {
        return [
            'reviewerRecommendationId.required' => __('api.422.missingRequiredField', ['field' => 'reviewerRecommendationId']),
            'reviewerRecommendationId.exists' => __('api.submissions.reviews.422.invalidRecommendation'),
            'reviewerRecommendationId.prohibited' => __('api.submissions.reviews.422.reviewerRecommendation.notEditable'),
        ];
    }

    /** @copydoc FormRequest::validated() */
    public function validated($key = null, $default = null)
    {
        return array_merge(
            parent::validated(),
            [
                'reviewAssignment' => $this->reviewAssignment,
                'comments' => $this->input('comments'),
                'reviewFormResponses' => $this->input('reviewFormResponses'),
                // Will default to null in cases where the app does not support reviewer recommendations (e.g., in OMP).
                'reviewerRecommendationId' => $this->input('reviewerRecommendationId') ? (int)$this->input('reviewerRecommendationId') : null,
            ]
        );
    }
}
