<?php

/**
 * @file api/v1/reviews/PKPReviewController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewController
 *
 * @ingroup api_v1_reviews
 *
 * @brief Handle API requests for reviews operations.
 *
 */

namespace PKP\API\v1\reviews;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEntry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPReviewController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'reviews';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     * @throws \Exception
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_REVIEWER,
            ]),
        ];
    }

    /**
     * @throws \Exception
     */
    public function getGroupRoutes(): void
    {
        Route::get('history/{submissionId}/{reviewRoundId}', $this->getHistory(...))
            ->name('review.get.submission.round.history')
            ->whereNumber(['reviewRoundId', 'submissionId']);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if ($actionName === 'getHistory') {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', true));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get reviewer's submission round history
     * @throws \Exception
     */
    public function getHistory(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        // TODO: get the reviewer ID from the request or from the route?
        $reviewerId = $request->getUser()->getId();
        //$reviewerId = $illuminateRequest->route('reviewerId');
        $submissionId = $illuminateRequest->route('submissionId');
        $reviewRoundId = $illuminateRequest->route('reviewRoundId');

        $reviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByReviewRoundIds([$reviewRoundId])
            ->getMany()
            ->first();

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = Repo::submission()->get($submissionId, $contextId);
        $publicationTitle = $submission->getCurrentPublication()->getLocalizedTitle();

        $declineEmail = null;
        if (!$reviewAssignment->getDeclined()) {
            $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
            $emailLogs = $submissionEmailLogDao->getBySenderId($submissionId, SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_DECLINE, $reviewerId)->toArray();
            // TODO: look for the right email log with the right round number.
            if ($emailLogs) {
                $emailLog = $emailLogs[0];
                $declineEmail = [
                    'subject' => $emailLog->getData('subject'),
                    'body' => $emailLog->getData('body'),
                ];
            }
        }

        $reviewAssignmentProps = Repo::reviewAssignment()->getSchemaMap()->map($reviewAssignment);
        $recommendation = $reviewAssignment->getLocalizedRecommendation();

        $reviewAssignmentId = $reviewAssignment->getId();
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $comments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewerId, $reviewAssignmentId, true)->toArray();
        $privateComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewerId, $reviewAssignmentId, false)->toArray();

        // TODO: get the comments data...

        $lastReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByLastReviewRound(true)
            ->getMany()
            ->first();
        $displayFiles = $lastReviewAssignment->getDeclined() != 1;

        $reviewRoundHistoryProps = [
            'publicationTitle' => $publicationTitle,
            'declineEmail' => $declineEmail,
            'reviewAssignment' => $reviewAssignmentProps,
            'recommendation' => $recommendation,
            'comments' => $comments,
            'privateComments' => $privateComments,
            'displayFiles' => $displayFiles,
        ];

        return response()->json($reviewRoundHistoryProps, Response::HTTP_OK);
    }
}
