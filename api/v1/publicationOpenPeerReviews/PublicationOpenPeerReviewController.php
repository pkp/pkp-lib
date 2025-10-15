<?php

/**
 * @file api/v1/publicPeerReviews/PublicationOpenPeerReviewController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationOpenPeerReviewController
 *
 * @ingroup api_v1_publicationOpenPeerReviews
 *
 * @brief Handle API requests for public peer reviews.
 *
 */

namespace PKP\API\v1\publicationOpenPeerReviews;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;

class PublicationOpenPeerReviewController extends PKPBaseController
{
    /**
     * @inheritdoc
     */
    public function getHandlerPath(): string
    {
        return 'publicationOpenPeerReviews';
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
        Route::get('', $this->getMany(...))
            ->name('publicationOpenPeerReviews.getMany');

        Route::get('{publicationId}', $this->get(...))
            ->name('publicationOpenPeerReviews.get')
            ->whereNumber('publicationId');
    }

    /**
     * Get peer review for a list of publications
     *  Filters available via query params:
     *  ```
     *  publicationIds(array, required) - publication IDs to retrieve peer review data for.
     *  ```
     */
    public function getMany(Request $illuminateRequest): JsonResponse
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
            Repo::publication()->getPeerReviews($publications),
            Response::HTTP_OK
        );
    }

    /**
     * Get peer review for a publication by ID
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $publicationId = (int)$illuminateRequest->route('publicationId');
        $publication = Repo::publication()->get($publicationId);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::publication()->getPeerReviews(collect([$publication]))->first(),
            Response::HTTP_OK
        );
    }
}
