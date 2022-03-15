<?php

/**
 * @file api/v1/stats/PKPStatsContextHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for context statistics.
 *
 */

use APP\core\Services;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\statistics\PKPStatisticsHelper;
use Slim\Http\Request as SlimHttpRequest;

class PKPStatsContextHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'stats/contexts';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN]
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/index',
                    'handler' => [$this, 'getManyIndex'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN]
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}/index',
                    'handler' => [$this, 'getIndex'],
                    'roles' => $roles
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $routeName = null;
        $slimRequest = $this->getSlimRequest();

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get usage stats for a set of contexts
     *
     * Returns total index page views.
     */
    public function getMany(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            'contextIds',
        ]);

        HookRegistry::call('API::stats::contexts::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        if (!in_array($allowedParams['orderDirection'], [PKPStatisticsHelper::STATISTICS_ORDER_ASC, PKPStatisticsHelper::STATISTICS_ORDER_DESC])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.invalidOrderDirection');
        }

        // Identify contexts which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedContextIds = empty($allowedParams['contextIds']) ? [] : $allowedParams['contextIds'];
            $allowedParams['contextIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedContextIds);

            if (empty($allowedParams['contextIds'])) {
                $csvColumnNames = $this->_getContextReportColumnNames();
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            }
        }

        // Get a list (count number) of top contexts by their views
        $statsService = Services::get('contextStats');
        $totalMetrics = $statsService->getTotalMetrics($allowedParams);

        // Get the stats for each context
        $items = [];
        foreach ($totalMetrics as $totalMetric) {
            if (empty($totalMetric->context_id)) {
                continue;
            }
            $contextId = $totalMetric->context_id;
            $contextViews = $totalMetric->metric;

            if ($responseCSV) {
                $items[] = $this->getCSVItem($contextId, $contextViews);
            } else {
                $items[] = $this->getJSONItem($slimRequest, $contextId, $contextViews);
            }
        }

        $itemsMaxParams = $allowedParams;
        unset($itemsMaxParams['count']);
        unset($itemsMaxParams['offset']);
        $itemsMax = $statsService->getTotalCount($itemsMaxParams);

        $csvColumnNames = $this->_getContextReportColumnNames();
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get the total index page views for a set of contexts
     * in a timeline broken down month or day
     */
    public function getManyIndex(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            'contextIds',
        ]);

        HookRegistry::call('API::stats::contexts::index::params', [&$allowedParams, $slimRequest]);

        if (!in_array($allowedParams['timelineInterval'], [PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimelineInterval');
        }

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        // Identify contexts which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedContextIds = empty($allowedParams['contextIds']) ? [] : $allowedParams['contextIds'];
            $allowedParams['contextIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedContextIds);

            if (empty($allowedParams['contextIds'])) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = Services::get('contextStats')->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            }
        }

        $data = Services::get('contextStats')->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get a single context's usage statistics
     */
    public function get(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();

        $context = Services::get('context')->get((int) $args['contextId']);
        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }
        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        $allowedParams = $this->_processAllowedParams($slimRequest->getQueryParams(), [
            'dateStart',
            'dateEnd',
        ]);

        HookRegistry::call('API::stats::context::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $allowedParams['contextIds'] = $context->getId();

        $contextViews = 0;
        $statsService = Services::get('contextStats');
        $contextMetrics = $statsService->getMetricsForContext($allowedParams);
        if (!empty($contextMetrics)) {
            $contextViews = (int) current($contextMetrics)->metric;
        }

        // Get basic context details for display
        // Stats may exist for deleted contexts
        $contextProps = ['id' => $context->getId()];
        $propertyArgs = [
            'request' => $request,
            'slimRequest' => $slimRequest,
        ];
        $context = Services::get('context')->get($context->getId());
        if ($context) {
            $contextProps = Services::get('context')->getSummaryProperties($context, $propertyArgs);
        }

        return $response->withJson([
            'contextViews' => $contextViews,
            'contextProps' => $contextProps
        ], 200);
    }

    /**
     * Get the total index pages views for a context broken down by
     * month or day
     */
    public function getIndex(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();

        $context = Services::get('context')->get((int) $args['contextId']);
        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }
        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
        ]);

        $allowedParams['contextIds'] = $context->getId();

        HookRegistry::call('API::stats::context::index::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = Services::get('contextStats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * A helper method to filter and sanitize the request params
     *
     * Only allows the specified params through and enforces variable
     * type where needed.
     */
    protected function _processAllowedParams(array $requestParams, array $allowedParams): array
    {
        $returnParams = [];
        foreach ($requestParams as $requestParam => $value) {
            if (!in_array($requestParam, $allowedParams)) {
                continue;
            }
            switch ($requestParam) {
                case 'dateStart':
                case 'dateEnd':
                case 'timelineInterval':
                    $returnParams[$requestParam] = $value;
                    break;

                case 'count':
                    $returnParams[$requestParam] = min(100, (int) $value);
                    break;

                case 'offset':
                    $returnParams[$requestParam] = (int) $value;
                    break;

                case 'orderDirection':
                    $returnParams[$requestParam] = strtoupper($value);
                    break;
                case 'contextIds':
                    if (is_string($value) && strpos($value, ',') > -1) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $returnParams[$requestParam] = array_map('intval', $value);
                    break;
            }
        }
        return $returnParams;
    }

    /**
     * A helper method to get the contextIds param when a searchPhase
     * param is also passed.
     *
     * If the searchPhrase and contextIds params were both passed in the
     * request, then we only return IDs that match both conditions.
     */
    protected function _processSearchPhrase(string $searchPhrase, array $contextIds = []): array
    {
        $searchPhraseContextIds = Services::get('context')->getIds(['searchPhrase' => $searchPhrase]);

        if (!empty($contextIds)) {
            $contextIds = array_intersect($contextIds, $searchPhraseContextIds->toArray());
        } else {
            $contextIds = $searchPhraseContextIds->toArray();
        }
        return $contextIds;
    }

    /**
     * Get CSV report columns
     */
    private function _getContextReportColumnNames(): array
    {
        return [
            __('common.id'),
            __('common.title'),
            __('stats.total'),
        ];
    }

    /**
     * Get CSV row with context index page metrics
     */
    protected function getCSVItem(int $contextId, int $contextViews): array
    {
        // Get context title for display
        // Now that we use foreign keys, the stats should not exist for deleted contexts, but consider it however?
        $title = '';
        $context = Services::get('context')->get($contextId);
        if ($context) {
            $title = $context->getLocalizedName();
        }
        return [
            $contextId,
            $title,
            $contextViews
        ];
    }

    /**
     * Get JSON data with context index page metrics
     */
    protected function getJSONItem(SlimHttpRequest $slimRequest, int $contextId, int $contextViews): array
    {
        // Get basic context details for display
        // Now that we use foreign keys, the stats should not exist for deleted contexts, but consider it however?
        $contextProps = ['id' => $contextId];
        $propertyArgs = [
            'request' => $this->getRequest(),
            'slimRequest' => $slimRequest,
        ];
        $context = Services::get('context')->get($contextId);
        if ($context) {
            $contextProps = Services::get('context')->getSummaryProperties($context, $propertyArgs);
        }
        return [
            'contextViews' => $contextViews,
            'context' => $contextProps,
        ];
    }
}
