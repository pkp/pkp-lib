<?php

/**
 * @file api/v1/publicPeerReviews/PublicationPeerReviewController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationPeerReviewController
 *
 * @ingroup api_v1_publicationPeerReviews
 *
 * @brief Handle API requests for public peer reviews.
 *
 */

namespace PKP\API\v1\publicationPeerReviews;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\publicationPeerReviews\resources\PublicationPeerReviewSummaryResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\PublicReviewsEnabledPolicy;

class PublicationPeerReviewController extends PKPBaseController
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
        return 'publicationPeerReviews';
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
        Route::prefix('open')->group(function () {
            Route::get('/', $this->getManyOpenReviews(...))
                ->name('publicationPeerReviews.getManyOpenReviews');

            Route::get('{publicationId}', $this->getOpenReview(...))
                ->name('publicationPeerReviews.get')
                ->whereNumber('publicationId');

            Route::get('{publicationId}/summary', $this->getPublicationReviewSummary(...))
                ->name('publicationPeerReviews.publication.summary')
                ->whereNumber('publicationId');
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
}
