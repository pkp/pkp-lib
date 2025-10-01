<?php

/**
 * @file api/v1/navigations/PKPNavigationController.php
 *
 * Copyright (c) 2023-2025 Simon Fraser University
 * Copyright (c) 2023-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationController
 *
 * @ingroup api_v1_navigation
 *
 * @brief Handle API requests for navigation operations.
 *
 */

namespace PKP\API\v1\navigations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\navigations\resources\NavigationResource;
use PKP\core\PKPBaseController;
use PKP\navigationMenu\models\NavigationMenu;

class PKPNavigationController extends PKPBaseController
{
    /** @var array Routes that can be accessed without user authentication */
    public array $publicAccessRoutes = [
        'get', // Allow public access to navigation endpoint
    ];


    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'navigations';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{areaName}/public', $this->getPublic(...))
            ->name('navigation.get')
            ->where('areaName', '[0-9]+|[a-zA-Z0-9\-_]+');
    }

    /**
     * Get navigation menu by ID with formatted menu items and nesting
     */
    public function getPublic(Request $illuminateRequest): JsonResponse
    {
        $navigationMenu = NavigationMenu::where('area_name', $illuminateRequest->route('areaName'))
            ->where('context_id', $this->getRequest()->getContext()->getId())->first();

        if (!$navigationMenu) {
            return response()->json([
                'error' => 'Navigation menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            (new NavigationResource($navigationMenu))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

}
