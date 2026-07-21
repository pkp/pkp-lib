<?php

/**
 * @file api/v1/peerReviews/PeerReviewController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PeerReviewController
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Handle API requests for public peer reviews.
 *
 */

namespace PKP\API\v1\peerReviews;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewResource;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\PublicAccessPolicy;

class PeerReviewController extends PKPBaseController
{
    /**
     * @copyDoc
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new PublicAccessPolicy());
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @inheritdoc
     */
    public function getHandlerPath(): string
    {
        return 'peerReviews';
    }

    /**
     * @inheritdoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.context',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getGroupRoutes(): void
    {
        Route::prefix('open/submissions')->group(function () {
            Route::get('/', $this->getManySubmissionPeerReviews(...))
                ->name('peerReviews.open.submissions.getMany');

            Route::get('summary', $this->getManySubmissionPeerReviewSummary(...))
                ->name('peerReviews.open.submissions.summary.getMany');

            Route::get('{submissionId}', $this->getSubmissionPeerReview(...))
                ->name('peerReviews.open.submissions.get')
                ->whereNumber('submissionId');

            Route::get('{submissionId}/summary', $this->getSubmissionPeerReviewSummary(...))
                ->name('peerReviews.open.submissions.summary.get')
                ->whereNumber('submissionId');
        });
    }

    /**
     * Get the peer review record for a submission by ID.
     */
    public function getSubmissionPeerReview(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $submissionId = (int)$illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId, $context->getId());

        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            new SubmissionPeerReviewResource($submission),
            Response::HTTP_OK
        );
    }

    /**
     * Get peer review records for a list of submission IDs
     */
    public function getManySubmissionPeerReviews(Request $illuminateRequest): JsonResponse
    {
        $submissionIdsRaw = paramToArray($illuminateRequest->query('submissionIds', []));
        $submissionIds = [];

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        foreach ($submissionIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                return response()->json([
                    'error' => __('api.submission.400.invalidSubmissionId', ['submissionId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $submissionIds[] = (int)$id;
        }

        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterBySubmissionIds($submissionIds)->getMany();

        if ($submissions->count() != count($submissionIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            SubmissionPeerReviewResource::collection($submissions),
            Response::HTTP_OK
        );
    }

    /**
     * Get peer review summary by submission ID.
     */
    public function getSubmissionPeerReviewSummary(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $submissionId = (int)$illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId, $context->getId());

        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            new SubmissionPeerReviewSummaryResource($submission),
            Response::HTTP_OK
        );
    }

    /**
     * Get peer review summaries for a list of submission IDs
     */
    public function getManySubmissionPeerReviewSummary(Request $illuminateRequest): JsonResponse
    {
        $submissionIdsRaw = paramToArray($illuminateRequest->query('submissionIds', []));
        $submissionIds = [];

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        foreach ($submissionIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                return response()->json([
                    'error' => __('api.submission.400.invalidSubmissionId', ['submissionId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $submissionIds[] = (int)$id;
        }

        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterBySubmissionIds($submissionIds)->getMany();

        if ($submissions->count() != count($submissionIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            SubmissionPeerReviewSummaryResource::collection($submissions),
            Response::HTTP_OK
        );
    }
}
