<?php

/**
 * @file api/v1/stats/sushi/PKPStatsSushiController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiController
 *
 * @ingroup api_v1_stats
 *
 * @brief Controller class to handle API requests for COUNTER R5 SUSHI statistics.
 *
 */

namespace PKP\API\v1\stats\sushi;

use APP\facades\Repo;
use APP\sushi\PR;
use APP\sushi\PR_P1;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\PKPRoutingProvider;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\sushi\CounterR5Report;
use PKP\sushi\SushiException;
use PKP\validation\ValidatorFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PKPStatsSushiController extends PKPBaseController
{
    /**
     * Determine whether the API is public
     */
    public function isPublic(): bool
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $context = $request->getContext();

        if (($site->getData('isSushiApiPublic') !== null && !$site->getData('isSushiApiPublic')) ||
            ($context->getData('isSushiApiPublic') !== null && !$context->getData('isSushiApiPublic'))) {
            return false;
        }

        return true;
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'stats/sushi';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        if ($this->isPublic()) {
            return ['has.context'];
        }

        return [
            'has.context',
            'has.user',
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
        Route::get('status', $this->getStatus(...))
            ->name('stats.sushi.getStatus');

        Route::get('members', $this->getMembers(...))
            ->name('stats.sushi.getMembers');

        Route::get('reports', $this->getReports(...))
            ->name('stats.sushi.getReports');

        Route::get('reports/pr', $this->getReportsPR(...))
            ->name('stats.sushi.getReportsPR');

        Route::get('reports/pr_p1', $this->getReportsPR1(...))
            ->name('stats.sushi.getReportsPR1');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextRequiredPolicy($request));

        if (!$this->isPublic()) {

            $this->addPolicy(new UserRolesRequiredPolicy($request), true);

            $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            foreach ($roleAssignments as $role => $operations) {
                $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
            }

            $this->addPolicy($rolePolicy);
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the current status of the reporting service
     */
    public function getStatus(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        // use only the name in the context primary locale to be consistent
        $contextName = $context->getName($context->getPrimaryLocale());

        return response()->json([
            'Description' => __('sushi.status.description', ['contextName' => $contextName]),
            'Service_Active' => true,
        ], Response::HTTP_OK);
    }

    /**
     * Get the list of consortium members related to a Customer_ID
     */
    public function getMembers(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $site = $request->getSite();
        $params = $illuminateRequest->query();

        if (!isset($params['customer_id'])) {
            // error: missing required customer_id
            return response()->json([
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.missing', ['params' => 'customer_id'])
            ], Response::HTTP_BAD_REQUEST);
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
            return response()->json([
                'Code' => 1030,
                'Severity' => 'Fatal',
                'Message' => 'Insufficient Information to Process Request',
                'Data' => __('sushi.exception.1030.invalid', ['params' => 'customer_id'])
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = [
            'Customer_ID' => $customerId,
            'Name' => $institutionName,
        ];

        if (isset($institutionId)) {
            $item['Institution_ID'] = $institutionId;
        }

        return response()->json([$item], Response::HTTP_OK);
    }

    /**
     * Get list of reports supported by the API
     */
    public function getReports(Request $illuminateRequest): JsonResponse
    {
        $items = $this->getReportList();
        return response()->json($items, Response::HTTP_OK);
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
    public function getReportsPR(Request $illuminateRequest): JsonResponse|StreamedResponse
    {
        return $this->getReportResponse(new PR(), $illuminateRequest);
    }

    /**
     * COUNTER 'Platform Master Report' [PR].
     * This is a Standard View of the Platform Master Report that presents usage for the overall Platform broken down by Metric_Type
     */
    public function getReportsPR1(Request $illuminateRequest): JsonResponse|StreamedResponse
    {
        return $this->getReportResponse(new PR_P1(), $illuminateRequest);
    }

    /** Validate user input for TSV reports */
    protected function _validateUserInput(CounterR5Report $report, array $params): array
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $earliestDate = CounterR5Report::getEarliestDate();
        $lastDate = CounterR5Report::getLastDate();
        $submissionIds = Repo::submission()->getCollector()->filterByContextIds([$context->getId()])->getIds()->implode(',');

        $rules = [
            'begin_date' => [
                'regex:/^\d{4}-\d{2}(-\d{2})?$/',
                'after_or_equal:' . $earliestDate,
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'regex:/^\d{4}-\d{2}(-\d{2})?$/',
                'before_or_equal:' . $lastDate,
                'after_or_equal:begin_date',
            ],
            'item_id' => [
                // TO-ASK: shell this rather be just validation for positive integer?
                'in:' . $submissionIds,
            ],
            'yop' => [
                'regex:/^\d{4}((\||-)\d{4})*$/',
            ],
        ];
        $reportId = $report->getID();
        if (in_array($reportId, ['PR', 'TR', 'IR'])) {
            $rules['metric_type'] = ['required'];
        }

        $errors = [];
        $validator = ValidatorFactory::make(
            $params,
            $rules,
            [
                'begin_date.regex' => __(
                    'manager.statistics.counterR5Report.settings.wrongDateFormat'
                ),
                'end_date.regex' => __(
                    'manager.statistics.counterR5Report.settings.wrongDateFormat'
                ),
                'begin_date.after_or_equal' => __(
                    'stats.dateRange.invalidStartDateMin'
                ),
                'end_date.before_or_equal' => __(
                    'stats.dateRange.invalidEndDateMax'
                ),
                'begin_date.before_or_equal' => __(
                    'stats.dateRange.invalidDateRange'
                ),
                'end_date.after_or_equal' => __(
                    'stats.dateRange.invalidDateRange'
                ),
                'item_id.*' => __(
                    'manager.statistics.counterR5Report.settings.wrongItemId'
                ),
                'yop.regex' => __(
                    'manager.statistics.counterR5Report.settings.wrongYOPFormat'
                ),
            ]
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->getMessages();
        }

        return $errors;
    }

    /**
     * Get the requested report
     */
    protected function getReportResponse(CounterR5Report $report, Request $illuminateRequest): JsonResponse|StreamedResponse
    {
        $params = $illuminateRequest->query();
        //$responseTSV = str_contains($illuminateRequest->getHeaderLine('Accept'), PKPRoutingProvider::RESPONSE_TSV['mime']) ? true : false;
        $responseTSV = $illuminateRequest->accepts(PKPRoutingProvider::RESPONSE_TSV['mime']);

        if ($responseTSV) {
            $errors = $this->_validateUserInput($report, $params);
            if (!empty($errors)) {
                return response()->json($errors, 400);
            }
        }

        try {
            $report->processReportParams($this->getRequest(), $params);
        } catch (SushiException $e) {
            return response()->json($e->getResponseData(), $e->getHttpStatusCode());
        }

        if ($responseTSV) {
            $reportHeader = $report->getTSVReportHeader();
            $reportColumnNames = $report->getTSVColumnNames();
            $reportItems = $report->getTSVReportItems();
            // consider 3030 error (no usage available)
            $key = array_search('3030', array_column($report->warnings, 'Code'));
            if ($key !== false) {
                $error = $report->warnings[$key]['Code'] . ':' . $report->warnings[$key]['Message'] . '(' . $report->warnings[$key]['Data'] . ')';
                foreach ($reportHeader as &$headerRow) {
                    if (in_array('Exceptions', $headerRow)) {
                        $headerRow[1] =
                            $headerRow[1] == '' ?
                            $error :
                            $headerRow[1] . ';' . $error;
                    }
                }
            }
            $report = array_merge($reportHeader, [['']], $reportColumnNames, $reportItems);
            return response()->withFile($report, [], count($reportItems));
        }

        $reportHeader = $report->getReportHeader();
        $reportItems = $report->getReportItems();

        return response()->json([
            'Report_Header' => $reportHeader,
            'Report_Items' => $reportItems,
        ], Response::HTTP_OK);
    }
}
