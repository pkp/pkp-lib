<?php

/**
 * @file controllers/statistics/form/PKPReportGeneratorForm.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReportGeneratorForm
 * @ingroup controllers_statistics_form
 *
 * @see Form
 *
 * @brief Base form class to generate custom statistics reports.
 */

use APP\statistics\StatisticsHelper;
use PKP\form\Form;

use PKP\statistics\PKPStatisticsHelper;

define('TIME_FILTER_OPTION_YESTERDAY', 0);
define('TIME_FILTER_OPTION_CURRENT_MONTH', 1);
define('TIME_FILTER_OPTION_RANGE_DAY', 2);
define('TIME_FILTER_OPTION_RANGE_MONTH', 3);

abstract class PKPReportGeneratorForm extends Form
{
    /** @var array $_columns */
    private $_columns;

    /** @var array $_columns */
    private $_optionalColumns;

    /** @var array $_objects */
    private $_objects;

    /** @var array $_fileTypes */
    private $_fileTypes;

    /** @var string $_metricType */
    private $_metricType;

    /** @var array $_defaultReportTemplates */
    private $_defaultReportTemplates;

    /** @var int $_reportTemplateIndex */
    private $_reportTemplateIndex;

    /**
     * Constructor.
     *
     * @param array $columns Report column names.
     * @param array $optionalColumns Report column names that are optional.
     * @param array $objects Object types.
     * @param array $fileTypes File types.
     * @param string $metricType The default report metric type.
     * @param array $defaultReportTemplates Default report templates that
     * defines columns and filters selections. The key for each array
     * item is expected to be a localized key that describes the
     * report Template.
     * @param int $reportTemplateIndex (optional) Current report template index
     * from the passed default report templates array.
     */
    public function __construct($columns, $optionalColumns, $objects, $fileTypes, $metricType, $defaultReportTemplates, $reportTemplateIndex = null)
    {
        parent::__construct('controllers/statistics/form/reportGeneratorForm.tpl');

        $this->_columns = $columns;
        $this->_optionalColumns = $optionalColumns;
        $this->_objects = $objects;
        $this->_fileTypes = $fileTypes;
        $this->_metricType = $metricType;
        $this->_defaultReportTemplates = $defaultReportTemplates;
        $this->_reportTemplateIndex = $reportTemplateIndex;

        $this->addCheck(new \PKP\form\validation\FormValidatorArray($this, 'columns', 'required', 'manager.statistics.reports.form.columnsRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $router = $request->getRouter();
        $context = $router->getContext($request);
        $columns = $this->_columns;
        $statsHelper = new StatisticsHelper();

        $availableMetricTypeStrings = $statsHelper->getAllMetricTypeStrings();
        if (count($availableMetricTypeStrings) > 1) {
            $this->setData('metricTypeOptions', $availableMetricTypeStrings);
        }

        $reportTemplateOptions = [];
        $reportTemplates = $this->_defaultReportTemplates;
        foreach ($reportTemplates as $reportTemplate) {
            $reportTemplateOptions[] = __($reportTemplate['nameLocaleKey']);
        }

        if (!empty($reportTemplateOptions)) {
            $this->setData('reportTemplateOptions', $reportTemplateOptions);
        }

        $reportTemplateIndex = (int) $this->_reportTemplateIndex;
        if (!is_null($reportTemplateIndex) && isset($reportTemplates[$reportTemplateIndex])) {
            $reportTemplate = $reportTemplates[$reportTemplateIndex];
            $reportColumns = $reportTemplate['columns'];
            if (is_array($reportColumns)) {
                $this->setData('columns', $reportColumns);
                $this->setData('reportTemplate', $reportTemplateIndex);
                if (isset($reportTemplate['aggregationColumns'])) {
                    $aggreationColumns = $reportTemplate['aggregationColumns'];
                    if (is_array($aggreationColumns)) {
                        $aggreationOptions = $selectedAggregationOptions = [];
                        foreach ($aggreationColumns as $column) {
                            $columnName = $statsHelper->getColumnNames($column);
                            if (!$columnName) {
                                continue;
                            }
                            $aggreationOptions[$column] = $columnName;
                        }
                        $this->setData('aggregationOptions', $aggreationOptions);
                        $this->setData('selectedAggregationOptions', array_intersect($aggreationColumns, $reportColumns));
                    }
                }

                if (isset($reportTemplate['filter']) && is_array($reportTemplate['filter'])) {
                    foreach ($reportTemplate['filter'] as $dimension => $filter) {
                        switch ($dimension) {
                            case PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE:
                                $this->setData('objectTypes', $filter);
                                break;
                        }
                    }
                }
            }
        }

        $timeFilterSelectedOption = $request->getUserVar('timeFilterOption');
        if (is_null($timeFilterSelectedOption)) {
            $timeFilterSelectedOption = TIME_FILTER_OPTION_CURRENT_MONTH;
        }
        switch ($timeFilterSelectedOption) {
            case TIME_FILTER_OPTION_YESTERDAY:
                $this->setData('yesterday', true);
                break;
            case TIME_FILTER_OPTION_CURRENT_MONTH:
            default:
                $this->setData('currentMonth', true);
                break;
            case TIME_FILTER_OPTION_RANGE_DAY:
                $this->setData('byDay', true);
                break;
            case TIME_FILTER_OPTION_RANGE_MONTH:
                $this->setData('byMonth', true);
                break;
        }

        $startTime = $request->getUserDateVar('dateStart');
        $endTime = $request->getUserDateVar('dateEnd');
        if (!$startTime) {
            $startTime = time();
        }
        if (!$endTime) {
            $endTime = time();
        }

        $this->setData('dateStart', $startTime);
        $this->setData('dateEnd', $endTime);

        if (isset($columns[PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY])) {
            $geoLocationTool = $statsHelper->getGeoLocationTool();
            if ($geoLocationTool) {
                $countryCodes = $geoLocationTool->getAllCountryCodes();
                if (!$countryCodes) {
                    $countryCodes = [];
                }
                $countryCodes = array_combine($countryCodes, $countryCodes);
                $this->setData('countriesOptions', $countryCodes);
            }

            $this->setData('showRegionInput', isset($columns[PKPStatisticsHelper::STATISTICS_DIMENSION_REGION]));
            $this->setData('showCityInput', isset($columns[PKPStatisticsHelper::STATISTICS_DIMENSION_CITY]));
        }

        $this->setData('showMonthInputs', isset($columns[PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH]));
        $this->setData('showDayInputs', isset($columns[PKPStatisticsHelper::STATISTICS_DIMENSION_DAY]));

        $orderColumns = $this->_columns;
        $nonOrderableColumns = [PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE,
            PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_REGION,
            PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE,
            PKPStatisticsHelper::STATISTICS_DIMENSION_METRIC_TYPE
        ];

        foreach ($nonOrderableColumns as $column) {
            unset($orderColumns[$column]);
        }

        $this->setData('metricType', $this->_metricType);
        $this->setData('objectTypesOptions', $this->_objects);
        if ($this->_fileTypes) {
            $this->setData('fileTypesOptions', $this->_fileTypes);
        }
        $this->setData('fileAssocTypes', $this->getFileAssocTypes());
        $this->setData('orderColumnsOptions', $orderColumns);
        $this->setData('orderDirectionsOptions', [
            PKPStatisticsHelper::STATISTICS_ORDER_ASC => __('manager.statistics.reports.orderDir.asc'),
            PKPStatisticsHelper::STATISTICS_ORDER_DESC => __('manager.statistics.reports.orderDir.desc')]);

        $columnsOptions = $this->_columns;
        // Reports will always include this column.
        unset($columnsOptions[PKPStatisticsHelper::STATISTICS_METRIC]);
        $this->setData('columnsOptions', $columnsOptions);
        $this->setData('optionalColumns', $this->_optionalColumns);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign user-submitted data to form.
     */
    public function readInputData()
    {
        $this->readUserVars(['columns', 'objectTypes', 'fileTypes', 'objectIds', 'issues',
            'articles', 'timeFilterOption', 'countries', 'regions', 'cityNames',
            'orderByColumn', 'orderByDirection']);
        return parent::readInputData();
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
        $request = Application::get()->getRequest();
        $router = $request->getRouter(); /** @var PageRouter $router */
        $context = $router->getContext($request);
        $statsHelper = new StatisticsHelper();

        $columns = $this->getData('columns');
        $filter = [];
        if ($this->getData('objectTypes')) {
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE] = $this->getData('objectTypes');
        }

        if ($this->getData('objectIds') && count($filter[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE] == 1)) {
            $objectIds = explode(',', $this->getData('objectIds'));
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_ID] = $objectIds;
        }

        if ($this->getData('fileTypes')) {
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE] = $this->getData('fileTypes');
        }

        $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID] = $context->getId();

