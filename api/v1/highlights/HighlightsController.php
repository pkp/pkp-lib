<?php

/**
 * @file api/v1/highlights/HighlightsController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HighlightsController
 *
 * @ingroup api_v1_highlights
 *
 * @brief Handle API requests for highlights.
 *
 */

namespace PKP\API\v1\highlights;

use APP\facades\Repo;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\highlight\Collector;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class HighlightsController extends PKPBaseController
{
    /** @var int The maximum number of highlights to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'highlights';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('highlight.getMany');

        Route::get('{highlightId}', $this->get(...))
            ->name('highlight.get')
            ->whereNumber('highlightId');

        Route::post('', $this->add(...))
            ->name('highlight.add');

        Route::put('{highlightId}', $this->edit(...))
            ->name('highlight.edit')
            ->whereNumber('highlightId');

        Route::put('order', $this->order(...))
            ->name('highlight.order');

        Route::delete('{highlightId}', $this->delete(...))
            ->name('highlight.delete')
            ->whereNumber('highlightId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        if (!$request->getContext()) {
            $roleAssignments = $this->getSiteRoleAssignments($roleAssignments);
        }

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single highlight
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $highlight = Repo::highlight()->get((int) $illuminateRequest->route('highlightId'), $this->getRequest()->getContext());

        if (!$highlight) {
            return response()->json([
                'error' => __('api.highlights.404.highlightNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::highlight()->getSchemaMap()->map($highlight),
            Response::HTTP_OK
        );
    }

    /**
     * Get a collection of highlights
     *
     * @hook API::highlights::params [$collector, $illuminateRequest]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::highlight()->getCollector()
            ->limit(self::MAX_COUNT)
            ->offset(0);

        if ($this->getRequest()->getContext()) {
            $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);
        } else {
            $collector->withSiteHighlights(Collector::SITE_ONLY);
        }

        Hook::run('API::highlights::params', [$collector, $illuminateRequest]);

        $highlights = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::highlight()->getSchemaMap()->summarizeMany($highlights)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add a highlight
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_HIGHLIGHT, $illuminateRequest->input());
        $params['contextId'] = $context?->getId();
        if (!($params['sequence'] ?? null)) {
            $params['sequence'] = Repo::highlight()->getNextSequence($context?->getId());
        }

        $errors = Repo::highlight()->validate(null, $params, $context);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $highlight = Repo::highlight()->newDataObject($params);

        try {
            $highlightId = Repo::highlight()->add($highlight);
        } catch (StoreTemporaryFileException $e) {
            $highlight = Repo::highlight()->get($highlightId, $context?->getId());
            Repo::highlight()->delete($highlight);
            return response()->json([
                'image' => __('api.400.errorUploadingImage')
            ], Response::HTTP_BAD_REQUEST);
        }

        $highlight = Repo::highlight()->get($highlightId, $context?->getId());

        return response()->json(Repo::highlight()->getSchemaMap()->map($highlight), Response::HTTP_OK);
    }

    /**
     * Edit a highlight
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $highlight = Repo::highlight()->get((int) $illuminateRequest->route('highlightId'), $context?->getId());

        if (!$highlight) {
            return response()->json([
                'error' => __('api.highlights.404.highlightNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_HIGHLIGHT, $illuminateRequest->input());
        $params['id'] = $highlight->getId();

        // Not allowed to change the context of a highlight through the API
        unset($params['contextId']);

        $errors = Repo::highlight()->validate($highlight, $params, $context);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        try {
            Repo::highlight()->edit($highlight, $params);
        } catch (Exception $e) {
            Repo::highlight()->delete($highlight);
            return response()->json([
                'image' => __('api.400.errorUploadingImage')
            ], Response::HTTP_BAD_REQUEST);
        }

        $highlight = Repo::highlight()->get($highlight->getId(), $context?->getId());

        return response()->json(Repo::highlight()->getSchemaMap()->map($highlight), Response::HTTP_OK);
    }

    /**
     * Order the highlights
     */
    public function order(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $params = $illuminateRequest->input();
        $sequence = (array) $params['sequence'];

        if (empty($sequence)) {
            return response()->json([
                'sequence' => __('api.highlights.400.noOrderData'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $highlights = array_map(
            function ($item) use ($context) {
                return isset($item['id']) && isset($item['sequence'])
                    ? Repo::highlight()->get($item['id'], $context?->getId())
                    : null;
            },
            $sequence
        );

        if (in_array(null, $highlights)) {
            return response()->json([
                'sequence' => __('api.highlights.400.orderHighlightNotFound'),
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($highlights as $index => $highlight) {
            Repo::highlight()->edit($highlight, ['sequence' => $sequence[$index]['sequence']]);
        }

        $collector = Repo::highlight()
            ->getCollector()
            ->limit(self::MAX_COUNT);

        if ($context) {
            $collector->filterByContextIds([$context->getId()]);
        } else {
            $collector->withSiteHighlights(Collector::SITE_ONLY);
        }

        $highlights = $collector->getMany();

        return response()->json([
            'items' => Repo::highlight()->getSchemaMap()->summarizeMany($highlights)->values(),
            'itemsMax' => $highlights->count(),
        ], Response::HTTP_OK);
    }

    /**
     * Delete a highlight
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $highlight = Repo::highlight()->get((int) $illuminateRequest->route('highlightId'), $context?->getId());

        if (!$highlight) {
            return response()->json([
                'error' => __('api.highlights.404.highlightNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $highlightProps = Repo::highlight()->getSchemaMap()->map($highlight);

        Repo::highlight()->delete($highlight);

        return response()->json($highlightProps, Response::HTTP_OK);
    }

    /**
     * Modify the role assignments so that only
     * site admins have access
     */
    protected function getSiteRoleAssignments(array $roleAssignments): array
    {
        return array_filter($roleAssignments, fn ($key) => $key == Role::ROLE_ID_SITE_ADMIN, ARRAY_FILTER_USE_KEY);
    }
}
