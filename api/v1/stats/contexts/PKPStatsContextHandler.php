<?php

/**
 * @file api/v1/stats/contexts/PKPStatsContextHandler.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for context statistics.
 *
 */

namespace PKP\API\v1\stats\contexts;

use APP\core\Services;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
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
                    'pattern' => $this->getEndpointPattern() . '/timeline',
                    'handler' => [$this, 'getManyTimeline'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN]
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}/timeline',
                    'handler' => [$this, 'getTimeline'],
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
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get total views of the homepages for a set of contexts
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

        Hook::call('API::stats::contexts::params', [&$allowedParams, $slimRequest]);

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
                if ($responseCSV) {
                    $csvColumnNames = $this->_getContextReportColumnNames();
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
        }

        // Get a list of contexts with their total views matching the params
        $statsService = Services::get('contextStats');
        $totalMetrics = $statsService->getTotals($allowedParams);

        // Get the stats for each context
        $items = [];
        foreach ($totalMetrics as $totalMetric) {
            $contextId = $totalMetric->context_id;
            $contextViews = $totalMetric->metric;

            if ($responseCSV) {
                $items[] = $this->getItemForCSV($contextId, $contextViews);
            } else {
                $items[] = $this->getItemForJSON($slimRequest, $contextId, $contextViews);
            }
        }

        $itemsMax = $statsService->getCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getContextReportColumnNames();
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Get a monthly or daily timeline of total views for a set of contexts
     */
    public function getManyTimeline(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
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

        Hook::call('API::stats::contexts::timeline::params', [&$allowedParams, $slimRequest]);

        if (!$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
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

        Hook::call('API::stats::context::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $dateStart = array_key_exists('dateStart', $allowedParams) ? $allowedParams['dateStart'] : null;
        $dateEnd = array_key_exists('dateEnd', $allowedParams) ? $allowedParams['dateEnd'] : null;

        $statsService = Services::get('contextStats');
        $contextViews = $statsService->getTotal($context->getId(), $dateStart, $dateEnd);

        // Get basic context details for display
        $propertyArgs = [
            'request' => $request,
            'slimRequest' => $slimRequest,
        ];
        $contextProps = Services::get('context')->getSummaryProperties($context, $propertyArgs);
        return $response->withJson([
            'total' => $contextViews,
            'context' => $contextProps
        ], 200);
    }

    /**
     * Get a monthly or daily timeline of total views for a context
     */
    public function getTimeline(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
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

        $allowedParams['contextIds'] = [$context->getId()];

        Hook::call('API::stats::context::timeline::params', [&$allowedParams, $slimRequest]);

        if (!$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimelineInterval');
        }

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
            return array_intersect($contextIds, $searchPhraseContextIds->toArray());
        }
        return $searchPhraseContextIds->toArray();
    }

    /**
     * Get CSV report columns
     */
    protected function _getContextReportColumnNames(): array
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
    protected function getItemForCSV(int $contextId, int $contextViews): array
    {
        // Get context title for display
        $contexts = Services::get('context')->getManySummary([]);
        $context = array_filter($contexts, function ($context) use ($contextId) {
            return $context->id == $contextId;
        });
        $title = current($context)->name();
        return [
            $contextId,
            $title,
            $contextViews
        ];
    }

    /**
     * Get JSON data with context index page metrics
     */
    protected function getItemForJSON(SlimHttpRequest $slimRequest, int $contextId, int $contextViews): array
    {
        // Get basic context details for display
        $propertyArgs = [
            'request' => $this->getRequest(),
            'slimRequest' => $slimRequest,
        ];
        $context = Services::get('context')->get($contextId);
        $contextProps = Services::get('context')->getSummaryProperties($context, $propertyArgs);
        return [
            'total' => $contextViews,
            'context' => $contextProps,
        ];
    }

    /**
     * Check if the timeline interval is valid
     */
    protected function isValidTimelineInterval(string $interval): bool
    {
        return in_array($interval, [
            PKPStatisticsHelper::STATISTICS_DIMENSION_DAY,
            PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH
        ]);
    }
}
