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
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEntry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

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

        $reviewerId = $request->getUser()->getId();
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
        $publication = $submission->getCurrentPublication();
        //$publicationTitlePrefix = $publication->getData('prefix');
        $publicationTitle = $publication->getData('title');

        $section = Repo::section()->get($submission->getSectionId());
        $publicationType = $section->getData('title');
        $publicationAbstract = $publication->getData('abstract');
        $publicationKeywords = $publication->getData('keywords');

        $declineEmail = null;
        if ($reviewAssignment->getDeclined()) {
            $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
            $emailLogs = $submissionEmailLogDao->getBySenderId($submissionId, SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_DECLINE, $reviewerId)->toArray();
            foreach ($emailLogs as $emailLog) {
                $dateSent = substr($emailLog->getData('dateSent'), 0, 10);
                $dateConfirmed = substr($reviewAssignment->getData('dateConfirmed'), 0, 10);
                // Compare the dates to get the decline email associated to the current round.
                if ($dateSent === $dateConfirmed) {
                    $declineEmail = [
                        'subject' => $emailLog->getData('subject'),
                        'body' => $emailLog->getData('body'),
                    ];
                    break;
                }
            }
        }

        $reviewAssignmentProps = Repo::reviewAssignment()->getSchemaMap()->map($reviewAssignment);
        // It doesn't seem we can translate the recommendation inside the vue page as it's a dynamic label key.
        $recommendation = $reviewAssignment->getLocalizedRecommendation();

        $reviewAssignmentId = $reviewAssignment->getId();
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $allSubmissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewerId, $reviewAssignmentId)->toArray();
        $viewableComments = [];
        $privateComments = [];
        foreach ($allSubmissionComments as $submissionComment) {
            $comments = $submissionComment->getData('comments');
            if ($submissionComment->getData('viewable')) {
                $viewableComments[] = $comments;
            } else {
                $privateComments[] = $comments;
            }
        }

        $genreDao = DAORegistry::getDAO('GenreDAO');
        $fileGenres = $genreDao->getByContextId($contextId)->toArray();

        $attachments = Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewRoundIds([$reviewRoundId])
            ->filterByUploaderUserIds([$reviewerId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT])
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, [$reviewAssignmentId])
            ->getMany();
        $attachmentsProps = Repo::submissionFile()
            ->getSchemaMap()
            ->mapMany($attachments, $fileGenres)
            ->toArray();

        $lastReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByLastReviewRound(true)
            ->getMany()
            ->first();

        $filesProps = [];
        if ($lastReviewAssignment->getDeclined() != 1) {
            $files = Repo::submissionFile()->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->filterByReviewRoundIds([$reviewRoundId])
                ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ROUND, [$reviewAssignmentId])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_FILE])
                ->getMany();
            $filesProps = Repo::submissionFile()
                ->getSchemaMap()
                ->mapMany($files, $fileGenres)
                ->toArray();
        }

        $reviewRoundHistory = [
            'publicationTitle' => $publicationTitle,
            'publicationType' => $publicationType,
            'publicationAbstract' => $publicationAbstract,
            'publicationKeywords' => $publicationKeywords,
            'declineEmail' => $declineEmail,
            'reviewAssignment' => $reviewAssignmentProps,
            'recommendation' => $recommendation,
            'comments' => $viewableComments,
            'privateComments' => $privateComments,
            'attachments' => array_values($attachmentsProps),
            'files' => array_values($filesProps),
        ];

        return response()->json($reviewRoundHistory, Response::HTTP_OK);
    }
}
