<?php

/**
 * @file api/v1/highlights/HighlightsHandler.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HighlightsHandler
 *
 * @ingroup api_v1_highlights
 *
 * @brief Handle API requests for highlights.
 *
 */

namespace PKP\API\v1\highlights;

use APP\facades\Repo;
use Exception;
use PKP\core\APIResponse;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\handler\APIHandler;
use PKP\highlight\Collector;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use Slim\Http\Request as SlimRequest;

class HighlightsHandler extends APIHandler
{
    /** @var int The maximum number of highlights to return in one request */
    public const MAX_COUNT = 100;

    public function __construct()
    {
        $this->_handlerPath = 'highlights';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{highlightId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{highlightId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/order',
                    'handler' => [$this, 'order'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{highlightId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
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
    public function get(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $highlight = Repo::highlight()->get((int) $args['highlightId'], $this->getRequest()->getContext());

        if (!$highlight) {
            return $response->withStatus(404)->withJsonError('api.highlights.404.highlightNotFound');
        }

        return $response->withJson(
            Repo::highlight()
                ->getSchemaMap()
                ->map($highlight)
            , 200
        );
    }

    /**
     * Get a collection of highlights
     */
    public function getMany(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $collector = Repo::highlight()->getCollector()
            ->limit(self::MAX_COUNT)
            ->offset(0);

        if ($this->getRequest()->getContext()) {
            $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);
        } else {
            $collector->withSiteHighlights(Collector::SITE_ONLY);
        }

        Hook::run('API::highlights::params', [$collector, $slimRequest]);

        $highlights = $collector->getMany();

        return $response->withJson([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::highlight()->getSchemaMap()->summarizeMany($highlights)->values(),
        ], 200);
    }

    /**
     * Add a highlight
     */
    public function add(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_HIGHLIGHT, $slimRequest->getParsedBody());
        $params['contextId'] = $context?->getId();
        if (!$params['sequence']) {
            $params['sequence'] = Repo::highlight()->getNextSequence($context?->getId());
        }

        $errors = Repo::highlight()->validate(null, $params, $context);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $highlight = Repo::highlight()->newDataObject($params);

        try {
            $highlightId = Repo::highlight()->add($highlight);
        } catch (StoreTemporaryFileException $e) {
            $highlight = Repo::highlight()->get($highlightId, $context?->getId());
            Repo::highlight()->delete($highlight);
            return $response->withStatus(400)->withJson([
                'image' => __('api.400.errorUploadingImage')
            ]);
        }

        $highlight = Repo::highlight()->get($highlightId, $context?->getId());

        return $response->withJson(Repo::highlight()->getSchemaMap()->map($highlight), 200);
    }

    /**
     * Edit a highlight
     */
    public function edit(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();

        $highlight = Repo::highlight()->get((int) $args['highlightId'], $context?->getId());

        if (!$highlight) {
            return $response->withStatus(404)->withJsonError('api.highlights.404.highlightNotFound');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_HIGHLIGHT, $slimRequest->getParsedBody());
        $params['id'] = $highlight->getId();

        // Not allowed to change the context of a highlight through the API
        unset($params['contextId']);

        $errors = Repo::highlight()->validate($highlight, $params, $context);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        try {
            Repo::highlight()->edit($highlight, $params);
        } catch (Exception $e) {
            Repo::highlight()->delete($highlight);
            return $response->withStatus(400)->withJson([
                'image' => __('api.highlights.400.errorUploadingImage')
            ]);
        }

        $highlight = Repo::highlight()->get($highlight->getId(), $context?->getId());

        return $response->withJson(Repo::highlight()->getSchemaMap()->map($highlight), 200);
    }

    /**
     * Order the highlights
     */
    public function order(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();

        $params = $slimRequest->getParsedBody();
        $sequence = (array) $params['sequence'];

        if (empty($sequence)) {
            return $response->withStatus(400)->withJson(['sequence' => __('api.highlights.400.noOrderData')]);
        }

        $highlights = array_map(
            function($item) use ($context) {
                return isset($item['id']) && isset($item['sequence'])
                    ? Repo::highlight()->get($item['id'], $context?->getId())
                    : null;
            },
            $sequence
        );

        if (in_array(null, $highlights)) {
            return $response->withStatus(400)->withJson(['sequence' => __('api.highlights.400.orderHighlightNotFound')]);
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

        return $response->withJson([
            'items' => Repo::highlight()->getSchemaMap()->summarizeMany($highlights)->values(),
            'itemsMax' => $highlights->count(),
        ], 200);
    }

    /**
     * Delete a highlight
     */
    public function delete(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();

        $highlight = Repo::highlight()->get((int) $args['highlightId'], $context?->getId());

        if (!$highlight) {
            return $response->withStatus(404)->withJsonError('api.highlights.404.highlightNotFound');
        }

        $highlightProps = Repo::highlight()->getSchemaMap()->map($highlight);

        Repo::highlight()->delete($highlight);

        return $response->withJson($highlightProps, 200);
    }

    /**
     * Modify the role assignments so that only
     * site admins have access
     */
    protected function getSiteRoleAssignments(array $roleAssignments): array
    {
        return array_filter($roleAssignments, fn($key) => $key == Role::ROLE_ID_SITE_ADMIN, ARRAY_FILTER_USE_KEY);
    }
}
