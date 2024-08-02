<?php

/**
 * @file classes/sushi/CounterR5Report.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CounterR5Report
 *
 * @ingroup sushi
 *
 * @brief Base class for COUNTER R5 reports
 *
 */

namespace PKP\sushi;

use APP\facades\Repo;
use DateTime;
use PKP\context\Context;

abstract class CounterR5Report
{
    /** The access method */
    public const ACCESS_METHOD = 'Regular';

    /** Access type */
    public const ACCESS_TYPE = 'OA_Gold';

    /** ID of the context the report is for. */
    public Context $context;

    /** Platform name, either context or site name (if configured so in the site settings). */
    public string $platformName;

    /** The platform ID is configured in the site settings. Uses the context path if no platform ID is configured. */
    public string $platformId;

    /** The customer ID is the ID of the institutional record in this context. */
    public int $customerId;

    /** Name of the institutional record in this context. */
    public string $institutionName;

    /** Available institution IDs (the ID of the institutional record and ROR). */
    public ?array $institutionIds;

    /** The requested begin date */
    public string $beginDate;

    /** The requested end date */
    public string $endDate;

    /** Requested metric types */
    public array $metricTypes = [
        'Total_Item_Investigations',
        'Unique_Item_Investigations',
        'Total_Item_Requests',
        'Unique_Item_Requests'
    ];

    /** Requested Year of Publication (YOP) */
    public array $yearsOfPublication = [];

    /** Warnings displayed in the report header. */
    public array $warnings = [];

    /** List of all filters requested and applied that will be displayed in the report header. */
    protected array $filters = [];

    /** List of all attributes requested and applied that will be displayed in the report header. */
    protected array $attributes = [];

    /** Additional columns/elements to include in the report. */
    protected array $attributesToShow = [];

    /** The granularity of the usage data to include in the report. */
    protected string $granularity = 'Month';

    /**
     * Get report name defined by COUNTER.
     */
    abstract public function getName(): string;

    /**
     * Get report ID defined by COUNTER.
     */
    abstract public function getID(): string;

    /**
     * Get report release.
     */
    public function getRelease(): string
    {
        return '5';
    }

    /**
     * Get report description.
     */
    abstract public function getDescription(): string;

    /**
     * Get API path defined by COUNTER for this report.
     */
    abstract public function getAPIPath(): string;

    /**
     * Get request parameters supported by this report.
     */
    abstract public function getSupportedParams(): array;

    /**
     * Get filters supported by this report.
     */
    abstract public function getSupportedFilters(): array;

    /**
     * Get attributes supported by this report.
     */
    abstract public function getSupportedAttributes(): array;

