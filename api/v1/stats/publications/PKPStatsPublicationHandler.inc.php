<?php

/**
 * @file api/v1/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for publication statistics.
 *
 */

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\statistics\PKPStatisticsHelper;

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
                    'pattern' => $this->getEndpointPattern() . '/abstract',
                    'handler' => [$this, 'getManyAbstract'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/galley',
                    'handler' => [$this, 'getManyGalley'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/abstract',
                    'handler' => [$this, 'getAbstract'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/galley',
                    'handler' => [$this, 'getGalley'],
                    'roles' => $roles
                ],
            ],
        ];
        parent::__construct();
    }

    //
    // Implement methods from PKPHandler
    //
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
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

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
            $this->sectionIdsQueryParam,
            'submissionIds',
        ]);

        $allowedParams['contextIds'] = $request->getContext()->getId();

        HookRegistry::call('API::stats::publications::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        if (!in_array($allowedParams['orderDirection'], [PKPStatisticsHelper::STATISTICS_ORDER_ASC, PKPStatisticsHelper::STATISTICS_ORDER_DESC])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.invalidOrderDirection');
        }

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                return $response->withJson([
                    'items' => [],
                    'itemsMax' => 0,
                ], 200);
            }
        }

        // Get a list of top publications by total abstract and file views
        $statsService = Services::get('stats');
        $totals = $statsService->getOrderedObjects(PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, $allowedParams['orderDirection'], array_merge($allowedParams, [
            'assocTypes' => [ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_SUBMISSION_FILE]
        ]));

        // Get the stats for each publication
        $items = [];
        foreach ($totals as $total) {
            if (empty($total['id'])) {
                continue;
            }

            $galleyRecords = $statsService->getRecords(array_merge($allowedParams, [
                'assocTypes' => ASSOC_TYPE_SUBMISSION_FILE,
                'submissionIds' => [$total['id']],
            ]));

            // Get the galley totals for each file type (pdf, html, other)
            $galleyViews = array_reduce($galleyRecords, [$statsService, 'sumMetric'], 0);
            $pdfViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordPdf']), [$statsService, 'sumMetric'], 0);
            $htmlViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordHtml']), [$statsService, 'sumMetric'], 0);
            $otherViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordOther']), [$statsService, 'sumMetric'], 0);

            // Get the abstract records
            $abstractRecords = $statsService->getRecords(array_merge($allowedParams, [
                'assocTypes' => ASSOC_TYPE_SUBMISSION,
                'submissionIds' => [$total['id']],
            ]));
            $abstractViews = array_reduce($abstractRecords, [$statsService, 'sumMetric'], 0);

            // Get basic submission details for display
            // Stats may exist for deleted submissions
            $submissionProps = ['id' => $total['id']];
            $submission = Repo::submission()->get($total['id']);
            if ($submission) {
                $submissionProps = Repo::submission()->getSchemaMap()->mapToStats($submission);
            }

            $items[] = [
                'abstractViews' => $abstractViews,
                'galleyViews' => $galleyViews,
                'pdfViews' => $pdfViews,
                'htmlViews' => $htmlViews,
                'otherViews' => $otherViews,
                'publication' => $submissionProps,
            ];
        }

        // Get a count of all submission ids that have stats matching this request
        $statsQB = new \PKP\services\queryBuilders\PKPStatsQueryBuilder();
        $statsQB
            ->filterByContexts(\Application::get()->getRequest()->getContext()->getId())
            ->before($allowedParams['dateEnd'] ?? PKPStatisticsHelper::STATISTICS_YESTERDAY)
            ->after($allowedParams['dateStart'] ?? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE);
        if (isset($allowedParams[$this->sectionIdsQueryParam])) {
            $statsQB->filterBySections($allowedParams[$this->sectionIdsQueryParam]);
        }
        if (isset($allowedParams['submissionIds'])) {
            $statsQB->filterBySubmissions($allowedParams['submissionIds']);
        }
        $statsQO = $statsQB->getSubmissionIds();

        $metricsDao = \DAORegistry::getDAO('MetricsDAO'); /** @var MetricsDAO */
        return $response->withJson([
            'items' => $items,
            'itemsMax' => $metricsDao->countRecords($statsQO->toSql(), $statsQO->getBindings()),
        ], 200);
    }

    /**
     * Get the total abstract views for a set of publications
     * in a timeline broken down month or day
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getManyAbstract($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ]);

        HookRegistry::call('API::stats::publications::abstract::params', [&$allowedParams, $slimRequest]);

        if (!in_array($allowedParams['timelineInterval'], [PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimelineInterval');
        }

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['assocTypes'] = ASSOC_TYPE_SUBMISSION;

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = \Services::get('stats')->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            }
        }

        $data = Services::get('stats')->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get the total galley views for a set of publications
     * in a timeline broken down month or day
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getManyGalley($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ]);

        \HookRegistry::call('API::stats::publications::galley::params', [&$allowedParams, $slimRequest]);

        if (!in_array($allowedParams['timelineInterval'], [PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH])) {
            return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimelineInterval');
        }

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['assocTypes'] = ASSOC_TYPE_SUBMISSION_FILE;

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = \Services::get('stats')->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            }
        }

        $data = \Services::get('stats')->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get a single publication's usage statistics
     *
     * @param object $slimRequest Request Slim request
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function get($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        $allowedParams = $this->_processAllowedParams($slimRequest->getQueryParams(), [
            'dateStart',
            'dateEnd',
        ]);

        \HookRegistry::call('API::stats::publication::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $allowedParams['submissionIds'] = [$submission->getId()];
        $allowedParams['contextIds'] = $request->getContext()->getId();

        $statsService = Services::get('stats');

        $abstractRecords = $statsService->getRecords(array_merge($allowedParams, [
            'assocTypes' => [ASSOC_TYPE_SUBMISSION],
        ]));
        $abstractViews = array_reduce($abstractRecords, [$statsService, 'sumMetric'], 0);

        // Get the galley totals for each file type (pdf, html, other)
        $galleyRecords = $statsService->getRecords(array_merge($allowedParams, [
            'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
        ]));
        $galleyViews = array_reduce($galleyRecords, [$statsService, 'sumMetric'], 0);
        $pdfViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordPdf']), [$statsService, 'sumMetric'], 0);
        $htmlViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordHtml']), [$statsService, 'sumMetric'], 0);
        $otherViews = array_reduce(array_filter($galleyRecords, [$statsService, 'filterRecordOther']), [$statsService, 'sumMetric'], 0);

        $submission = Repo::submission()->get($total['id']);

        return $response->withJson([
            'abstractViews' => $abstractViews,
            'galleyViews' => $galleyViews,
            'pdfViews' => $pdfViews,
            'htmlViews' => $htmlViews,
            'otherViews' => $otherViews,
            'publication' => Repo::submission()->getSchemaMap()->mapToStats($submission),
        ], 200);
    }

    /**
     * Get the total abstract views for a set of publications broken down by
     * month or day
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getAbstract($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
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

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['submissionIds'] = $submission->getId();
        $allowedParams['assocTypes'] = ASSOC_TYPE_SUBMISSION;
        $allowedParams['assocIds'] = $submission->getId();

        \HookRegistry::call('API::stats::publication::abstract::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = \Services::get('stats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get the total galley views for a publication broken down by
     * month or day
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getGalley($slimRequest, $response, $args)
    {
        $request = $this->getRequest();


        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
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

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['submissionIds'] = $submission->getId();
        $allowedParams['assocTypes'] = ASSOC_TYPE_SUBMISSION_FILE;

        \HookRegistry::call('API::stats::publication::galley::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = \Services::get('stats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * A helper method to filter and sanitize the request params
     *
     * Only allows the specified params through and enforces variable
     * type where needed.
     *
     * @param array $requestParams
     * @param array $allowedParams
     *
     * @return array
     */
    protected function _processAllowedParams($requestParams, $allowedParams)
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

        // Get the earliest date of publication if no start date set
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
     * request, then we only return ids that match both conditions.
     *
     * @param string $searchPhrase
     * @param array $submissionIds List of allowed submission Ids
     *
     * @return array submission ids
     */
    protected function _processSearchPhrase($searchPhrase, $submissionIds = [])
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
}