        if ($this->getData('issues')) {
            $filter[StatisticsHelper::STATISTICS_DIMENSION_ISSUE_ID] = $this->getData('issues');
        }

        if ($this->getData('articles')) {
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID] = $this->getData('articles');
        }

        // Get the time filter data, if any.
        $startTime = $request->getUserDateVar('dateStart', 1, 1, 1, 23, 59, 59);
        $endTime = $request->getUserDateVar('dateEnd', 1, 1, 1, 23, 59, 59);
        if ($startTime && $endTime) {
            $startYear = date('Y', $startTime);
            $endYear = date('Y', $endTime);
            $startMonth = date('m', $startTime);
            $endMonth = date('m', $endTime);
            $startDay = date('d', $startTime);
            $endDay = date('d', $endTime);
        }

        $timeFilterOption = $this->getData('timeFilterOption');
        switch ($timeFilterOption) {
            case TIME_FILTER_OPTION_YESTERDAY:
                $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_DAY] = PKPStatisticsHelper::STATISTICS_YESTERDAY;
                break;
            case TIME_FILTER_OPTION_CURRENT_MONTH:
                $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH] = PKPStatisticsHelper::STATISTICS_CURRENT_MONTH;
                break;
            case TIME_FILTER_OPTION_RANGE_DAY:
            case TIME_FILTER_OPTION_RANGE_MONTH:
                $dimension = PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH;
                $startDate = $startYear . $startMonth;
                $endDate = $endYear . $endMonth;

                if ($timeFilterOption == TIME_FILTER_OPTION_RANGE_DAY) {
                    $startDate .= $startDay;
                    $endDate .= $endDay;

                    $dimension = PKPStatisticsHelper::STATISTICS_DIMENSION_DAY;
                }

                if ($startDate == $endDate) {
                    $filter[$dimension] = $startDate;
                } else {
                    $filter[$dimension]['from'] = $startDate;
                    $filter[$dimension]['to'] = $endDate;
                }
                break;
            default:
                break;
        }

        if ($this->getData('countries')) {
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY] = $this->getData('countries');
        }

        if ($this->getData('regions')) {
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_REGION] = $this->getData('regions');
        }

        if ($this->getData('cityNames')) {
            $cityNames = explode(',', $this->getData('cityNames'));
            $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_CITY] = $cityNames;
        }

        $orderBy = [];
        if ($this->getData('orderByColumn') && $this->getData('orderByDirection')) {
            $orderByColumn = $this->getData('orderByColumn');
            $orderByDirection = $this->getData('orderByDirection');

            $columnIndex = 0;

            foreach ($orderByColumn as $column) {
                if ($column != '0' && !isset($orderBy[$column])) {
                    $orderByDir = $orderByDirection[$columnIndex];
                    if ($orderByDir == PKPStatisticsHelper::STATISTICS_ORDER_ASC || $orderByDir == PKPStatisticsHelper::STATISTICS_ORDER_DESC) {
                        $orderBy[$column] = $orderByDir;
                    }
                }

                $columnIndex++;
            }
        }

        return $statsHelper->getReportUrl($request, $this->_metricType, $columns, $filter, $orderBy);
    }


    //
    // Protected methods.
    //
    /**
     * Return which assoc types represents file objects.
     *
     * @return array
     */
    abstract public function getFileAssocTypes();
}
