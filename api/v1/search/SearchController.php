<?php

/**
 * @file api/v1/search/SearchController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchController
 *
 * @brief Controller class to handle API requests for search operations.
 *
 */

namespace PKP\API\v1\search;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;

class SearchController extends PKPBaseController
{
    /** @var int The maximum number of sections to return in one request */
    public const MAX_FACETS_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'search';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('facets/keywords', $this->getKeywords(...))
            ->name('keywords.getMany');

        Route::get('facets/subjects', $this->getSubjects(...))
            ->name('subjects.getMany');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a list of available keywords
     *
     * @hook API::sections::params [$collector, $illuminateRequest]
     */
    public function getKeywords(Request $illuminateRequest): JsonResponse
    {
        $searchEngine = app(\Laravel\Scout\EngineManager::class)->engine();
        $request = $this->getRequest();
        return response()->json([
            'items' => $searchEngine->getFacets(
                $request->getContext()->getId(),
                'keywords',
                $request->getUserVar('filter'),
                self::MAX_FACETS_COUNT
            ),
        ], Response::HTTP_OK);
    }

    /**
     * Get a list of available subjects
     *
     * @hook API::sections::params [$collector, $illuminateRequest]
     */
    public function getSubjects(Request $illuminateRequest): JsonResponse
    {
        $searchEngine = app(\Laravel\Scout\EngineManager::class)->engine();
        $request = $this->getRequest();
        return response()->json([
            'items' => $searchEngine->getFacets(
                $request->getContext()->getId(),
                'subjects',
                $request->getUserVar('filter'),
                self::MAX_FACETS_COUNT
            ),
        ], Response::HTTP_OK);
    }
}
