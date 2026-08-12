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
use PKP\API\v1\submissions\reviewAssignments\resources\ReviewResource;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
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
            Route::get('{reviewAssignmentId}', $this->get(...))
                ->name('submission.reviewAssignment.get')
                ->whereNumber('reviewAssignmentId');

            Route::put('{reviewAssignmentId}', $this->editReviewAssignment(...))
                ->name('submission.reviewAssignment.edit')
                ->whereNumber('reviewAssignmentId');

            Route::put('{reviewAssignmentId}/consider', $this->consider(...))
                ->name('submission.reviewAssignment.consider')
                ->whereNumber('reviewAssignmentId');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ])->group(function () {
            Route::get('{reviewAssignmentId}/review', $this->getReview(...))
                ->name('submission.reviewAssignment.review.get')
                ->whereNumber('reviewAssignmentId');
            Route::put('{reviewAssignmentId}/review', $this->editReview(...))
                ->name('submission.reviewAssignment.review.edit')
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
    public function get(Request $illuminateRequest): JsonResponse
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

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment)) {
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
        if (!key_exists('quality', $params)) {
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

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment)) {
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

        if (!$this->canAccessReviewAssignment($submission, $reviewAssignment)) {
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
        ]);

        Repo::eventLog()->add($eventLog);

        return Repo::reviewAssignment()->get($reviewAssignment->getId(), $reviewAssignment->getData('submissionId'));
    }

    /**
     * Check if the current user can access the given review assignment.
     */
    protected function canAccessReviewAssignment(Submission $submission, ReviewAssignment $reviewAssignment): bool
    {
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $isManager = array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles);

        if ($isManager) {
            return true;
        }

        $user = $this->getRequest()->getUser();
        return StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$reviewAssignment->getStageId()])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])
            ->withUserId($user->getId())
            ->exists();
    }

    /**
     * Get a reviewer's review.
     *
     * Handles both a free-text review (no review form) and a custom review form.
     * The response is shaped by {@see ReviewResource}.
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

        if (!$this->canAccessReview($submission, $reviewAssignment, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_REVIEWER])) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json(new ReviewResource($reviewAssignment), Response::HTTP_OK);
    }

    /**
     * Update a reviewer's review.
     *
     * This updates the review's comments and/or form responses, along with the reviewer's recommendation.
     * Partial updates are not allowed; the full object with all required review fields must be submitted. That is:
     * - reviewerRecommendationId must be present
     * - If a review has a form, then form fields marked as 'required' must be submitted in the form response
     * - If the review does not have a form, then comment fields are allowed. Currently, comment fields are not required even when a form is not present.
     */
    public function editReview(Request $illuminateRequest): JsonResponse
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

        $submittedReviewerRecommendationId = $illuminateRequest->input('reviewerRecommendationId');
        if (!$submittedReviewerRecommendationId) {
            return response()->json([
                'error' => __('api.422.missingRequiredField', ['field' => 'reviewerRecommendationId']),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }


        // If review has form then no comments can be added
        $commentsSubmitted = $illuminateRequest->exists('comments');
        $privateCommentsSubmitted = $illuminateRequest->exists('commentsPrivate');

        if ($reviewAssignment->getReviewFormId() && ($commentsSubmitted || $privateCommentsSubmitted)) {
            return response()->json([
                'error' => 'comments not allowed on this review', // TODO: use locale
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($reviewAssignment->getReviewFormId()) {
            // If a review has a form, then validate that the required form fields all exist in the request
            // Then update the review form responses with the new values
            $reviewFormResponses = $illuminateRequest->input('reviewFormResponses') ?? '';
            if ($reviewFormResponses) {
                // Parse the submitted review form responses from JSON into an associative array.
                // Each key is a review form element ID, and the value is the submitted response.
                $reviewFormResponses = json_decode($reviewFormResponses, true);
            }

            /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');

            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());

            // loop over each $reviewFormElements and check if:
            // - the required fields are present in the responses submitted by the user in the current request
            // - the submitted response is valid for each field
            while ($reviewFormElement = $reviewFormElements->next()) {
                $reviewFormElementId = $reviewFormElement->getId();
                $required = $reviewFormElement->getRequired();

                if ($required && (!$reviewFormResponses || !isset($reviewFormResponses[$reviewFormElementId]) || $reviewFormResponses[$reviewFormElementId] === '')) {
                    return response()->json([
                        'error' => 'MISSING REQUIRED FORM FIELD ID: ' . $reviewFormElementId . ' Question: ' . $reviewFormElement->getLocalizedQuestion(),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if (isset($reviewFormResponses[$reviewFormElementId])) {
                    // Check if the submitted response is valid for the field type
                    $isValidResponseType = $this->validateReviewFormFieldResponse($reviewFormElement, $reviewFormResponses[$reviewFormElementId]);

                    if (!$isValidResponseType) {
                        return response()->json([
                            'error' => 'INVALID RESPONSE TYPE FOR FORM FIELD ID: ' . $reviewFormElementId . ' Question: ' . $reviewFormElement->getLocalizedQuestion(),
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                }
            }

            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */

            foreach ($reviewFormResponses as $reviewFormElementId => $reviewFormResponseValue) {
                $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getId(), $reviewFormElementId);
                if (!isset($reviewFormResponse)) {
                    $reviewFormResponse = new ReviewFormResponse();
                }

                $reviewFormElement = $reviewFormElementDao->getById($reviewFormElementId);
                $elementType = $reviewFormElement->getElementType();
                switch ($elementType) {
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD:
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD:
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA:
                        $reviewFormResponse->setResponseType('string');
                        $reviewFormResponse->setValue($reviewFormResponseValue);
                        break;
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
                        $reviewFormResponse->setResponseType('int');
                        $reviewFormResponse->setValue($reviewFormResponseValue);
                        break;
                    case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
                        $reviewFormResponse->setResponseType('object');
                        $reviewFormResponse->setValue($reviewFormResponseValue);
                        break;
                }
                if ($reviewFormResponse->getReviewFormElementId() != null && $reviewFormResponse->getReviewId() != null) {
                    $reviewFormResponseDao->updateObject($reviewFormResponse);
                } else {
                    $reviewFormResponse->setReviewFormElementId($reviewFormElementId);
                    $reviewFormResponse->setReviewId($reviewAssignment->getId());
                    $reviewFormResponseDao->insertObject($reviewFormResponse);
                }
            }


        } else {
            // if comments were submitted, update the review comments with the new values
            if ($commentsSubmitted) {
                // Create a comment with the review.

                /** @var SubmissionCommentDAO $submissionCommentDao */
                $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');

                $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId(
                    $reviewAssignment->getSubmissionId(),
                    $reviewAssignment->getReviewerId(),
                    $reviewAssignment->getId(),
                    true
                );

                /** @var \PKP\submission\SubmissionComment $comment */
                $comment = $submissionComments->next();

                if (!isset($comment)) {
                    $comment = $submissionCommentDao->newDataObject();
                }

                $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
                $comment->setRoleId(Role::ROLE_ID_REVIEWER);
                $comment->setAssocId($reviewAssignment->getId());
                $comment->setSubmissionId($reviewAssignment->getSubmissionId());
                $comment->setAuthorId($reviewAssignment->getReviewerId());
                $comment->setComments($commentsSubmitted);
                $comment->setCommentTitle('');
                $comment->setViewable(true);
                $comment->setDatePosted(Core::getCurrentDate());

                // Save or update
                if ($comment->getId() != null) {
                    $submissionCommentDao->updateObject($comment);
                    // todo: update log
                } else {
                    $submissionCommentDao->insertObject($comment);
                }
            }

            if ($privateCommentsSubmitted) {
                // Create a comment with the review.
                $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
                /** @var SubmissionCommentDAO $submissionCommentDao */
                $submissionCommentsPrivate = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), false);
                $comment = $submissionCommentsPrivate->next();
                /** @var \PKP\submission\SubmissionComment $comment */

                if (!isset($comment)) {
                    $comment = $submissionCommentDao->newDataObject();
                }

                $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
                $comment->setRoleId(Role::ROLE_ID_REVIEWER);
                $comment->setAssocId($reviewAssignment->getId());
                $comment->setSubmissionId($reviewAssignment->getSubmissionId());
                $comment->setAuthorId($reviewAssignment->getReviewerId());
                $comment->setComments($privateCommentsSubmitted);
                $comment->setCommentTitle('');
                $comment->setViewable(false);
                $comment->setDatePosted(Core::getCurrentDate());

                // Save or update
                if ($comment->getId() != null) {
                    $submissionCommentDao->updateObject($comment);
                    // todo: update log
                } else {
                    $submissionCommentDao->insertObject($comment);
                }
            }
        }

        Repo::reviewAssignment()->edit($reviewAssignment, [
            'reviewerRecommendationId' => $submittedReviewerRecommendationId,
        ]);


        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId(), $submission->getId());

        return response()->json(new ReviewResource($reviewAssignment), Response::HTTP_OK);
    }

    /**
     * Check whether the current user (a manager or the assigned sub-editor/section editor)
     * may access a review for the given submission.
     */
    protected function canAccessReview(Submission $submission, ReviewAssignment $reviewAssignment, array $assignedRoles): bool
    {
        $user = $this->getRequest()->getUser();

        $isManager = $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $submission->getData('contextId'));
        $isAssignedSubEditor = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$reviewAssignment->getStageId()])
            ->withRoleIds($assignedRoles)
            ->withUserId($user->getId())
            ->exists();

        return $isManager || $isAssignedSubEditor;
    }

    /**
     * Validate a partial set of custom review form responses.
     *
     * Every submitted element must belong to the assignment's review form, and a
     * required element may not be emptied.
     *
     * @param array $reviewFormResponses A `reviewFormElementId => value` map.
     *
     * @return ?JsonResponse A validation error response, or null when valid.
     */
    protected function validateReviewFormResponses(ReviewAssignment $reviewAssignment, array $reviewFormResponses): ?JsonResponse
    {
        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');

        foreach ($reviewFormResponses as $reviewFormElementId => $value) {
            $reviewFormElement = $reviewFormElementDao->getById((int) $reviewFormElementId, $reviewAssignment->getReviewFormId());
            if (!$reviewFormElement) {
                return response()->json([
                    'error' => __('api.submissions.reviews.422.invalidReviewFormElement', ['reviewFormElementId' => $reviewFormElementId]),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($reviewFormElement->getRequired() && (is_array($value) ? empty($value) : $value === '')) {
                return response()->json([
                    'error' => __('api.submissions.reviews.422.requiredReviewFormResponse', ['question' => strip_tags($reviewFormElement->getLocalizedQuestion())]),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return null;
    }

    /**
     * Save the reviewer's free-text comments.
     *
     * Only the fields present in the request are affected. A field submitted as an empty
     * string clears the existing comment; a non-empty value creates or updates it.
     */
    protected function saveReviewComments(ReviewAssignment $reviewAssignment, Request $illuminateRequest): void
    {
        if ($illuminateRequest->exists('comments')) {
            $this->saveReviewComment($reviewAssignment, (string) $illuminateRequest->input('comments'), true);
        }
        if ($illuminateRequest->exists('commentsPrivate')) {
            $this->saveReviewComment($reviewAssignment, (string) $illuminateRequest->input('commentsPrivate'), false);
        }
    }

    /**
     * Create, update or delete a single free-text reviewer comment.
     *
     * @param bool $viewable True for the comment shared with the author, false for the editor-only comment.
     */
    protected function saveReviewComment(ReviewAssignment $reviewAssignment, string $comments, bool $viewable): void
    {
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $comment = $submissionCommentDao->getReviewerCommentsByReviewerId(
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getReviewerId(),
            $reviewAssignment->getId(),
            $viewable
        )->next();

        // An empty value clears any existing comment.
        if (strlen($comments) === 0) {
            if ($comment) {
                $submissionCommentDao->deleteObject($comment);
            }
            return;
        }

        if (!$comment) {
            $comment = $submissionCommentDao->newDataObject();
            $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
            $comment->setRoleId(Role::ROLE_ID_REVIEWER);
            $comment->setAssocId($reviewAssignment->getId());
            $comment->setSubmissionId($reviewAssignment->getSubmissionId());
            $comment->setAuthorId($reviewAssignment->getReviewerId());
            $comment->setCommentTitle('');
            $comment->setViewable($viewable);
            $comment->setDatePosted(Core::getCurrentDate());
        }

        $comment->setComments($comments);

        if ($comment->getId()) {
            $submissionCommentDao->updateObject($comment);
        } else {
            $submissionCommentDao->insertObject($comment);
        }
    }

    public function validateReviewFormFieldResponse(ReviewFormElement $reviewFormElement, mixed $response): bool
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
}
