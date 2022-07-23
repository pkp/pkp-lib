<?php

/**
 * @file api/v1/stats/sushi/PKPStatsSushiHandler.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for COUNTER R5 SUSHI statistics.
 *
 */

use APP\facades\Repo;
use APP\sushi\PR;
use APP\sushi\PR_P1;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\sushi\CounterR5Report;
use PKP\sushi\SushiException;
use Slim\Http\Request as SlimHttpRequest;

class PKPStatsSushiHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'stats/sushi';
        $this->_endpoints = [
            'GET' => $this->getGETDefinitions()
        ];
        parent::__construct();
    }

    /**
     * Get this API's endpoints definitions
     */
    protected function getGETDefinitions(): array
    {
        $roles = [];
        return [
            [
                'pattern' => $this->getEndpointPattern() . '/status',
                'handler' => [$this, 'getStatus'],
                'roles' => $roles
            ],
            [
                'pattern' => $this->getEndpointPattern() . '/members',
                'handler' => [$this, 'getMembers'],
                'roles' => $roles
            ],
            [
                'pattern' => $this->getEndpointPattern() . '/reports',
                'handler' => [$this, 'getReports'],
                'roles' => $roles
            ],
            [
                'pattern' => $this->getEndpointPattern() . '/reports/pr',
                'handler' => [$this, 'getReportsPR'],
                'roles' => $roles
            ],
            [
                'pattern' => $this->getEndpointPattern() . '/reports/pr_p1',
                'handler' => [$this, 'getReportsPR1'],
                'roles' => $roles
            ],
        ];
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the current status of the reporting service
     */
    public function getStatus(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        // use only the name in the context primary locale to be consistent
        $contextName = $context->getName($context->getPrimaryLocale());
        return $response->withJson([
            'Description' => __('sushi.status.description', ['contextName' => $contextName]),
            'Service_Active' => true,
        ], 200);
    }

    /**
     * Get the list of consortium members related to a Customer_ID
     */
    public function getMembers(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $site = $request->getSite();
        $params = $slimRequest->getQueryParams();
        if (!isset($params['customer_id'])) {
            // error: missing required customer_id
            return $response->withJson([
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.missing', ['params' => 'customer_id'])
            ], 400);
        }
        $platformId = $context->getPath();
        if ($site->getData('isSiteSushiPlatform')) {
            $platformId = $site->getData('sushiPlatformID');
        }
        $institutionName = $institutionId = null;
        $customerId = $params['customer_id'];
        if (is_numeric($customerId)) {
            $customerId = (int) $customerId;
            if ($customerId == 0) {
                $institutionName = 'The World';
            } else {
                $institution = Repo::institution()->get($customerId);
                if (isset($institution) && $institution->getContextId() == $context->getId()) {
                    $institutionId = [];
                    $institutionName = $institution->getLocalizedName();
                    if (!empty($institution->getROR())) {
                        $institutionId[] = ['Type' => 'ROR', 'Value' => $institution->getROR()];
                    }
                    $institutionId[] = ['Type' => 'Proprietary', 'Value' => $platformId . ':' . $customerId];
                }
            }
        }
        if (!isset($institutionName)) {
            // error: invalid customer_id
            return $response->withJson([
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.invalid', ['params' => 'customer_id'])
            ], 400);
        }
        $item = [
            'Customer_ID' => $customerId,
            'Name' => $institutionName,
        ];
        if (isset($institutionId)) {
            $item['Insitution_ID'] = $institutionId;
        }
        return $response->withJson([$item], 200);
    }

    /**
     * Get list of reports supported by the API
     */
    public function getReports(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $items = $this->getReportList();
        return $response->withJson($items, 200);
    }

    /**
     * Get the application specific list of reports supported by the API
     */
    protected function getReportList(): array
    {
        return [
            [
                'Report_Name' => 'Platform Master Report',
                'Report_ID' => 'PR',
                'Release' => '5',
                'Report_Description' => __('sushi.reports.pr.description'),
                'Path' => 'reports/pr'
            ],
            [
                'Report_Name' => 'Platform Usage',
                'Report_ID' => 'PR_P1',
                'Release' => '5',
                'Report_Description' => __('sushi.reports.pr_p1.description'),
                'Path' => 'reports/pr_p1'
            ],
        ];
    }

    /**
     * COUNTER 'Platform Usage' [PR_P1].
     * A customizable report summarizing activity across the Platform (journal, press, or server).
     */
    public function getReportsPR(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        return $this->getReportResponse(new PR(), $slimRequest, $response, $args);
    }

    /**
     * COUNTER 'Platform Master Report' [PR].
     * This is a Standard View of the Platform Master Report that presents usage for the overall Platform broken down by Metric_Type
     */
    public function getReportsPR1(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        return $this->getReportResponse(new PR_P1(), $slimRequest, $response, $args);
    }

    /**
     * Get the requested report
     */
    protected function getReportResponse(CounterR5Report $report, SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $params = $slimRequest->getQueryParams();

        try {
            $report->processReportParams($this->getRequest(), $params);
        } catch (SushiException $e) {
            return $response->withJson($e->getResponseData(), $e->getHttpStatusCode());
        }

        $reportHeader = $report->getReportHeader();
        $reportItems = $report->getReportItems();

        return $response->withJson([
            'Report_Header' => $reportHeader,
            'Report_Items' => $reportItems,
        ], 200);
    }
}
