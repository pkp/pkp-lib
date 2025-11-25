<?php

/**
 * @file api/v1/peerReviews/peerReviewController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class peerReviewController
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
use PKP\API\v1\peerReviews\resources\PublicationPeerReviewSummaryResource;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\PublicReviewsEnabledPolicy;

class peerReviewController extends PKPBaseController
{
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new PublicReviewsEnabledPolicy($request->getContext()));
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
        Route::prefix('open/publications')->group(function () {
            Route::get('/', $this->getManyOpenReviews(...))
                ->name('peerReviews.getManyOpenReviews');

            Route::get('{publicationId}', $this->getOpenReview(...))
                ->name('peerReviews.get')
                ->whereNumber('publicationId');

            Route::get('/summary', $this->getPublicationReviewSummary(...))
                ->name('peerReviews.publication.summary');

            Route::get('{publicationId}/summary', $this->getPublicationReviewSummary(...))
                ->name('peerReviews.publication.summary')
                ->whereNumber('publicationId');
        });

        Route::prefix('open/submissions')->group(function () {
            Route::get('{submissionId}/summary', $this->getSubmissionPeerReviewSummary(...))
                ->name('peerReviews.open.submissions.summary')
                ->whereNumber('submissionId');

            Route::get('summary', $this->getManySubmissionPeerReviewSummary(...));
        });
    }
    /**
     * Get peer review for a list of publications
     *  Filters available via query params:
     *  ```
     *  publicationIds(array, required) - publication IDs to retrieve peer review data for.
     *  ```
     */
    public function getManyOpenReviews(Request $illuminateRequest): JsonResponse
    {
        $publicationIdsRaw = paramToArray($illuminateRequest->query('publicationIds', []));
        $publicationIds = [];

        foreach ($publicationIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                return response()->json([
                    'error' => __('api.publication.400.invalidPublicationId', ['publicationId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $publicationIds[] = (int)$id;
        }

        $publications = Repo::publication()->getCollector()
            ->filterByPublicationIds($publicationIds)
            ->getMany();

        if ($publications->count() != count($publicationIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::publication()->getPeerReviews($publications->all()),
            Response::HTTP_OK
        );
    }

    /**
     * Get peer review for a publication by ID
     */
    public function getOpenReview(Request $illuminateRequest): JsonResponse
    {
        $publicationId = (int)$illuminateRequest->route('publicationId');
        $publication = Repo::publication()->get($publicationId);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::publication()->getPeerReviews([$publication])->first(),
            Response::HTTP_OK
        );
    }

    public function getPublicationReviewSummary(Request $illuminateRequest): JsonResponse
    {
        $publicationId = (int)$illuminateRequest->route('publicationId');
        $publication = Repo::publication()->get($publicationId);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }


        return response()->json(
            new PublicationPeerReviewSummaryResource($publication),
            Response::HTTP_OK
        );
    }

    public function getManyPublicationReviewSummaries(Request $illuminateRequest): JsonResponse
    {
        $publicationIdsRaw = paramToArray($illuminateRequest->query('publicationIds', []));
        $publicationIds = [];

        foreach ($publicationIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                return response()->json([
                    'error' => __('api.publication.400.invalidPublicationId', ['publicationId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $publicationIds[] = (int)$id;
        }

        $publications = Repo::publication()->getCollector()
            ->filterByPublicationIds($publicationIds)
            ->getMany();

        if ($publications->count() != count($publicationIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $summaries = $publications->map(function ($publication) {
            return new PublicationPeerReviewSummaryResource($publication);
        });

        return response()->json(
            $summaries->all(),
            Response::HTTP_OK
        );
    }

    public function getSubmissionPeerReviewSummary(Request $illuminateRequest): JsonResponse
    {
        $publicationId = (int)$illuminateRequest->route('publicationId');
        $publication = Repo::publication()->get($publicationId);

        $submissionId = (int)$illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);

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

    public function getManySubmissionPeerReviewSummary(Request $illuminateRequest)
    {
        $submissionIdsRaw = paramToArray($illuminateRequest->query('submissionIds', []));
        $submissionIds = [];

        foreach ($submissionIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                // TODO add submission locale
                return response()->json([
                    'error' => __('api.publication.400.invalidPublicationId', ['publicationId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $submissionIds[] = (int)$id;
        }

        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([Application::SITE_CONTEXT_ID_ALL])
            ->filterBySubmissionIds($submissionIds)->getMany();

        if ($submissions->count() != count($submissionIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }




        $summaries = $submissions->map(function ($submission) {
            return new SubmissionPeerReviewSummaryResource($submission);
        });

        return response()->json(
            $summaries->values(),
            Response::HTTP_OK
        );
    }
}
