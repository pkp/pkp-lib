<?php

/**
 * @file api/v1/submissions/reviewAssignments/ReviewAssignmentController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentController
 *
 * @brief Handle API requests to manage review assignments for a submission.
 *
 */

namespace PKP\API\v1\submissions\reviewAssignments;

use APP\core\Application;
use APP\facades\Repo;
use APP\orcid\actions\SendReviewToOrcid;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\submissions\reviewAssignments\formRequests\EditReview;
use PKP\API\v1\submissions\reviewAssignments\resources\ReviewResource;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionCommentDAO;

class ReviewAssignmentController extends PKPBaseController
{
    /**
     * @copydoc PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/reviewAssignments';
    }

    /**
     * @copydoc PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {
            Route::get('{reviewAssignmentId}', $this->getReviewAssignment(...))
                ->name('submission.reviewAssignment.getReviewAssignment')
                ->whereNumber('reviewAssignmentId');

            Route::put('{reviewAssignmentId}', $this->editReviewAssignment(...))
                ->name('submission.reviewAssignment.editReviewAssignment')
                ->whereNumber('reviewAssignmentId');

            Route::put('{reviewAssignmentId}/consider', $this->consider(...))
                ->name('submission.reviewAssignment.consider')
                ->whereNumber('reviewAssignmentId');

            Route::get('{reviewAssignmentId}/review', $this->getReview(...))
                ->name('submission.reviewAssignment.review.getReview')
                ->whereNumber('reviewAssignmentId');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ])->group(function () {
            Route::put('{reviewAssignmentId}/review', $this->editReview(...))
                ->name('submission.reviewAssignment.review.editReview')
                ->whereNumber('reviewAssignmentId');
        });
    }

    /**
     * @copydoc PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a review assignment by ID.
     */
    public function getReviewAssignment(Request $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviewAssignmentId = (int)$illuminateRequest->route('reviewAssignmentId');

        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId, $submission->getId());

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $data = Repo::reviewAssignment()
            ->getSchemaMap()
            ->map($reviewAssignment, $submission);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Edit a review assignment.
     * Currently, only support updating the `quality` field of a review assignment. Accepted rating values are 1 to 5, or 0 to unset existing the rating.
     */
    public function editReviewAssignment(Request $illuminateRequest): JsonResponse
    {
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_REVIEW_ASSIGNMENT, $illuminateRequest->input());

