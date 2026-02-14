<?php

/**
 * @file api/v1/genres/GenreController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreController
 *
 * @ingroup api_v1_genres
 *
 * @brief Handle API requests for genre operations.
 *
 */

namespace PKP\API\v1\genres;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\genres\resources\GenreResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\db\DBResultRange;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\GenreDAO;

class GenreController extends PKPBaseController
{
    /** @var int The default number of genres to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of genres to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'genres';
    }

    /**
     * @inheritDoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...))
                ->name('genre.getMany');

            Route::get('/{genreId}', $this->get(...))
                ->name('genre.get')
                ->whereNumber('genreId');
        });
    }

    /**
     * @inheritDoc
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of genres for the current context
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        // Handles with DB-level LIMIT/OFFSET via DBResultRange and count from a direct DB::table('genres') query (see below).
        $count = self::DEFAULT_COUNT;
        $offset = 0;

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'count':
                    $count = min((int) $val, self::MAX_COUNT);
                    break;
                case 'offset':
                    $offset = (int) $val;
                    break;
            }
        }

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');

        $totalCount = DB::table('genres')
            ->where('context_id', $context->getId())
            ->count();

        $results = $genreDao->getByContextId($context->getId(), new DBResultRange($count, null, $offset));

        $genresCollection = collect($results->toArray());

        return response()->json([
            'itemsMax' => $totalCount,
            'items' => GenreResource::collection($genresCollection)->resolve(),
        ], Response::HTTP_OK);
    }

    /**
     * Get a single genre
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genre = $genreDao->getById((int) $illuminateRequest->route('genreId'), $context->getId());

        if (!$genre) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            (new GenreResource($genre))->resolve(),
            Response::HTTP_OK
        );
    }
}
