<?php

/**
* @file classes/sushi/IR.php
*
* Copyright (c) 2022 Simon Fraser University
* Copyright (c) 2022 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class IR
* @ingroup sushi
*
* @brief COUNTER R5 SUSHI Item Master Report (IR).
*
*/

namespace APP\sushi;

use APP\core\Services;
use APP\facades\Repo;
use PKP\statistics\PKPStatisticsHelper;
use PKP\sushi\CounterR5Report;

class IR extends CounterR5Report
{
    /** Data type */
    public const DATA_TYPE = 'Repository_Item';

    /** The requested item i.e. preprint ID the report should be created for. */
    public int $itemId = 0;

    /** Preprint contributor */
    public string $itemContributor;

    /** If the details about the parent should be included */
    protected string $includeParentDetails = 'False';

    /**
     * Get report name defined by COUNTER.
     */
    public function getName(): string
    {
        return 'Item Master Report';
    }

    /**
     * Get report ID defined by COUNTER.
     */
    public function getID(): string
    {
        return 'IR';
    }

    /**
     * Get report description.
     */
    public function getDescription(): string
    {
        return __('sushi.reports.ir.description');
    }

    /**
     * Get API path defined by COUNTER for this report.
     */
    public function getAPIPath(): string
    {
        return 'reports/ir';
    }

    /**
     * Get request parameters supported by this report.
     */
    public function getSupportedParams(): array
    {
        return [
            'customer_id',
            'begin_date',
            'end_date',
            'platform',
            'item_id',
            'item_contributor',
            'metric_type',
            'data_type',
            'yop',
            'access_type',
            'access_method',
            'attributes_to_show',
            'include_component_details',
            'include_parent_details',
            'granularity'
        ];
    }

    /**
     * Get filters supported by this report.
     */
    public function getSupportedFilters(): array
    {
        return [
            [
                'name' => 'YOP',
                'supportedValues' => [],
                'param' => 'yop'
            ],
            [
                'name' => 'Item_Id',
                'supportedValues' => [],
                'param' => 'item_id'
            ],
            [
                'name' => 'Access_Type',
                'supportedValues' => [self::ACCESS_TYPE],
                'param' => 'access_type'
            ],
            [
                'name' => 'Data_Type',
                'supportedValues' => [self::DATA_TYPE],
                'param' => 'data_type'
            ],
            [
                'name' => 'Metric_Type',
                'supportedValues' => ['Total_Item_Investigations', 'Unique_Item_Investigations', 'Total_Item_Requests', 'Unique_Item_Requests'],
                'param' => 'metric_type'
            ],
            [
                'name' => 'Access_Method',
                'supportedValues' => [self::ACCESS_METHOD],
                'param' => 'access_method'
            ],
        ];
    }

    /**
     * Get attributes supported by this report.
     *
     * The attributes will be displayed and they define what the metrics will be aggregated by.
     * Data_Type, Access_Method, and Access_Type are currently always the same for this report,
     * so they will only be displayed and not considered for metrics aggregation.
     * The only attributes considered for metrics aggregation are Attributes_To_Show=YOP and granularity=Month.
     * Component details do not exist and parent is not known, so we cannot report this information.
     */
    public function getSupportedAttributes(): array
    {
        return [
            [
                'name' => 'Attributes_To_Show',
                'supportedValues' => ['Article_Version', 'Authors', 'Access_Method', 'Access_Type', 'Data_Type', 'Publication_Date', 'YOP'],
                'param' => 'attributes_to_show'
            ],
            [
                'name' => 'Include_Component_Details',
                'supportedValues' => ['False'],
                'param' => 'include_component_details'
            ],
            [
                'name' => 'Include_Parent_Details',
                'supportedValues' => ['False'],
                'param' => 'include_parent_details'
            ],
            [
                'name' => 'granularity',
                'supportedValues' => ['Month', 'Totals'],
                'param' => 'granularity'
            ],
        ];
    }

    /**
     * Set filters based on the requested parameters.
     */
    public function setFilters(array $filters): void
    {
        parent::setFilters($filters);
        foreach ($filters as $filter) {
            switch ($filter['Name']) {
                case 'YOP':
                    $this->yearsOfPublication = explode('|', $filter['Value']);
                    break;
                case 'Item_Id':
                    $this->itemId = (int) $filter['Value'];
                    break;
            }
        }
    }