        // Check if 'quality' key exist in request
        if (!array_key_exists('quality', $params)) {
            return response()->json([
                'error' => __('api.422.missingRequiredField', ['field' => 'quality']),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if keys other than 'quality' exist in params.
        if (count($params) > 1) {
            return response()->json([
                'error' => __('api.submissions.reviews.422.uneditableFields'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviewAssignmentId = (int)$illuminateRequest->route('reviewAssignmentId');
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId, $submission->getId());

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $context = $this->getRequest()->getContext();

        $hasRating = $params['quality'] !== 0;
        // If no rating was submitted, then unset the existing one with a `null` value.
        $params['dateRated'] = $hasRating ? Core::getCurrentDate() : null;
        $params['quality'] = $hasRating ? $params['quality'] : null;

        $errors = Repo::reviewAssignment()->validate($reviewAssignment, $params, $context);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        Repo::reviewAssignment()->edit($reviewAssignment, $params);

        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId(), $submission->getId());

        $data = Repo::reviewAssignment()
            ->getSchemaMap()
            ->map($reviewAssignment, $submission);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Update the considered status of a review assignment.
     * Accepts the following values for the `considered` field:
     * - ReviewAssignment::REVIEW_ASSIGNMENT_VIEWED
     * - ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED
     * - ReviewAssignment::REVIEW_ASSIGNMENT_UNCONSIDERED
     */
    public function consider(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $reviewAssignmentId = (int) $illuminateRequest->route('reviewAssignmentId');
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId, $submission->getId());

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $considered = (int)$illuminateRequest->input('considered');
        if (!in_array($considered, [ReviewAssignment::REVIEW_ASSIGNMENT_VIEWED, ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED, ReviewAssignment::REVIEW_ASSIGNMENT_UNCONSIDERED])) {
            return response()->json([
                'error' => __('api.reviews.assignments.422.missingOrInvalidConsideredValue'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // A review assignment that an editor has already considered cannot be considered again.
        if ($considered === ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED && $reviewAssignment->isRead()) {
            return response()->json([
                'error' => __('api.reviews.assignments.alreadyConsidered'),
            ], Response::HTTP_CONFLICT);
        }

        $updatedReviewAssignment = match ($considered) {
            ReviewAssignment::REVIEW_ASSIGNMENT_VIEWED => $this->markReviewViewed($reviewAssignment),
            ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED => $this->markReviewConsidered($reviewAssignment, $submission),
            ReviewAssignment::REVIEW_ASSIGNMENT_UNCONSIDERED => $this->markReviewUnconsidered($reviewAssignment, $submission),
        };

        return response()->json(
            Repo::reviewAssignment()->getSchemaMap()->map($updatedReviewAssignment, $submission),
            Response::HTTP_OK
        );
    }

    /**
     * Mark a new review assignment as viewed by the editor.
     */
    protected function markReviewViewed(ReviewAssignment $reviewAssignment): ReviewAssignment
    {
        // If it's a new review assignment, mark it as viewed
        if ($reviewAssignment->getConsidered() === ReviewAssignment::REVIEW_ASSIGNMENT_NEW) {
            Repo::reviewAssignment()->edit($reviewAssignment, [
                'considered' => ReviewAssignment::REVIEW_ASSIGNMENT_VIEWED,
            ]);

            return Repo::reviewAssignment()->get($reviewAssignment->getId(), $reviewAssignment->getData('submissionId'));
        }

        return $reviewAssignment;
    }

    /**
     * Mark a review assignment as considered by the editor.
     */
    protected function markReviewConsidered(ReviewAssignment $reviewAssignment, Submission $submission): ReviewAssignment
    {
        $request = $this->getRequest();

        // If the review assignment had been unconsidered or only viewed but not considered, update the flag.
        $newReviewData = [
            'considered' => ($reviewAssignment->getConsidered() === ReviewAssignment::REVIEW_ASSIGNMENT_NEW ||
                $reviewAssignment->getConsidered() === ReviewAssignment::REVIEW_ASSIGNMENT_VIEWED)
                ? ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED
                : ReviewAssignment::REVIEW_ASSIGNMENT_RECONSIDERED,
            // Set the date when the editor confirms the review
            'dateConsidered' => Core::getCurrentDate(),
        ];

        if (!$reviewAssignment->getDateCompleted()) {
            // Editor completes the review.
            $newReviewData['dateConfirmed'] = $newReviewData['dateCompleted'] = Core::getCurrentDate();
        }

        // Trigger an update of the review round status
        Repo::reviewAssignment()->edit($reviewAssignment, $newReviewData);
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId(), $reviewAssignment->getData('submissionId'));

        // If the review was read by an editor, log event
        if ($reviewAssignment->isRead()) {
            $user = $request->getUser();
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
                'message' => 'log.review.reviewConfirmed',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'editorName' => $user->getFullName(),
                'submissionId' => $submission->getId(),
                'round' => $reviewAssignment->getRound(),
                'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
            ]);

            Repo::eventLog()->add($eventLog);
        }

        // Remove the reviewer task.
        Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())
            ->withUserId($reviewAssignment->getReviewerId())
            ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
            ->delete();

        // Deposit review to ORCID
        (new SendReviewToOrcid($reviewAssignment->getId()))->execute();

        return $reviewAssignment;
    }

    /**
     * Revoke the considered status of a review assignment.
     *
     */
    protected function markReviewUnconsidered(ReviewAssignment $reviewAssignment, Submission $submission): ReviewAssignment
    {
        $user = $this->getRequest()->getUser();

        // This resets the state of the review to 'unconsidered', but does not delete note history.
        Repo::reviewAssignment()->edit($reviewAssignment, [
            'considered' => ReviewAssignment::REVIEW_ASSIGNMENT_UNCONSIDERED,
            'dateConsidered' => null,
        ]);

        // Log the unconsidered event.
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_UNCONSIDERED,
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'message' => 'log.review.reviewUnconsidered',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'editorName' => $user->getFullName(),
            'submissionId' => $submission->getId(),
            'round' => $reviewAssignment->getRound(),
            'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
        ]);

        Repo::eventLog()->add($eventLog);

        return Repo::reviewAssignment()->get($reviewAssignment->getId(), $reviewAssignment->getData('submissionId'));
    }

    /**
     * Check if the current user can access the given review assignment.
     *
     * @param array $roles - The roles that are allowed to access the review assignment. Managers and Admins have access to all review assignments, so those roles are not required to be included in the $roles array.
     */
    protected function canAccessReviewAssignment(Submission $submission, ReviewAssignment $reviewAssignment, array $roles): bool
    {
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $isManager = array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles);

        if ($isManager) {
            return true;
        }

        $user = $this->getRequest()->getUser();
        return StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$reviewAssignment->getStageId()])
            ->withRoleIds($roles)
            ->withUserId($user->getId())
            ->exists();
    }

    /**
     * Get the review submitted for a review assignment.
     */
    public function getReview(Request $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviewAssignmentId = (int)$illuminateRequest->route('reviewAssignmentId');
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId, $submission->getId());

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json(new ReviewResource($reviewAssignment), Response::HTTP_OK);
    }

    /**
     * Update the review submitted for a review assignment.
     *
     * This updates the review's comments or form responses, along with the reviewer's recommendation.
     * All required review fields must be submitted. That is:
     * - `reviewerRecommendationId` must be present
     * - If a review has a form, then form fields marked as 'required' must be submitted in the `reviewFormResponses` field.
     *       The submitted form fields in `reviewFormResponses` will completely overwrite existing values for those fields.
     *       Therefore, omitting a non-required field or submitting an empty value will be considered as an indication that existing responses for those fields are to be deleted.
     *       If the review does not have a form, then comments are allowed. Currently, comment fields are optional even when a form is not present.
     */
    public function editReview(EditReview $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $validated = $illuminateRequest->validated();
        $user = $this->getRequest()->getUser();
        /** @var ReviewAssignment $reviewAssignment */
        $reviewAssignment = $validated['reviewAssignment'];

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $isReviewUpdated = false;
        $submittedReviewerRecommendationId = $validated['reviewerRecommendationId'];

        if ($reviewAssignment->getReviewFormId() && $submittedReviewFormResponses = $validated['reviewFormResponses']) {
            if (!is_array($submittedReviewFormResponses)) {
                // Parse the submitted review form responses from JSON string to an associative array.
                // Each key is a review form element ID, and the value is the submitted response.
                $submittedReviewFormResponses = json_decode($submittedReviewFormResponses, true);
            }

            /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
            /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');

            // Tracks whether any form responses changed as a result of this update
            $formFieldsUpdated = false;
            $allExistingFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());

            $reviewResponsesToDeleteId = [];
            foreach ($submittedReviewFormResponses as $reviewFormElementId => $reviewFormResponseValue) {
                $hasExistingResponse = array_key_exists($reviewFormElementId, $allExistingFormResponses);

                // Skip elements whose submitted response matches what is already stored.
                if ($hasExistingResponse) {
                    if (is_array($reviewFormResponseValue)) {
                        $submittedSorted = $reviewFormResponseValue;
                        $existingSorted = $allExistingFormResponses[$reviewFormElementId];
                        sort($submittedSorted);
                        sort($existingSorted);
                        if ($existingSorted == $submittedSorted) {
                            continue;
                        }
                    } elseif ($allExistingFormResponses[$reviewFormElementId] == $reviewFormResponseValue) {
                        continue;
                    }
                }

                if (
                    $reviewFormResponseValue !== null &&
                    $reviewFormResponseValue !== '' &&
                    (!is_array($reviewFormResponseValue) || count($reviewFormResponseValue) !== 0)
                ) {
                    Repo::reviewAssignment()->saveReviewFormResponse($reviewAssignment, $reviewFormElementId, $reviewFormResponseValue);
                    $formFieldsUpdated = true;
                } else {
                    // Submitting an empty value for a non-required field will delete any existing response for that field
                    if (array_key_exists($reviewFormElementId, $allExistingFormResponses)) {
                        $reviewResponsesToDeleteId[] = $reviewFormElementId;
                    }
                }
            }

            $elementsNotSubmittedId = array_diff(array_keys($allExistingFormResponses), array_keys($submittedReviewFormResponses));

            // If an element is omitted from the submitted response, and it has an existing response, then delete that existing response.
            // Else, the omitted element can be safely ignored as there are no changes being made to it.
            foreach ($elementsNotSubmittedId as $elementNotSubmittedId) {
                if (array_key_exists($elementNotSubmittedId, $allExistingFormResponses)) {
                    $reviewResponsesToDeleteId[] = $elementNotSubmittedId;
                }
            }
            $formFieldsUpdated = $formFieldsUpdated || !empty($reviewResponsesToDeleteId);

            if ($formFieldsUpdated) {
                $isReviewUpdated = true;
                $oldReviewFormResponses = [];
                $updatedReviewFormResponses = [];

                foreach ($allExistingFormResponses as $reviewFormElementId => $reviewFormResponseValue) {
                    $reviewFormElement = $reviewFormElementDao->getById($reviewFormElementId);
                    if (!$reviewFormElement) {
                        continue;
                    }
                    // Prepare log value for old form responses. This will include any fields that will be deleted by this request.
                    $oldReviewFormResponses[$reviewFormElementId] = Repo::reviewAssignment()->formatReviewFormElementResponseForLogEntry($reviewFormElement, $reviewFormResponseValue);
                }

                // Ensure the responses that are omitted or empty are deleted before preparing log value for new form responses so that they are not included in that log.
                foreach ($reviewResponsesToDeleteId as $reviewFormElementId) {
                    $reviewFormResponseDao->deleteById($reviewAssignment->getId(), $reviewFormElementId);
                }

                $storedReviewFormResponseValues = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
                foreach ($storedReviewFormResponseValues as $reviewFormElementId => $reviewFormResponseValue) {
                    $reviewFormElement = $reviewFormElementDao->getById($reviewFormElementId);
                    if (!$reviewFormElement) {
                        continue;
                    }
                    $updatedReviewFormResponses[$reviewFormElementId] = Repo::reviewAssignment()->formatReviewFormElementResponseForLogEntry($reviewFormElement, $reviewFormResponseValue);
                }

                // Once all responses have been processed, and at least one field changed, log the entire old and new form with their responses.
                // The form responses will be stored as JSON in the following format:
                // {
                //     "<elementID>": {
                //         "question": "<string>",
                //         "answer": "<int|string|array>",
                //         "elementType": <int>,
                //         "possibleResponses": [<int>], // optional
                //         "selectedResponses": [<int>]    // optional
                //     },
                //     ...
                // }
                $eventLog = Repo::eventLog()->newDataObject([
                    'assocType' => PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                    'assocId' => $reviewAssignment->getId(),
                    'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_FORM_RESPONSE_MODIFIED,
                    'userId' => Validation::loggedInAs() ?? $user->getId(),
                    'message' => 'submission.event.review.field.modified',
                    'isTranslated' => false,
                    'dateLogged' => Core::getCurrentDate(),
                    'reviewFormResponseOld' => json_encode($oldReviewFormResponses),
                    'reviewFormResponseNew' => json_encode($updatedReviewFormResponses),
                    'fieldNameKey' => 'submission.event.review.fieldName.reviewFormResponses',
                    'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
                ]);
                Repo::eventLog()->add($eventLog);
            }
        } else {
            // Explicitly check if the comments field was submitted and update the review comments with the new values
            // A `null` value for the validated comments field indicates that either:
            // - The client submitted a `null` value for the comments field, or
            // - The client submitted an empty string for the comments field and laravel form request validation automatically converted the empty string to `null`.
            // In either case, we interpret this as an indication that the existing comments value should be cleared.
            if ($illuminateRequest->exists('comments')) {
                /** @var string $commentsSubmitted */
                $commentsSubmitted = $validated['comments'] ?: '';

                /** @var SubmissionCommentDAO $submissionCommentDao */
                $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
                $existingComment = $submissionCommentDao->getReviewerCommentsByReviewerId(
                    $reviewAssignment->getSubmissionId(),
                    $reviewAssignment->getReviewerId(),
                    $reviewAssignment->getId(),
                    true
                )->next();

                $oldComments = $existingComment?->getData('comments');
                if ($commentsSubmitted !== $oldComments) {
                    $savedComment = Repo::reviewAssignment()->saveReviewComment($reviewAssignment, $commentsSubmitted, true);
                    $isReviewUpdated = true;
                    // Log changes to the event log
                    $eventLog = Repo::eventLog()->newDataObject([
                        'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION_REVIEW_COMMENT,
                        'assocId' => $savedComment->getId(),
                        'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_COMMENTS_MODIFIED,
                        'userId' => Validation::loggedInAs() ?? $user->getId(),
                        'message' => 'submission.event.review.field.modified',
                        'isTranslated' => false,
                        'dateLogged' => Core::getCurrentDate(),
                        'reviewerCommentsOld' => $oldComments,
                        'reviewerCommentsNew' => $savedComment->getData('comments'),
                        'fieldNameKey' => 'submission.event.review.fieldName.comments',
                        'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
                    ]);

                    Repo::eventLog()->add($eventLog);
                }
            }
        }

        $newAssignmentData = [];
        $oldReviewerRecommendationId = $reviewAssignment->getReviewerRecommendationId();

        if ($submittedReviewerRecommendationId !== $oldReviewerRecommendationId && Application::get()->hasCustomizableReviewerRecommendation()) {
            $newAssignmentData['reviewerRecommendationId'] = $submittedReviewerRecommendationId;
            $isReviewUpdated = true;
        }

        if ($isReviewUpdated) {
            $newAssignmentData['lastModifiedById'] = Validation::loggedInAs() ?? $user->getId();

            if (!$reviewAssignment->getDateCompleted()) {
                $newAssignmentData['dateCompleted'] = Core::getCurrentDate();
            }
            Repo::reviewAssignment()->edit($reviewAssignment, $newAssignmentData);
        }

        if ($submittedReviewerRecommendationId !== $oldReviewerRecommendationId && Application::get()->hasCustomizableReviewerRecommendation()) {
            // Log changes to the event log
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                'assocId' => $reviewAssignment->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_RECOMMENDATION_MODIFIED,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'submission.event.review.field.modified',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'reviewerRecommendationOldId' => $oldReviewerRecommendationId,
                'reviewerRecommendationNewId' => $submittedReviewerRecommendationId,
                'fieldNameKey' => 'submission.event.review.fieldName.reviewerRecommendationId',
                'impersonatedUserId' => Validation::loggedInAs() ? $user->getId() : null,
            ]);

            Repo::eventLog()->add($eventLog);
        }

        // If the editor completed the review on reviewer's behalf, then remove the initial task notification sent to reviewer
        if (isset($newAssignmentData['dateCompleted'])) {
            // Remove the reviewer task.
            Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())
                ->withUserId($reviewAssignment->getReviewerId())
                ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
                ->delete();
        }

        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId(), $submission->getId());

        return response()->json(new ReviewResource($reviewAssignment), Response::HTTP_OK);
    }
}
