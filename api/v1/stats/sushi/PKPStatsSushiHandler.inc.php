<?php

/**
 * @file api/v1/stats/sushi/PKPStatsSushiHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for COUNTER R5 SUSHI statistics.
 *
 */

use APP\core\Services;
use APP\facades\Repo;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
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
        return true;
    }

    /**
     * Get the current status of the reporting service
     */
    public function getStatus(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        if (!$context) {
            throw new \Exception('api.404.resourceNotFound', 404);
        }
        // use only the name in the contxt primary locale to be consistent
        $contextName = $context->getName($context->getPrimaryLocale());
        ;
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
        if (!$context) {
            throw new \Exception('api.404.resourceNotFound', 404);
        }

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
        $institutionName = $institutionId = null;
        $customerId = $params['customer_id'];
        if (is_numeric($customerId)) {
            if ($customerId == 0) {
                $institutionName = 'The World';
            } else {
                if (Repo::institution()->existsByContextId($customerId, $context->getId())) {
                    $institution = Repo::institution()->get($customerId);
                    if (isset($institution)) {
                        $institutionName = $institution->getLocalizedName();
                        $ror = $institution->getROR();
                        if (isset($ror)) {
                            $institutionId = [
                                'Type' => 'ROR',
                                'Value' => $ror,
                            ];
                        }
                    }
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
            $item['Insitution_ID'] = [
                'Type' => 'ROR',
                'Value' => $ror,
            ];
        }
        return $response->withJson([$item], 200);
    }

    /**
     * Get list of reports supported by the API
     */
    public function getReports(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        if (!$context) {
            throw new \Exception('api.404.resourceNotFound', 404);
        }
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
        $args['report'] = new \APP\sushi\PR();
        return $this->getReport($slimRequest, $response, $args);
    }

    /**
     * COUNTER 'Platform Master Report' [PR].
     * This is a Standard View of the Platform Master Report that presents usage for the overall Platform broken down by Metric_Type
     */
    public function getReportsPR1(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $args['report'] = new \APP\sushi\PR_P1();
        return $this->getReport($slimRequest, $response, $args);
    }

    /**
     * Get the requested report
     */
    public function getReport(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        if (!$context) {
            throw new \Exception('api.404.resourceNotFound', 404);
        }

        $params = $slimRequest->getQueryParams();

        $report = $args['report'];
        $error = $this->processReportParams($report, $params);
        if (isset($error)) {
            return $response->withJson($error['data'], $error['code']);
        }

        $reportHeader = $this->getReportHeader($report);
        $reportItems = $report->getReportItems();

        return $response->withJson([
            'Report_Header' => $reportHeader,
            'Report_Items' => $reportItems,
        ], 200);
    }

    /**
     * Process report parameters
     */
    public function processReportParams(&$report, $params): ?array
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $site = $request->getSite();
        $contextId = $context->getId();

        $platformName = $context->getName($context->getPrimaryLocale());
        $platformId = $context->getPath();
        if ($site->getData('siteSushiPlatform')) {
            if ($site->getData('title')) {
                $platformName = $site->getTitle($site->getPrimaryLocale());
            }
            $platformId = $site->getData('siteSushiPlatformID');
        }

        $error = null;

        $missingRequiredParams = [];
        if (!isset($params['customer_id'])) {
            $missingRequiredParams[] = 'customer_id';
        }
        if (!isset($params['begin_date'])) {
            $missingRequiredParams[] = 'begin_date';
        }
        if (!isset($params['end_date'])) {
            $missingRequiredParams[] = 'end_date';
        }
        if (!empty($missingRequiredParams)) {
            $error['data'] = [
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.missing', ['params' => implode(', ', $missingRequiredParams)])
            ];
            $error['code'] = 400;
            return $error;
        }

        $institutionName = $institutionId = null;
        $customerId = $params['customer_id'];
        if (is_numeric($customerId)) {
            if ($customerId == 0) {
                $institutionName = 'The World';
            } else {
                if (Repo::institution()->existsByContextId($customerId, $contextId)) {
                    $institution = Repo::institution()->get($customerId);
                    if (isset($institution)) {
                        $institutionId = [];
                        $institutionName = $institution->getLocalizedName();
                        $ror = $institution->getROR();
                        if (isset($ror)) {
                            $institutionId[] = ['Type' => 'ROR', 'Value' => $ror];
                        }
                        $institutionId[] = ['Type' => 'Proprietary', 'Value' => $platformId . ':' . $customerId];
                    }
                }
            }
        }
        if (!isset($institutionName)) {
            $error['data'] = [
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.invalid', ['params' => 'customer_id'])
            ];
            $error['code'] = 400;
            return $error;
        }

        // get the first month the usage data is available for COUNTER R5 for
        // it is the next month of the installation date of the release 3.4.0.0 or of the first next release.
        // Once we decided how to allow reprocessing of the log files in old format, this might change.
        $statsService = Services::get('sushiStats');
        $dateInstalled = $statsService->getEarliestDate();
        $earliestDate = date('Y-m-01', strtotime($dateInstalled . ' + 1 months'));
        $lastDate = date('Y-m-d', strtotime('last day of previous month')); // get the last month in the DB table
        $beginDate = $params['begin_date'];
        $endDate = $params['end_date'];

        // validate the date parameters
        $invalidDateErrorMessages = [];
        // validate if begin_date and end_date in the format Y-m-d or Y-m
        if ((!$this->validateDate($beginDate) && !$this->validateDate($beginDate, 'Y-m')) ||
            (!$this->validateDate($endDate) && !$this->validateDate($endDate, 'Y-m'))) {
            $invalidDateErrorMessages[] = __('sushi.exception.3020.dateFormat');
        }
        // validate if begin_date is after the end_date, or
        // if it is the current of future month i.e. later than the lastDate
        if (strtotime($beginDate) >= strtotime($endDate) ||
            strtotime($beginDate) > strtotime($lastDate)) {
            $invalidDateErrorMessages[] = __('sushi.exception.3020.dateRange');
        }
        if (!empty($invalidDateErrorMessages)) {
            $error['data'] = [
                'Code' => 3020,
                'Severity' => 'Error',
                'Message' => 'Invalid Date Arguments',
                'Data' => __('sushi.exception.3020', ['msg' => implode('. ', $invalidDateErrorMessages)])
            ];
            $error['code'] = 400;
            return $error;
        }

        $report->contextId = $contextId;
        $report->customerId = $customerId;
        $report->institutionName = $institutionName;
        if (isset($institutionId)) {
            $report->institutionId = $institutionId;
        }

        // check for warnings
        $warnings = [];
        // check dates
        if (strtotime($endDate) > strtotime($lastDate)) {
            $warnings[] = [
                'Code' => 3031,
                'Severity' => 'Warning',
                'Message' => 'Usage Not Ready for Requested Dates',
                'Data' => __('sushi.exception.3031', ['beginDate' => $beginDate, 'endDate' => $endDate, 'lastDate' => $lastDate])
            ];
            $endDate = $lastDate;
        }
        if (strtotime($beginDate) < strtotime($earliestDate)) {
            $warnings[] = [
                'Code' => 3032,
                'Severity' => 'Warning',
                'Message' => 'Usage No Longer Available for Requested Dates',
                'Data' => __('sushi.exception.3032', ['beginDate' => $beginDate, 'endDate' => $endDate, 'earliestDate' => $earliestDate])
            ];
        }
        // check if requested dates are in the middle of a month if their format is YYYY-MM-DD
        if ($this->validateDate($beginDate) || $this->validateDate($endDate)) {
            $beginDay = date('d', strtotime($beginDate));
            $endDay = date('d', strtotime($endDate));
            $lastDayOfEndMonth = date('t', strtotime($endDate));
            if ($beginDay != '01' || $endDay != $lastDayOfEndMonth) {
                $warnings[] = [
                    'Code' => 1,
                    'Severity' => 'Warning',
                    'Message' => 'Wrong Requested Dates',
                    'Data' => __('sushi.exception.1', ['beginDate' => $beginDate, 'endDate' => $endDate])
                ];
            }
        }

        $report->beginDate = date_format(date_create($beginDate), 'Y-m-01');
        $report->endDate = date_format(date_create($endDate), 'Y-m-t');

        // check for other, not recognized parameters in this context
        $supportedParameters = $report->getSupportedParams();
        $unsupportedParameters = array_diff(array_keys($params), $supportedParameters);
        if (!empty($unsupportedParameters)) {
            $warnings[] = [
                'Code' => 3050,
                'Severity' => 'Warning',
                'Message' => 'Parameter Not Recognized in this Context',
                'Data' => __('sushi.exception.3050', ['params' => implode(', ', $unsupportedParameters)])
            ];
        }

        // validate filters
        $filters = [
            ['Name' => 'Begin_Date', 'Value' => $report->beginDate],
            ['Name' => 'End_Date', 'Value' => $report->endDate],
        ];
        $unsupportedFilterParams = [];
        $supportedFilters = $report->getSupportedFilters();
        foreach ($supportedFilters as $supportedFilter) {
            if (isset($params[$supportedFilter['param']])) {
                $requestedFilterValues = explode('|', $params[$supportedFilter['param']]);
                $validFilters = array_intersect($requestedFilterValues, $supportedFilter['supportedValues']);
                if (!empty($validFilters)) {
                    $filters[] = ['Name' => $supportedFilter['name'], 'Value' => implode('|', $validFilters)];
                }
                if ($supportedFilter['name'] == 'YOP') {
                    $unsupportedYOP = $validYOP = [];
                    foreach ($requestedFilterValues as $yopValue) {
                        if (!preg_match('/\d{4}|\d{4}-\d{4}/', $yopValue)) {
                            $unsupportedYOP[] = $yopValue;
                        } else {
                            $validYOP[] = $yopValue;
                        }
                        // TO-DO: check if between the first and last date_published ???
                    }
                    if (!empty($unsupportedYOP)) {
                        $unsupportedFilterParams[] = $supportedFilter['param'] . '=' . implode('|', $unsupportedYOP);
                    }
                    if (!empty($validYOP)) {
                        $filters[] = ['Name' => $supportedFilter['name'], 'Value' => implode('|', $validYOP)];
                    }
                } elseif ($supportedFilter['name'] == 'Item_Id') {
                    // TO-DO: check if the submission_id exists ???
                    $filters[] = ['Name' => $supportedFilter['name'], 'Value' => $requestedFilterValues];
                } else {
                    $unsupportedFilterValues = array_diff($requestedFilterValues, $supportedFilter['supportedValues']);
                    if (!empty($unsupportedFilterValues)) {
                        $unsupportedFilterParams[] = $supportedFilter['param'] . '=' . implode('|', $unsupportedFilterValues);
                    }
                }
            }
        }
        $report->setFilters($filters);

        // The Platform filter is only intended in cases where there is a single endpoint for multiple platforms.
        // This can be omitted if the service provides report data for only one platform.
        // Thus we will not consider it in the filter list we provide in the response, but will use an exception
        // if it is provided in the request and different that this platform name.
        if (isset($params['platform']) && $params['platform'] != $platformName) {
            $unsupportedFilterParams[] = 'platform=' . $params['platform'];
        }
        if (!empty($unsupportedFilterParams)) {
            $warnings[] = [
                'Code' => 3060,
                'Severity' => 'Warning',
                'Message' => 'Invalid ReportFilter Value',
                'Data' => __('sushi.exception.3060', ['filterValues' => implode(', ', $unsupportedFilterParams)])
            ];
        }
        $report->platformName = $platformName;
        $report->platformId = $platformId;

        // validate attributes
        $attributes = $unsupportedAttributeParams = [];
        $supportedAttributes = $report->getSupportedAttributes();
        foreach ($supportedAttributes as $supportedAttribute) {
            if (isset($params[$supportedAttribute['param']])) {
                $requestedAttributeValues = explode('|', $params[$supportedAttribute['param']]);
                $unsupportedAttributeValues = array_diff($requestedAttributeValues, $supportedAttribute['supportedValues']);
                if (!empty($unsupportedAttributeValues)) {
                    $unsupportedAttributeParams[] = $supportedAttribute['param'] . '=' . implode('|', $unsupportedAttributeValues);
                }
                $validAttributes = array_intersect($requestedAttributeValues, $supportedAttribute['supportedValues']);
                if (!empty($validAttributes)) {
                    $attributes[] = ['Name' => $supportedAttribute['name'], 'Value' => implode('|', $validAttributes)];
                }
            }
        }
        if (!empty($unsupportedAttributeParams)) {
            $warnings[] = [
                'Code' => 3062,
                'Severity' => 'Warning',
                'Message' => 'Invalid ReportAttribute Value',
                'Data' => __('sushi.exception.3062', ['attributeValues' => implode(', ', $unsupportedAttributeParams)])
            ];
        }
        // even if attributes are empty (e.g. for standard views), call setAttribute so that the predefined attributes can be set
        $report->setAttributes($attributes);

        $report->exceptions = $warnings;
        return $error;
    }

    /**
     * Get report header
     */
    protected function getReportHeader($report): array
    {
        $reportHeader = [
            'Created' => date('Y-m-d\TH:i:s\Z', time()),
            'Created_By' => $report->platformName,
            'Customer_ID' => (string) $report->customerId,
            'Report_ID' => $report->getID(),
            'Release' => $report->getRelease(),
            'Report_Name' => $report->getName(),
            'Institution_Name' => $report->institutionName,
        ];
        if (!empty($report->institutionId)) {
            $reportHeader['Institution_ID'][] = $report->institutionId;
        }
        $reportHeader['Report_Filters'] = $report->getFilters();
        if (!empty($report->getAttributes())) {
            $reportHeader['Report_Attributes'] = $report->getAttributes();
        }
        if (!empty($report->exceptions)) {
            $reportHeader['Exceptions'] = $report->exceptions;
        }

        return $reportHeader;
    }

    /**
     * Validate date, check if the date is a valid date and in requested format
     */
    protected function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
