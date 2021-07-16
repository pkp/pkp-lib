<?php

/**
 * @file api/v1/stats/publications/PKPStatsPublicationHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for submission statistics.
 *
 */

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use Slim\Http\Request as SlimHttpRequest;
use Sokil\IsoCodes\IsoCodesFactory;

abstract class PKPStatsPublicationHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'stats/publications';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/timeline',
                    'handler' => [$this, 'getManyTimeline'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/timeline',
                    'handler' => [$this, 'getTimeline'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/files',
                    'handler' => [$this, 'getManyFiles'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/countries',
                    'handler' => [$this, 'getManyCountries'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/regions',
                    'handler' => [$this, 'getManyRegions'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/cities',
                    'handler' => [$this, 'getManyCities'],
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

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        if (!is_null($slimRequest) && ($route = $slimRequest->getAttribute('route'))) {
            $routeName = $route->getName();
        }
        if (in_array($routeName, ['get', 'getAbstract', 'getGalley'])) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get usage stats for a set of publications
     *
     * Returns total views by abstract, all galleys, pdf galleys,
     * html galleys, and other galleys.
     */
    public function getMany(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::params', [&$allowedParams, $slimRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getSubmissionReportColumnNames();
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $statsService = Services::get('publicationStats');
        // Get a list of top submissions by total views
        $totalMetrics = $statsService->getTotals($allowedParams);

        // Get the stats for each submission
        $items = [];
        foreach ($totalMetrics as $totalMetric) {
            $submissionId = $totalMetric->submission_id;

            // get abstract, pdf, html and other views for the submission
            $dateStart = array_key_exists('dateStart', $allowedParams) ? $allowedParams['dateStart'] : null;
            $dateEnd = array_key_exists('dateEnd', $allowedParams) ? $allowedParams['dateEnd'] : null;
            $metricsByType = $statsService->getTotalsByType($submissionId, $this->getRequest()->getContext()->getId(), $dateStart, $dateEnd);

            if ($responseCSV) {
                $items[] = $this->getItemForCSV($submissionId, $metricsByType['abstract'], $metricsByType['pdf'], $metricsByType['html'], $metricsByType['other'], $metricsByType['suppFileViews']);
            } else {
                $items[] = $this->getItemForJSON($submissionId, $metricsByType['abstract'], $metricsByType['pdf'], $metricsByType['html'], $metricsByType['other'], $metricsByType['suppFileViews']);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getSubmissionReportColumnNames();
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Get the total abstract or files views for a set of submissions
     * in a timeline broken down by month or day
     */
    public function getManyTimeline(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $defaultParams = [
            'timelineInterval' => StatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
            'type'
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::timeline::params', [&$allowedParams, $slimRequest]);

        $statsService = Services::get('publicationStats');
        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $dateStart = empty($allowedParams['dateStart']) ? StatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = $statsService->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION];
        if (array_key_exists('type', $allowedParams) && $allowedParams['type'] == 'files') {
            $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER];
        };
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);
        return $response->withJson($data, 200);
    }

    /**
     * Get a single submission's usage statistics
     */
    public function get(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $allowedParams = $this->_processAllowedParams($slimRequest->getQueryParams(), [
            'dateStart',
            'dateEnd',
        ]);

        HookRegistry::call('API::stats::publication::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = Services::get('publicationStats');
        // get abstract, pdf, html and other views for the submission
        $dateStart = array_key_exists('dateStart', $allowedParams) ? $allowedParams['dateStart'] : null;
        $dateEnd = array_key_exists('dateEnd', $allowedParams) ? $allowedParams['dateEnd'] : null;
        $metricsByType = $statsService->getTotalsByType($submission->getId(), $request->getContext()->getId(), $dateStart, $dateEnd);

        $galleyViews = $metricsByType['pdf'] + $$metricsByType['html'] + $metricsByType['other'];
        return $response->withJson([
            'abstractViews' => $metricsByType['abstract'],
            'galleyViews' => $galleyViews,
            'pdfViews' => $metricsByType['pdf'],
            'htmlViews' => $metricsByType['html'],
            'otherViews' => $metricsByType['other'],
            'suppFileViews' => $metricsByType['suppFileViews'],
            'publication' => Repo::submission()->getSchemaMap()->mapToStats($submission),
        ], 200);
    }

    /**
     * Get the total abstract of files views for a submission broken down by
     * month or day
     */
    public function getTimeline(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $defaultParams = [
            'timelineInterval' => StatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
        ]);

        HookRegistry::call('API::stats::publication::timeline::params', [&$allowedParams, $slimRequest]);

        $allowedParams['contextIds'] = [$request->getContext()->getId()];
        $allowedParams['submissionIds'] = [$submission->getId()];
        $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION];
        if (array_key_exists('type', $allowedParams) && $allowedParams['type'] == 'files') {
            $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER];
        };

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        if (!in_array($allowedParams['timelineInterval'], [StatisticsHelper::STATISTICS_DIMENSION_DAY, StatisticsHelper::STATISTICS_DIMENSION_MONTH])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.invalidTimelineInterval');
        }

        $statsService = Services::get('publicationStats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get total usage stats for a set of submission files.
     */
    public function getManyFiles(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::files::params', [&$allowedParams, $slimRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getFileReportColumnNames();
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $statsService = Services::get('publicationStats');
        $filesMetrics = $statsService->getFilesTotals($allowedParams);

        $items = $submissionTitles = [];
        foreach ($filesMetrics as $fileMetric) {
            $submissionId = $fileMetric->submission_id;
            $submissionFileId = $fileMetric->submission_file_id;
            $downloads = $fileMetric->metric;

            if (!isset($submissionTitles[$submissionId])) {
                $submission = Repo::submission()->get($submissionId);
                $submissionTitles[$submissionId] = $submission->getCurrentPublication()->getLocalizedTitle();
            }

            if ($responseCSV) {
                $items[] = $this->getFilesCSVItem($submissionFileId, $downloads, $submissionId, $submissionTitles[$submissionId]);
            } else {
                $items[] = $this->getFilesJSONItem($submissionFileId, $downloads, $submissionId, $submissionTitles[$submissionId]);
            }
        }

        // Get the total count of submissions files
        $itemsMax = $statsService->getFilesCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getFileReportColumnNames();
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Get usage stats for a set of countries
     *
     * Returns total count of views, downloads, unique views and unique downloads in a country
     */
    public function getManyCountries(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::countries::params', [&$allowedParams, $slimRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $statsService = Services::get('geoStats');
        // Get a list of top countries by total views
        $totals = $statsService->getTotals($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);

        // Get the stats for each country
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            $country = !empty($total->country) ? $isoCodes->getCountries()->getByAlpha2($total->country) : null;
            $countryName = $country ? $country->getLocalName() : $total->country;

            $metric = $total->metric;
            $metric_unique = $total->metric_unique;
            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($metric, $metric_unique, $countryName);
            } else {
                $items[] = $this->getGeoJSONItem($metric, $metric_unique, $countryName);
            }
        }

        // Get the total count of countries
        $itemsMax = $statsService->getCount($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
        if ($responseCSV) {
            $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Get usage stats for set of regions
     *
     * Returns total count of views, downloads, unique views and unique downloads in a region
     */
    public function getManyRegions(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::regions::params', [&$allowedParams, $slimRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_REGION);
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $statsService = Services::get('geoStats');
        // Get a list of top regions by total views
        $totals = $statsService->getTotals($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_REGION);

        // Get the stats for each region
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            $country = !empty($total->country) ? $isoCodes->getCountries()->getByAlpha2($total->country) : null;
            $countryName = $country ? $country->getLocalName() : __('stats.unknown');
            $regionName = !empty($total->region) ? $total->region : __('stats.unknown');
            if (!empty($total->country) && !empty($total->region)) {
                $regionCode = $total->country . '-' . $total->region;
                $region = $isoCodes->getSubdivisions()->getByCode($regionCode);
                $regionName = $region ? $region->getLocalName() : $regionCode;
            }

            $metric = $total->metric;
            $metric_unique = $total->metric_unique;
            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($metric, $metric_unique, $countryName, $regionName);
            } else {
                $items[] = $this->getGeoJSONItem($metric, $metric_unique, $countryName, $regionName);
            }
        }

        // Get the total count of regions
        $itemsMax = $statsService->getCount($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_REGION);
        if ($responseCSV) {
            $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_REGION);
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Get usage stats for set of cities
     *
     * Returns total count of views, downloads, unique views and unique downloads in a city
     */
    public function getManyCities(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];
        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        HookRegistry::call('API::stats::publications::cities::params', [&$allowedParams, $slimRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_CITY);
                    return $response->withCSV([], $csvColumnNames, 0);
                }
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

        $statsService = Services::get('geoStats');
        // Get a list of top cities by total views
        $totals = $statsService->getTotals($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_CITY);

        // Get the stats for each city
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            $country = !empty($total->country) ? $isoCodes->getCountries()->getByAlpha2($total->country) : null;
            $countryName = $country ? $country->getLocalName() : __('stats.unknown');
            $regionName = !empty($total->region) ? $total->region : __('stats.unknown');
            if (!empty($total->country) && !empty($total->region)) {
                $regionCode = $total->country . '-' . $total->region;
                $region = $isoCodes->getSubdivisions()->getByCode($regionCode);
                $regionName = $region ? $region->getLocalName() : $regionCode;
            }
            $cityName = !empty($total->city) ? $total->city : __('stats.unknown');

            $metric = $total->metric;
            $metric_unique = $total->metric_unique;
            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($metric, $metric_unique, $countryName, $regionName, $cityName);
            } else {
                $items[] = $this->getGeoJSONItem($metric, $metric_unique, $countryName, $regionName, $cityName);
            }
        }

        // Get the total count of cities
        $itemsMax = $statsService->getCount($allowedParams, StatisticsHelper::STATISTICS_DIMENSION_CITY);
        if ($responseCSV) {
            $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_CITY);
            return $response->withCSV($items, $csvColumnNames, $itemsMax);
        }
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], 200);
    }

    /**
     * Validate, filter, sanitize the params
     *
     * @throws Exception
     */
    protected function validateParams(array $allowedParams): array
    {
        $request = $this->getRequest();

        $allowedParams['contextIds'] = [$request->getContext()->getId()];

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            throw new Exception($result, 400);
        }

        if (array_key_exists('orderDirection', $allowedParams) && !in_array($allowedParams['orderDirection'], [StatisticsHelper::STATISTICS_ORDER_ASC, StatisticsHelper::STATISTICS_ORDER_DESC])) {
            throw new Exception('api.stats.400.invalidOrderDirection', 400);
        }

        if (array_key_exists('timelineInterval', $allowedParams) && !$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
            return new Exception('api.stats.400.invalidTimelineInterval', 400);
        }

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                throw new Exception('', 200);
            }
            unset($allowedParams['searchPhrase']);
        }
        return $allowedParams;
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
                case 'searchPhrase':
                case 'type':
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

                case $this->sectionIdsQueryParam:
                    if (is_string($value) && str_contains($value, ',')) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $returnParams['pkpSectionIds'] = array_map('intval', $value);
                    break;
                case 'submissionIds':
                    if (is_string($value) && str_contains($value, ',')) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $returnParams[$requestParam] = array_map('intval', $value);
                    break;
            }
        }
        /*
        // Get the context's earliest date of publication if no start date is set
        if (in_array('dateStart', $allowedParams) && !isset($returnParams['dateStart'])) {
            $dateRange = Repo::publication()->getDateBoundaries(
                Repo::publication()
                    ->getCollector()
                    ->filterByContextIds([$this->getRequest()->getContext()->getId()])
            );
            $returnParams['dateStart'] = $dateRange->min_date_published;
        }
        */
        return $returnParams;
    }

    /**
     * A helper method to get the submissionIds param when a searchPhase
     * param is also passed.
     *
     * If the searchPhrase and submissionIds params were both passed in the
     * request, then we only return IDs that match both conditions.
     */
    protected function _processSearchPhrase(string $searchPhrase, array $submissionIds = []): array
    {
        $searchPhraseSubmissionIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
                ->searchPhrase($searchPhrase)
        );

        if (!empty($submissionIds)) {
            $submissionIds = array_intersect($submissionIds, $searchPhraseSubmissionIds->toArray());
        } else {
            $submissionIds = $searchPhraseSubmissionIds->toArray();
        }

        return $submissionIds;
    }

    /**
     * Get column names for the submission CSV report
     */
    protected function _getSubmissionReportColumnNames(): array
    {
        return [
            __('common.id'),
            __('common.title'),
            __('stats.total'),
            __('submission.abstractViews'),
            __('stats.fileViews'),
            __('stats.pdf'),
            __('stats.html'),
            __('common.other'),
            __('stats.suppFileViews')
        ];
    }

    /**
     * Get column names for the file CSV report
     */
    protected function _getFileReportColumnNames(): array
    {
        return [
            __('common.id'),
            __('common.title'),
            __('stats.fileViews'),
            __('common.publication') . ' ' . __('common.id'),
            __('submission.title'),
        ];
    }

    /**
     * Get column names for the country, region and city CSV report
     */
    protected function _getGeoReportColumnNames(string $scale, bool $withPublication = false): array
    {
        $publicationColumns = [];
        if ($withPublication) {
            $publicationColumns = [
                __('common.id'),
                __('common.title')
            ];
        }
        $scaleColumns = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $scaleColumns = [
                __('stats.city'),
                __('stats.region')
            ];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $scaleColumns = [__('stats.region')];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $scaleColumns = [__('common.country'),];
        }
        return array_merge(
            $publicationColumns,
            $scaleColumns,
            [__('stats.total'), __('stats.unique')]
        );
    }

    /**
     * Get the CSV row with submission metrics
     */
    protected function getItemForCSV(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews, int $suppFileViews): array
    {
        $galleyViews = $pdfViews + $htmlViews + $otherViews;
        $totalViews = $abstractViews + $galleyViews;

        // Get submission title for display
        $submission = Repo::submission()->get($submissionId);
        $submissionTitle = $submission->getCurrentPublication()->getLocalizedTitle();

        return [
            $submissionId,
            $submissionTitle,
            $totalViews,
            $abstractViews,
            $galleyViews,
            $pdfViews,
            $htmlViews,
            $otherViews,
            $suppFileViews
        ];
    }

    /**
     * Get the JSON data with submission metrics
     */
    protected function getItemForJSON(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews, int $suppFileViews): array
    {
        $galleyViews = $pdfViews + $htmlViews + $otherViews;

        // Get basic submission details for display
        $submission = Repo::submission()->get($submissionId);
        $submissionProps = Repo::submission()->getSchemaMap()->mapToStats($submission);

        return [
            'abstractViews' => $abstractViews,
            'galleyViews' => $galleyViews,
            'pdfViews' => $pdfViews,
            'htmlViews' => $htmlViews,
            'otherViews' => $otherViews,
            'suppFileViews' => $suppFileViews,
            'publication' => $submissionProps,
        ];
    }

    /**
     * Get CSV row with file metrics
     */
    protected function getFilesCSVItem(int $submissionFileId, int $downloads, int $submissionId, string $submissionTitle): array
    {
        // Get submission file title for display
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        $title = $submissionFile->getLocalizedData('name');
        return [
            $submissionFileId,
            $title,
            $downloads,
            $submissionId,
            $submissionTitle
        ];
    }

    /**
     * Get JSON data with file metrics
     */
    protected function getFilesJSONItem(int $submissionFileId, int $downloads, int $submissionId, string $submissionTitle): array
    {
        // Get submission file title for display
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        $title = $submissionFile->getLocalizedData('name');
        return [
            'submissionFileId' => $submissionFileId,
            'fileName' => $title,
            'downloads' => $downloads,
            'submissionId' => $submissionId,
            'submissionTitle' => $submissionTitle
        ];
    }

    /**
     * Get CSV row with geographical (country, region, and/or city) metrics
     */
    protected function getGeoCSVItem(int $metric, int $metric_unique, string $country, ?string $region = null, ?string $city = null): array
    {
        $item = [];
        if (isset($city)) {
            $item[] = $city;
        }
        if (isset($region)) {
            $item[] = $region;
        }
        return array_merge($item, [$country, $metric, $metric_unique]);
    }

    /**
     * Get JSON data with geographical (country, region, and/or city) metrics
     */
    protected function getGeoJSONItem(int $metric, int $metric_unique, string $country, ?string $region = null, ?string $city = null): array
    {
        $item = [];
        if (isset($city)) {
            $item['city'] = $city;
        }
        if (isset($region)) {
            $item['region'] = $region;
        }
        return array_merge(
            $item,
            [
                'country' => $country,
                'total' => $metric,
                'unique' => $metric_unique
            ]
        );
    }

    /**
     * Check if the timeline interval is valid
     */
    protected function isValidTimelineInterval(string $interval): bool
    {
        return in_array($interval, [
            StatisticsHelper::STATISTICS_DIMENSION_DAY,
            StatisticsHelper::STATISTICS_DIMENSION_MONTH
        ]);
    }
}
