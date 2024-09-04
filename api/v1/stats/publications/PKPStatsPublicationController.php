<?php

/**
 * @file api/v1/stats/publications/PKPStatsPublicationController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationController
 *
 * @ingroup api_v1_stats
 *
 * @brief Controller class to handle API requests for submission statistics.
 *
 */

namespace PKP\API\v1\stats\publications;

use APP\core\Application;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Sokil\IsoCodes\IsoCodesFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class PKPStatsPublicationController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'stats/publications';
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
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('stats.publication.getMany');

        Route::get('timeline', $this->getManyTimeline(...))
            ->name('stats.publication.getManyTimeline');

        Route::get('{submissionId}', $this->get(...))
            ->name('stats.publication.getSubmission')
            ->whereNumber('submissionId');

        Route::get('{submissionId}/timeline', $this->getTimeline(...))
            ->name('stats.publication.getTimeline')
            ->whereNumber('submissionId');

        Route::get('files', $this->getManyFiles(...))
            ->name('stats.publication.getFiles');

        Route::get('countries', $this->getManyCountries(...))
            ->name('stats.publication.getCountries');

        Route::get('regions', $this->getManyRegions(...))
            ->name('stats.publication.getRegions');

        Route::get('cities', $this->getManyCities(...))
            ->name('stats.publication.getCities');
    }

    /** The name of the section ids query param for this application */
    abstract public function getSectionIdsQueryParam();

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */

        if (in_array(static::getRouteActionName($illuminateRequest), ['get', 'getTimeline'])) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * A helper method to filter and sanitize the application specific request params
     */
    protected function _processAppSpecificAllowedParams(string $requestParam, mixed $value, array &$returnParams): void
    {
    }

    /**
     * Get allowed parameters for getMany methods:
     * getMany(), getManyFiles(), getManyCountries(), getManyRegions(), getManyCities
     */
    protected function getManyAllowedParams(): array
    {
        $allowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            'submissionIds',
        ];
        $allowedParams[] = $this->getSectionIdsQueryParam();
        return $allowedParams;
    }

    /**
     * Get allowed parameters for getManyTimeline method
     */
    protected function getManyTimelineAllowedParams(): array
    {
        $allowedParams = [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            'submissionIds',
            'type'
        ];
        $allowedParams[] = $this->getSectionIdsQueryParam();
        return $allowedParams;
    }

    /**
     * Get usage stats for a set of publications
     *
     * Returns total views by abstract, all galleys, pdf galleys,
     * html galleys, and other galleys.
     *
     * @hook API::stats::publications::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = $this->getManyAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::params', [&$allowedParams, $illuminateRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getSubmissionReportColumnNames();
                    return response()->withFile([], $csvColumnNames, 0);
                }

                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }

            return response()->json([
                'error' => $e->getMessage(),
            ], $e->getCode());
        }

        $statsService = app()->get('publicationStats'); /** @var \PKP\services\PKPStatsPublicationService $statsService */

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
                $items[] = $this->getItemForCSV($submissionId, $metricsByType['abstract'], $metricsByType['pdf'], $metricsByType['html'], $metricsByType['other']);
            } else {
                $items[] = $this->getItemForJSON($submissionId, $metricsByType['abstract'], $metricsByType['pdf'], $metricsByType['html'], $metricsByType['other']);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getSubmissionReportColumnNames();
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }
        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Get the total abstract or files views for a set of submissions
     * in a timeline broken down by month or day
     *
     * @hook API::stats::publications::timeline::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyTimeline(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'timelineInterval' => StatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];
        $initAllowedParams = $this->getManyTimelineAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::timeline::params', [&$allowedParams, $illuminateRequest]);

        $statsService = app()->get('publicationStats'); /** @var \PKP\services\PKPStatsPublicationService $statsService */

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $dateStart = empty($allowedParams['dateStart']) ? StatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = $statsService->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);

                if ($responseCSV) {
                    $csvColumnNames = $statsService->getTimelineReportColumnNames();
                    return response()->withFile($emptyTimeline, $csvColumnNames, 0);
                }

                return response()->json($emptyTimeline, Response::HTTP_OK);
            }

            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION];
        if (array_key_exists('type', $allowedParams) && $allowedParams['type'] == 'files') {
            $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION_FILE];
        };
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $statsService->getTimelineReportColumnNames();
            return response()->withFile($data, $csvColumnNames, count($data));
        }
        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single submission's usage statistics
     *
     * @hook API::stats::publication::params [[&$allowedParams, $illuminateRequest]]
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $allowedParams = $this->_processAllowedParams($illuminateRequest->query(), [
            'dateStart',
            'dateEnd',
        ]);

        Hook::call('API::stats::publication::params', [&$allowedParams, $illuminateRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json(['error' => $result], Response::HTTP_BAD_REQUEST);
        }

        $statsService = app()->get('publicationStats'); /** @var \PKP\services\PKPStatsPublicationService $statsService */

        // get abstract, pdf, html and other views for the submission
        $dateStart = array_key_exists('dateStart', $allowedParams) ? $allowedParams['dateStart'] : null;
        $dateEnd = array_key_exists('dateEnd', $allowedParams) ? $allowedParams['dateEnd'] : null;
        $metricsByType = $statsService->getTotalsByType($submission->getId(), $request->getContext()->getId(), $dateStart, $dateEnd);

        $galleyViews = $metricsByType['pdf'] + $metricsByType['html'] + $metricsByType['other'];
        return response()->json([
            'abstractViews' => $metricsByType['abstract'],
            'galleyViews' => $galleyViews,
            'pdfViews' => $metricsByType['pdf'],
            'htmlViews' => $metricsByType['html'],
            'otherViews' => $metricsByType['other'],
            'publication' => Repo::submission()->getSchemaMap()->mapToStats($submission),
        ], Response::HTTP_OK);
    }

    /**
     * Get the total abstract of files views for a submission broken down by
     * month or day
     *
     * @hook API::stats::publication::timeline::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getTimeline(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $defaultParams = [
            'timelineInterval' => StatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $illuminateRequest->query());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'type'
        ]);

        Hook::call('API::stats::publication::timeline::params', [&$allowedParams, $illuminateRequest]);

        $allowedParams['contextIds'] = [$request->getContext()->getId()];
        $allowedParams['submissionIds'] = [$submission->getId()];
        $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION];
        if (array_key_exists('type', $allowedParams) && $allowedParams['type'] == 'files') {
            $allowedParams['assocTypes'] = [Application::ASSOC_TYPE_SUBMISSION_FILE];
        };

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return response()->json(['error' => $result], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($allowedParams['timelineInterval'], [StatisticsHelper::STATISTICS_DIMENSION_DAY, StatisticsHelper::STATISTICS_DIMENSION_MONTH])) {
            return response()->json([
                'error' => __('api.stats.400.invalidTimelineInterval')
            ], Response::HTTP_BAD_REQUEST);
        }

        $statsService = app()->get('publicationStats'); /** @var \PKP\services\PKPStatsPublicationService $statsService */
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get total usage stats for a set of submission files.
     *
     * @hook API::stats::publications::files::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyFiles(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = $this->getManyAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::files::params', [&$allowedParams, $illuminateRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getFileReportColumnNames();
                    return response()->withFile([], $csvColumnNames, 0);
                }

                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }

            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        $statsService = app()->get('publicationStats'); /** @var \PKP\services\PKPStatsPublicationService $statsService */
        $filesMetrics = $statsService->getFilesTotals($allowedParams);

        $items = $submissionTitles = [];
        foreach ($filesMetrics as $fileMetric) {
            $submissionId = $fileMetric->submission_id;
            $submissionFileId = $fileMetric->submission_file_id;
            $downloads = $fileMetric->metric;
            $type = $fileMetric->assoc_type;

            if (!isset($submissionTitles[$submissionId])) {
                $submission = Repo::submission()->get($submissionId);
                $submissionTitles[$submissionId] = $submission->getCurrentPublication()->getLocalizedTitle();
            }

            if ($responseCSV) {
                $items[] = $this->getFilesCSVItem($submissionFileId, $downloads, $type, $submissionId, $submissionTitles[$submissionId]);
            } else {
                $items[] = $this->getFilesJSONItem($submissionFileId, $downloads, $type, $submissionId, $submissionTitles[$submissionId]);
            }
        }

        // Get the total count of submissions files
        $itemsMax = $statsService->getFilesCount($allowedParams);
        if ($responseCSV) {
            $csvColumnNames = $this->_getFileReportColumnNames();
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }

        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Get usage stats for a set of countries
     *
     * Returns total count of views, downloads, unique views and unique downloads in a country
     *
     * @hook API::stats::publications::countries::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyCountries(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = $this->getManyAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::countries::params', [&$allowedParams, $illuminateRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
                    return response()->withFile([], $csvColumnNames, 0);
                }

                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }

            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        $statsService = app()->get('geoStats'); /** @var \PKP\services\PKPStatsGeoService $statsService */
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
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }
        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Get usage stats for set of regions
     *
     * Returns total count of views, downloads, unique views and unique downloads in a region
     *
     * @hook API::stats::publications::regions::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyRegions(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = $this->getManyAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::regions::params', [&$allowedParams, $illuminateRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_REGION);
                    return response()->withFile([], $csvColumnNames, 0);
                }

                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }

            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        $statsService = app()->get('geoStats'); /** @var \PKP\services\PKPStatsGeoService $statsService */
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
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }

        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Get usage stats for set of cities
     *
     * Returns total count of views, downloads, unique views and unique downloads in a city
     *
     * @hook API::stats::publications::cities::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getManyCities(Request $illuminateRequest): StreamedResponse|JsonResponse
    {
        $responseCSV = str_contains($illuminateRequest->headers->get('Accept'), 'text/csv') ? true : false;

        $defaultParams = [
            'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = $this->getManyAllowedParams();
        $requestParams = array_merge($defaultParams, $illuminateRequest->query());
        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        Hook::call('API::stats::publications::cities::params', [&$allowedParams, $illuminateRequest]);

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->validateParams($allowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                if ($responseCSV) {
                    $csvColumnNames = $this->_getGeoReportColumnNames(StatisticsHelper::STATISTICS_DIMENSION_CITY);
                    return response()->withFile([], $csvColumnNames, 0);
                }

                return response()->json([
                    'items' => [],
                    'itemsMax' => 0,
                ], Response::HTTP_OK);
            }

            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        $statsService = app()->get('geoStats'); /** @var \PKP\services\PKPStatsGeoService $statsService */
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
            return response()->withFile($items, $csvColumnNames, $itemsMax);
        }

        return response()->json([
            'items' => $items,
            'itemsMax' => $itemsMax,
        ], Response::HTTP_OK);
    }

    /**
     * Validate, filter, sanitize the params
     *
     * @throws \Exception
     */
    protected function validateParams(array $allowedParams): array
    {
        $request = $this->getRequest();

        $allowedParams['contextIds'] = [$request->getContext()->getId()];

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            throw new \Exception($result, 400);
        }

        if (array_key_exists('orderDirection', $allowedParams) && !in_array($allowedParams['orderDirection'], [StatisticsHelper::STATISTICS_ORDER_ASC, StatisticsHelper::STATISTICS_ORDER_DESC])) {
            throw new \Exception('api.stats.400.invalidOrderDirection', 400);
        }

        if (array_key_exists('timelineInterval', $allowedParams) && !$this->isValidTimelineInterval($allowedParams['timelineInterval'])) {
            throw new \Exception('api.stats.400.invalidTimelineInterval', 400);
        }

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                throw new \Exception('', 200);
            }
            unset($allowedParams['searchPhrase']);
        }
        return $allowedParams;
    }

    /**
     * Filter and sanitize the request param
     */
    protected function _processParam(string $requestParam, mixed $value): array
    {
        $returnParams = [];
        $sectionIdsQueryParam = $this->getSectionIdsQueryParam();
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

            case $sectionIdsQueryParam:
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
        return $returnParams;
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
            $returnParams += $this->_processParam($requestParam, $value);
        }
        // Get the context's earliest date of publication if no start date is set
        if (in_array('dateStart', $allowedParams) && !isset($returnParams['dateStart'])) {
            $dateRange = Repo::publication()->getDateBoundaries(
                Repo::publication()
                    ->getCollector()
                    ->filterByContextIds([$this->getRequest()->getContext()->getId()])
            );
            $returnParams['dateStart'] = $dateRange->min_date_published;
        }
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
        $searchPhraseSubmissionIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->searchPhrase($searchPhrase)
            ->getIds();

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
            __('common.other')
        ];
    }

    /**
     * Get column names for the file CSV report
     */
    protected function _getFileReportColumnNames(): array
    {
        return [
            __('common.publication') . ' ' . __('common.id'),
            __('submission.title'),
            __('common.file') . ' ' . __('common.id'),
            __('common.fileName'),
            __('common.type'),
            __('stats.fileViews'),
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
                __('stats.region'),
                __('common.country')
            ];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $scaleColumns = [__('stats.region'), __('common.country')];
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
    protected function getItemForCSV(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews): array
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
            $otherViews
        ];
    }

    /**
     * Get the JSON data with submission metrics
     */
    protected function getItemForJSON(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews): array
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
            'publication' => $submissionProps,
        ];
    }

    /**
     * Get CSV row with file metrics
     */
    protected function getFilesCSVItem(int $submissionFileId, int $downloads, int $assocType, int $submissionId, string $submissionTitle): array
    {
        // Get submission file title for display
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        $title = $submissionFile->getLocalizedData('name');
        $type = $assocType == Application::ASSOC_TYPE_SUBMISSION_FILE ? __('stats.file.type.primaryFile') : __('stats.file.type.suppFile');
        return [
            $submissionId,
            $submissionTitle,
            $submissionFileId,
            $title,
            $type,
            $downloads
        ];
    }

    /**
     * Get JSON data with file metrics
     */
    protected function getFilesJSONItem(int $submissionFileId, int $downloads, int $assocType, int $submissionId, string $submissionTitle): array
    {
        // Get submission file title for display
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        $title = $submissionFile->getLocalizedData('name');
        $type = $assocType == Application::ASSOC_TYPE_SUBMISSION_FILE ? __('stats.file.type.primaryFile') : __('stats.file.type.suppFile');
        return [
            'submissionId' => $submissionId,
            'submissionTitle' => $submissionTitle,
            'submissionFileId' => $submissionFileId,
            'fileName' => $title,
            'fileType' => $type,
            'downloads' => $downloads
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