    /**
     * Get report items
     */
    public function getReportItems(): array
    {
        $params['contextIds'] = [$this->context->getId()];
        $params['institutionId'] = $this->customerId;
        $params['dateStart'] = $this->beginDate;
        $params['dateEnd'] = $this->endDate;
        $params['yearsOfPublication'] = $this->yearsOfPublication;
        if ($this->itemId > 0) {
            $allowedParams['submissionIds'] = [$this->itemId];
        }
        // do not consider metric_type filter now, but for display

        $statsService = Services::get('sushiStats');
        $metricsQB = $statsService->getQueryBuilder($params);
        // consider attributes to group the metrics by
        $groupBy = ['m.' . PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $orderBy = ['m.' . PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID => 'asc'];
        // The report is on submission level, and relationship between submission_id and YOP is one to one,
        // so no need to group or order by YOP -- it is enough to group and order by submission_id
        if ($this->granularity == 'Month') {
            $groupBy[] = 'm.' . PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH;
            $orderBy['m.' . PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH] = 'asc';
        }
        $metricsQB = $metricsQB->getSum($groupBy);
        // order results
        foreach ($orderBy as $column => $direction) {
            $metricsQB = $metricsQB->orderBy($column, $direction);
        }
        // get metrics results as array
        $results = $metricsQB->get();
        if (!$results->count()) {
            $this->addWarning([
                'Code' => 3030,
                'Severity' => 'Error',
                'Message' => 'No Usage Available for Requested Dates',
                'Data' => __('sushi.exception.3030', ['beginDate' => $this->beginDate, 'endDate' => $this->endDate])
            ]);
        }

        $resultsGroupedBySubmission = $submissions = $items = [];
        foreach ($results as $result) {
            if (!in_array($result->submission_id, $submissions)) {
                $submissions[] = $result->submission_id;
            }
            $resultsGroupedBySubmission[$result->submission_id][] = $result;
        }

        foreach ($resultsGroupedBySubmission as $submissionId => $submissionResults) {
            // Get the submission properties
            $submission = Repo::submission()->get($submissionId);
            if (!$submission) {
                break;
            }
            $currentPublication = $submission->getCurrentPublication();
            $submissionLocale = $submission->getData('locale');
            $itemTitle = $currentPublication->getLocalizedTitle($submissionLocale);

            $item = [
                'Title' => $itemTitle,
                'Platform' => $this->platformName,
                // there is no publisher setting in OPS, so take the platform name
                // which is either server or site title, depending on the COUNTER SUSHI settings
                'Publisher' => $this->platformName,
            ];
            $item['Item_ID'][] = [
                'Type' => 'Proprietary',
                'Value' => $this->platformId . ':' . $submissionId,
            ];
            $doi = $currentPublication->getDoi();
            if (isset($doi)) {
                $item['Item_ID'][] = [
                    'Type' => 'DOI',
                    'Value' => $doi,
                ];
            }

            $datePublished = $submission->getOriginalPublication()->getData('datePublished');
            foreach ($this->attributesToShow as $attributeToShow) {
                if ($attributeToShow == 'Data_Type') {
                    $item['Data_Type'] = self::DATA_TYPE;
                } elseif ($attributeToShow == 'Access_Type') {
                    $item['Access_Type'] = self::ACCESS_TYPE;
                } elseif ($attributeToShow == 'Access_Method') {
                    $item['Access_Method'] = self::ACCESS_METHOD;
                } elseif ($attributeToShow == 'YOP') {
                    $item['YOP'] = date('Y', strtotime($datePublished));
                } elseif ($attributeToShow == 'Publication_Date') {
                    $item['Item_Dates'] = [
                        ['Type' => 'Publication_Date', 'Value' => $datePublished]
                    ];
                } elseif ($attributeToShow == 'Authors') {
                    $authors = $currentPublication->getData('authors');
                    $itemContributors = [];
                    foreach ($authors as $author) {
                        $itemContributor['Type'] = 'Author';
                        $itemContributor['Name'] = $author->getFullName();
                        $orcid = $author->getOrcid();
                        if (isset($orcid) && !empty($orcid)) {
                            $itemContributor['Identifier'] = $orcid;
                        }
                        $itemContributors[] = $itemContributor;
                    }
                    if (!empty($itemContributors)) {
                        $item['Item_Contributors'] = $itemContributors;
                    }
                } elseif ($attributeToShow == 'Article_Version') {
                    // COUNTER R5 does not support preprints, so use VoR here as well
                    $item['Item_Attributes'] = [
                        ['Type' => 'Article_Version', 'Value' => 'VoR']
                    ];
                }
            }

            $performances = [];
            foreach ($submissionResults as $result) {
                // if granularity=Month, the results will contain metrics for each month
                // else the results will only contain the summarized metrics for the whole period
                if (isset($result->month)) {
                    $periodBeginDate = date_format(date_create($result->month . '01'), 'Y-m-01');
                    $periodEndDate = date_format(date_create($result->month . '01'), 'Y-m-t');
                } else {
                    $periodBeginDate = date_format(date_create($this->beginDate), 'Y-m-01');
                    $periodEndDate = date_format(date_create($this->endDate), 'Y-m-t');
                }
                $periodMetrics['Period'] = [
                    'Begin_Date' => $periodBeginDate,
                    'End_Date' => $periodEndDate,
                ];

                $instances = [];
                $metrics['Total_Item_Investigations'] = $result->metric_investigations;
                $metrics['Unique_Item_Investigations'] = $result->metric_investigations_unique;
                $metrics['Total_Item_Requests'] = $result->metric_requests;
                $metrics['Unique_Item_Requests'] = $result->metric_requests_unique;
                // filter here by requested metric types
                foreach ($this->metricTypes as $metricType) {
                    if ($metrics[$metricType] > 0) {
                        $instances[] = [
                            'Metric_Type' => $metricType,
                            'Count' => (int) $metrics[$metricType]
                        ];
                    }
                }
                $periodMetrics['Instance'] = $instances;
                $performances[] = $periodMetrics;
            }
            $item['Performance'] = $performances;
            $items[] = $item;
        }

        return $items;
    }
}