    /**
     * Get used filters that will be displayed in the report header.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get used attributes that will be displayed in the report header.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set filters based on the requested parameters.
     */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
        foreach ($filters as $filter) {
            switch ($filter['Name']) {
                case 'Metric_Type':
                    $this->metricTypes = explode('|', $filter['Value']);
                    break;
            }
        }
    }

    /**
     * Set attributes based on the requested parameters.
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
        foreach ($attributes as $attribute) {
            switch ($attribute['Name']) {
                case 'Attributes_To_Show':
                    $this->attributesToShow = explode('|', $attribute['Value']);
                    break;
                case 'granularity':
                    $this->granularity = $attribute['Value'];
                    break;
            }
        }
    }

    /**
     * Get report items
     */
    abstract public function getReportItems(): array;

    protected function addWarning(array $exception): void
    {
        $this->warnings[] = $exception;
    }

    /**
     * Process report parameters
     *
     * @throws SushiException
     */
    public function processReportParams($request, $params): void
    {
        $this->context = $request->getContext();
        $this->setPlatform($request->getSite());

        $this->checkRequiredParams($params);

        $this->checkCustomerId($params);

        $this->checkDate($params);

        $this->checkSupportedParams($params);

        $this->checkFilters($params);

        $this->checkAttributes($params);
    }

    /**
     * Set the platform name and ID
     */
    protected function setPlatform($site): void
    {
        $platformName = $this->context->getName($this->context->getPrimaryLocale());
        $platformId = $this->context->getPath();
        if ($site->getData('isSiteSushiPlatform')) {
            if ($site->getData('title')) {
                $platformName = $site->getTitle($site->getPrimaryLocale());
            }
            $platformId = $site->getData('sushiPlatformID');
        }
        $this->platformName = $platformName;
        $this->platformId = $platformId;
    }

    /**
     * Check if the required parameter are provided
     *
     * @throws SushiException
     */
    protected function checkRequiredParams($params): void
    {
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
            throw new SushiException(
                'Insufficient Information to Process Request',
                1030,
                'Fatal',
                __('sushi.exception.1030.missing', ['params' => implode(__('common.commaListSeparator'), $missingRequiredParams)]),
                400
            );
        }
    }

    /**
     * Check if the customer ID is valid
     *
     * @throws SushiException
     */
    protected function checkCustomerId($params): void
    {
        $institutionName = $institutionId = null;
        $customerId = $params['customer_id'];
        if (is_numeric($customerId)) {
            if ($customerId == 0) {
                $institutionName = 'The World';
            } else {
                $institution = Repo::institution()->get($customerId);
                if (isset($institution) && $institution->getContextId() == $this->context->getId()) {
                    $institutionId = [];
                    $institutionName = $institution->getLocalizedName();
                    $ror = $institution->getROR();
                    if (isset($ror)) {
                        $institutionId[] = ['Type' => 'ROR', 'Value' => $ror];
                    }
                    $institutionId[] = ['Type' => 'Proprietary', 'Value' => $this->platformId . ':' . $customerId];
                }
            }
        }
        if (!isset($institutionName)) {
            throw new SushiException(
                'Insufficient Information to Process Request',
                1030,
                'Fatal',
                __('sushi.exception.1030.invalid', ['params' => 'customer_id']),
                400
            );
        }

        $this->customerId = $customerId;
        $this->institutionName = $institutionName;
        if (isset($institutionId)) {
            $this->institutionIds = $institutionId;
        }
    }

    /**
     * Validate the date parameters (begin_date, end_date)
     *
     * @throws SushiException
     */
    protected function checkDate($params): void
    {
        // get the first month the usage data is available for COUNTER R5, it is either:
        // the next month of the COUNTER R5 start, or
        // this journal's first publication date.
        $statsService = app()->get('sushiStats');
        $counterR5StartDate = $statsService->getEarliestDate();
        $firstDatePublished = Repo::publication()->getDateBoundaries(
            Repo::publication()
                ->getCollector()
                ->filterByContextIds([$this->context->getId()])
        )->min_date_published;
        $earliestDate = strtotime($firstDatePublished) > strtotime($counterR5StartDate) ? $firstDatePublished : $counterR5StartDate;
        $earliestDate = date('Y-m-01', strtotime($earliestDate . ' + 1 months'));
        $lastDate = date('Y-m-d', strtotime('last day of previous month')); // get the last month in the DB table
        $beginDate = $params['begin_date'];
        $endDate = $params['end_date'];

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
            throw new SushiException(
                'Invalid Date Arguments',
                3020,
                'Error',
                implode('. ', $invalidDateErrorMessages),
                400
            );
        }

        // check for warnings
        if (strtotime($endDate) > strtotime($lastDate)) {
            $this->addWarning([
                'Code' => 3031,
                'Severity' => 'Warning',
                'Message' => 'Usage Not Ready for Requested Dates',
                'Data' => __('sushi.exception.3031', ['beginDate' => $beginDate, 'endDate' => $endDate, 'lastDate' => $lastDate])
            ]);
            $endDate = $lastDate;
        }
        if (strtotime($beginDate) < strtotime($earliestDate)) {
            $this->addWarning([
                'Code' => 3032,
                'Severity' => 'Warning',
                'Message' => 'Usage No Longer Available for Requested Dates',
                'Data' => __('sushi.exception.3032', ['beginDate' => $beginDate, 'endDate' => $endDate, 'earliestDate' => $earliestDate])
            ]);
            $beginDate = $earliestDate;
        }
        // check if requested dates are in the middle of a month, if their format is YYYY-MM-DD
        if ($this->validateDate($beginDate) || $this->validateDate($endDate)) {
            $beginDay = date('d', strtotime($beginDate));
            $endDay = date('d', strtotime($endDate));
            $lastDayOfEndMonth = date('t', strtotime($endDate));
            if ($beginDay != '01' || $endDay != $lastDayOfEndMonth) {
                $this->addWarning([
                    'Code' => 1,
                    'Severity' => 'Warning',
                    'Message' => 'Wrong Requested Dates',
                    'Data' => __('sushi.exception.1', ['beginDate' => $beginDate, 'endDate' => $endDate])
                ]);
            }
        }

        $this->beginDate = date_format(date_create($beginDate), 'Y-m-01');
        $this->endDate = date_format(date_create($endDate), 'Y-m-t');
    }

    /**
     * Check if there are other, not recognized parameters in this context/for this report
     */
    protected function checkSupportedParams($params): void
    {
        $supportedParameters = $this->getSupportedParams();
        $unsupportedParameters = array_diff(array_keys($params), $supportedParameters);
        if (!empty($unsupportedParameters)) {
            $this->addWarning([
                'Code' => 3050,
                'Severity' => 'Warning',
                'Message' => 'Parameter Not Recognized in this Context',
                'Data' => __('sushi.exception.3050', ['params' => implode(__('common.commaListSeparator'), $unsupportedParameters)])
            ]);
        }
    }

    /**
     * Check required filters
     */
    protected function checkFilters($params): void
    {
        $filters = [
            ['Name' => 'Begin_Date', 'Value' => $this->beginDate],
            ['Name' => 'End_Date', 'Value' => $this->endDate],
        ];
        $unsupportedFilterParams = [];
        $supportedFilters = $this->getSupportedFilters();
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
                    }
                    if (!empty($unsupportedYOP)) {
                        $unsupportedFilterParams[] = $supportedFilter['param'] . '=' . implode('|', $unsupportedYOP);
                    }
                    if (!empty($validYOP)) {
                        $filters[] = ['Name' => $supportedFilter['name'], 'Value' => implode('|', $validYOP)];
                    }
                } elseif ($supportedFilter['name'] == 'Item_Id') {
                    $itemId = array_shift($requestedFilterValues);
                    if (!is_numeric($itemId)) {
                        $this->addWarning([
                            'Code' => 2,
                            'Severity' => 'Warning',
                            'Message' => 'Invalid Item_Id',
                            'Data' => __('sushi.exception.2', ['itemId' => $itemId])
                        ]);
                    } else {
                        $filters[] = ['Name' => $supportedFilter['name'], 'Value' => $itemId];
                        if (!empty($requestedFilterValues)) {
                            $this->addWarning([
                                'Code' => 3,
                                'Severity' => 'Warning',
                                'Message' => 'Wrong Item_Id Value',
                                'Data' => __('sushi.exception.3', ['itemIdValues' => implode('|', $requestedFilterValues)])
                            ]);
                        }
                    }
                } else {
                    $unsupportedFilterValues = array_diff($requestedFilterValues, $supportedFilter['supportedValues']);
                    if (!empty($unsupportedFilterValues)) {
                        $unsupportedFilterParams[] = $supportedFilter['param'] . '=' . implode('|', $unsupportedFilterValues);
                    }
                }
            }
        }
        $this->setFilters($filters);

        // The Platform filter is only intended in cases where there is a single endpoint for multiple platforms.
        // This can be omitted if the service provides report data for only one platform.
        // Thus we will not consider it in the filter list we provide in the response, but will use an exception
        // if it is provided in the request and different that this platform name.
        if (isset($params['platform']) && $params['platform'] != $this->platformName) {
            $unsupportedFilterParams[] = 'platform=' . $params['platform'];
        }
        if (!empty($unsupportedFilterParams)) {
            $this->addWarning([
                'Code' => 3060,
                'Severity' => 'Warning',
                'Message' => 'Invalid ReportFilter Value',
                'Data' => __('sushi.exception.3060', ['filterValues' => implode(__('common.commaListSeparator'), $unsupportedFilterParams)])
            ]);
        }
    }

    /**
     * Check required attributes
     */
    protected function checkAttributes($params): void
    {
        $attributes = $unsupportedAttributeParams = [];
        $supportedAttributes = $this->getSupportedAttributes();
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
            $this->addWarning([
                'Code' => 3062,
                'Severity' => 'Warning',
                'Message' => 'Invalid ReportAttribute Value',
                'Data' => __('sushi.exception.3062', ['attributeValues' => implode(__('common.commaListSeparator'), $unsupportedAttributeParams)])
            ]);
        }
        // even if attributes are empty (e.g. for standard views), call setAttribute so that the predefined attributes can be set
        $this->setAttributes($attributes);
    }

    /**
     * Get report header
     */
    public function getReportHeader(): array
    {
        $reportHeader = [
            'Created' => date('Y-m-d\TH:i:s\Z', time()),
            'Created_By' => $this->platformName,
            'Customer_ID' => (string) $this->customerId,
            'Report_ID' => $this->getID(),
            'Release' => $this->getRelease(),
            'Report_Name' => $this->getName(),
            'Institution_Name' => $this->institutionName,
        ];
        if (!empty($this->institutionIds)) {
            $reportHeader['Institution_ID'] = $this->institutionIds;
        }
        $reportHeader['Report_Filters'] = $this->getFilters();
        if (!empty($this->getAttributes())) {
            $reportHeader['Report_Attributes'] = $this->getAttributes();
        }
        if (!empty($this->warnings)) {
            $reportHeader['Exceptions'] = $this->warnings;
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
