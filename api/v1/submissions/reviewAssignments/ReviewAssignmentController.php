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
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;

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

            Route::put('{reviewAssignmentId}', $this->edit(...))
                ->name('submission.reviewAssignment.edit')
                ->whereNumber('reviewAssignmentId');

            Route::put('{reviewAssignmentId}/consider', $this->consider(...))
                ->name('submission.reviewAssignment.consider')
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
    public function edit(Request $illuminateRequest): JsonResponse
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
}
