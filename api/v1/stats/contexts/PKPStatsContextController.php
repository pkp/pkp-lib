<?php

/**
 * @file api/v1/stats/contexts/PKPStatsContextController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextController
 *
 * @ingroup api_v1_stats
 *
 * @brief Controller class to handle API requests for context statistics.
 *
 */

namespace PKP\API\v1\stats\contexts;

use APP\services\ContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPStatsContextService;
use PKP\statistics\PKPStatisticsHelper;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PKPStatsContextController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'stats/contexts';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('timeline', $this->getManyTimeline(...))
            ->name('stats.context.multipleContextTimeline');

        Route::get('{contextId}/timeline', $this->getTimeline(...))
            ->name('stats.context.contextTimeline')
            ->whereNumber('contextId');

        Route::get('{contextId}', $this->get(...))
            ->name('stats.context.contextStat')
            ->whereNumber('contextId');

        Route::get('', $this->getMany(...))
            ->name('stats.context.multipleContextStat');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
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
     *
     * @hook API::stats::contexts::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];

        $requestParams = array_merge($defaultParams, $illuminateRequest->query());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            'contextIds',
        ]);

        Hook::call('API::stats::contexts::params', [&$allowedParams, $illuminateRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json([
                'error' => $result,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($allowedParams['orderDirection'], [PKPStatisticsHelper::STATISTICS_ORDER_ASC, PKPStatisticsHelper::STATISTICS_ORDER_DESC])) {
            return response()->json([
                'error' => __('api.stats.400.invalidOrderDirection'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Identify contexts which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedContextIds = empty($allowedParams['contextIds']) ? [] : $allowedParams['contextIds'];
            $allowedParams['contextIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedContextIds);

            if (empty($allowedParams['contextIds'])) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getContextReportColumnNames();
                    return response()->withFile([], $csvColumnNames, 0);
                }
                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }
        }

        // Get a list of contexts with their total views matching the params
        $statsService = app()->get('contextStats'); /** @var PKPStatsContextService $statsService */
        $totalMetrics = $statsService->getTotals($allowedParams);

        // Get the stats for each context
        $items = [];
        foreach ($totalMetrics as $totalMetric) {
            $contextId = $totalMetric->context_id;
            $contextViews = $totalMetric->metric;

            if ($responseCSV) {
                $items[] = $this->getItemForCSV($contextId, $contextViews);
            } else {
                $items[] = $this->getItemForJSON($illuminateRequest, $contextId, $contextViews);
            }
        }

        $itemsMax = $statsService->getCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getContextReportColumnNames();
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }

        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Get a monthly or daily timeline of total views for a set of contexts
     *
     * @hook API::stats::contexts::timeline::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyTimeline(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $illuminateRequest->query());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            'contextIds',
        ]);

        Hook::call('API::stats::contexts::timeline::params', [&$allowedParams, $illuminateRequest]);

        if (!$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
            return response()->json([
                'error' => __('api.stats.400.wrongTimelineInterval'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json([
                'error' => $result,
            ], Response::HTTP_BAD_REQUEST);
        }

        $statsService = app()->get('contextStats'); /** @var PKPStatsContextService $statsService */

        // Identify contexts which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedContextIds = empty($allowedParams['contextIds']) ? [] : $allowedParams['contextIds'];
            $allowedParams['contextIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedContextIds);

            if (empty($allowedParams['contextIds'])) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = $statsService->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                if ($responseCSV) {
                    $csvColumnNames = $statsService->getTimelineReportColumnNames();
                    return response()->withFile($emptyTimeline, $csvColumnNames, 0);
                }
                return response()->json($emptyTimeline, Response::HTTP_OK);
            }
        }

        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $statsService->getTimelineReportColumnNames();
            return response()->withFile($data, $csvColumnNames, count($data));
        }

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single context's usage statistics
     *
     * @hook API::stats::context::params [[&$allowedParams, $illuminateRequest]]
     */
    public function get(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $request = $this->getRequest();
        $contextService = app()->get('context'); /** @var ContextService $contextService */

        $context = $contextService->get((int) $illuminateRequest->route('contextId', null));
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }
        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch')
            ], Response::HTTP_FORBIDDEN);
        }

        $allowedParams = $this->_processAllowedParams($illuminateRequest->query(), [
            'dateStart',
            'dateEnd',
        ]);

        Hook::call('API::stats::context::params', [&$allowedParams, $illuminateRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json([
                'error' => $result
            ], Response::HTTP_BAD_REQUEST);
        }

        $dateStart = array_key_exists('dateStart', $allowedParams) ? $allowedParams['dateStart'] : null;
        $dateEnd = array_key_exists('dateEnd', $allowedParams) ? $allowedParams['dateEnd'] : null;

        $statsService = app()->get('contextStats');
        $contextViews = $statsService->getTotal($context->getId(), $dateStart, $dateEnd);

        // Get basic context details for display
        $propertyArgs = [
            'request' => $request,
            'apiRequest' => $illuminateRequest,
        ];
        $contextProps = $contextService->getSummaryProperties($context, $propertyArgs);
        if ($responseCSV) {
            $csvColumnNames = $this->_getContextReportColumnNames();
            $items = [$this->getItemForCSV($context->getId(), $contextViews)];
            return response()->withFile($items, $csvColumnNames, 1);
        }

        return response()->json([
            'total' => $contextViews,
            'context' => $contextProps
        ], Response::HTTP_OK);
    }

    /**
     * Get a monthly or daily timeline of total views for a context
     *
     * @hook API::stats::context::timeline::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getTimeline(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $request = $this->getRequest();
        $contextService = app()->get('context'); /** @var ContextService $contextService */

        $context = $contextService->get((int) $illuminateRequest->route('contextId'));
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }
        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $illuminateRequest->query());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
        ]);

        $allowedParams['contextIds'] = [$context->getId()];

        Hook::call('API::stats::context::timeline::params', [&$allowedParams, $illuminateRequest]);

        if (!$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
            return response()->json([
                'error' => __('api.stats.400.wrongTimelineInterval'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json([
                'error' => $result,
            ], Response::HTTP_BAD_REQUEST);
        }

        $statsService = app()->get('contextStats'); /** @var PKPStatsContextService $statsService */
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        if ($responseCSV) {
            $csvColumnNames = app()->get('contextStats')->getTimelineReportColumnNames();
            return response()->withFile($data, $csvColumnNames, count($data));
        }

        return response()->withJson($data, Response::HTTP_OK);
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
        $searchPhraseContextIds = app()->get('context')->getIds(['searchPhrase' => $searchPhrase]);
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
        $contexts = app()->get('context')->getManySummary([]);
        // @todo: Avoid retrieving all contexts just to grab one item
        $context = array_filter($contexts, function ($context) use ($contextId) {
            return $context->id == $contextId;
        });
        $title = current($context)->name;
        return [
            $contextId,
            $title,
            $contextViews
        ];
    }

    /**
     * Get JSON data with context index page metrics
     */
    protected function getItemForJSON(Request $illuminateRequest, int $contextId, int $contextViews): array
    {
        // Get basic context details for display
        $propertyArgs = [
            'request' => $this->getRequest(),
            'apiRequest' => $illuminateRequest,
        ];
        $context = app()->get('context')->get($contextId);
        $contextProps = app()->get('context')->getSummaryProperties($context, $propertyArgs);
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
