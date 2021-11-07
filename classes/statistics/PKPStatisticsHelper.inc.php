<?php

/**
* @file classes/statistics/PKPStatisticsHelper.inc.php
*
* Copyright (c) 2013-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class PKPStatisticsHelper
* @ingroup statistics
*
* @brief Statistics helper class.
*
*/

namespace PKP\statistics;

use APP\core\Application;
use PKP\core\PKPApplication;

use PKP\plugins\PluginRegistry;

abstract class PKPStatisticsHelper
{
    // Dimensions:
    // 1) publication object dimension:
    public const STATISTICS_DIMENSION_CONTEXT_ID = 'context_id';
    public const STATISTICS_DIMENSION_PKP_SECTION_ID = 'pkp_section_id';
    public const STATISTICS_DIMENSION_ASSOC_OBJECT_TYPE = 'assoc_object_type';
    public const STATISTICS_DIMENSION_ASSOC_OBJECT_ID = 'assoc_object_id';
    public const STATISTICS_DIMENSION_SUBMISSION_ID = 'submission_id';
    public const STATISTICS_DIMENSION_REPRESENTATION_ID = 'representation_id';
    public const STATISTICS_DIMENSION_ASSOC_TYPE = 'assoc_type';
    public const STATISTICS_DIMENSION_ASSOC_ID = 'assoc_id';
    public const STATISTICS_DIMENSION_FILE_TYPE = 'file_type';
    // 2) time dimension:
    public const STATISTICS_DIMENSION_MONTH = 'month';
    public const STATISTICS_DIMENSION_DAY = 'day';
    // 3) geography dimension:
    public const STATISTICS_DIMENSION_COUNTRY = 'country_id';
    public const STATISTICS_DIMENSION_REGION = 'region';
    public const STATISTICS_DIMENSION_CITY = 'city';
    // 4) metric type dimension (non-additive!):
    public const STATISTICS_DIMENSION_METRIC_TYPE = 'metric_type';

    // Metrics:
    public const STATISTICS_METRIC = 'metric';

    // Ordering:
    public const STATISTICS_ORDER_ASC = 'ASC';
    public const STATISTICS_ORDER_DESC = 'DESC';

    // File type to be used in publication object dimension.
    public const STATISTICS_FILE_TYPE_HTML = 1;
    public const STATISTICS_FILE_TYPE_PDF = 2;
    public const STATISTICS_FILE_TYPE_OTHER = 3;
    public const STATISTICS_FILE_TYPE_DOC = 4;

    // Geography.
    public const STATISTICS_UNKNOWN_COUNTRY_ID = 'ZZ';

    // Constants used to filter time dimension to current time.
    public const STATISTICS_YESTERDAY = 'yesterday';
    public const STATISTICS_CURRENT_MONTH = 'currentMonth';

    // Set the earliest date used
    public const STATISTICS_EARLIEST_DATE = '20010101';

    public function __construct()
    {
    }

    /**
    * Check whether the filter filters on a context
    * and if so: retrieve it.
    *
    * NB: We do not check filters below the context level as this would
    * be unnecessarily complex. We'd have to check whether the given
    * publication objects are actually from the same context. This again
    * would require us to retrieve all context objects for the filtered
    * objects, etc.
    *
    * @param array $filter
    *
    * @return null|Context
    */
    public function &getContext($filter)
    {
        // Check whether the report is on context level.
        $context = null;
        if (isset($filter[self::STATISTICS_DIMENSION_CONTEXT_ID])) {
            $contextFilter = $filter[self::STATISTICS_DIMENSION_CONTEXT_ID];
            if (is_scalar($contextFilter)) {
                // Retrieve the context object.
                $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
                $context = $contextDao->getById($contextFilter);
            }
        }
        return $context;
    }

    /**
    * Identify and canonicalize the filtered metric type.
    *
    * @param string|array $metricType A wildcard can be used to
    * identify all metric types.
    * @param null|Context $context
    * @param string $defaultSiteMetricType
    * @param array $siteMetricTypes
    *
    * @return null|array The canonicalized metric type array. Null if an error
    *  occurred.
    */
    public function canonicalizeMetricTypes($metricType, $context, $defaultSiteMetricType, $siteMetricTypes)
    {
        // Metric type is null: Return the default metric for
        // the filtered context.
        if (is_null($metricType)) {
            if ($context instanceof \PKP\context\Context) {
                $metricType = $context->getDefaultMetricType();
            } else {
                $metricType = $defaultSiteMetricType;
            }
        }

        // Canonicalize the metric type to an array of metric types.
        if (!is_null($metricType)) {
            if (is_scalar($metricType) && $metricType !== '*') {
                // Metric type is a scalar value: Select a single metric.
                $metricType = [$metricType];
            } elseif ($metricType === '*') {
                // Metric type is '*': Select all available metrics.
                if ($context instanceof \PKP\context\Context) {
                    $metricType = $context->getMetricTypes();
                } else {
                    $metricType = $siteMetricTypes;
                }
            } else {
                // Only arrays are otherwise supported as metric type
                // specification.
                if (!is_array($metricType)) {
                    $metricType = null;
                }

                // Metric type is an array: Select multiple metrics. This is the
                // canonical format so no change is required.
            }
        }

        return $metricType;
    }

    /**
     * Get the report plugin that implements
     * the passed metric type.
     *
     * @param string $metricType
     *
     * @return mixed ReportPlugin or null
     */
    public function &getReportPluginByMetricType($metricType)
    {
        $returner = null;

        // Retrieve site-level report plugins.
        $reportPlugins = PluginRegistry::loadCategory('reports', true, \PKP\core\PKPApplication::CONTEXT_SITE);
        if (empty($reportPlugins) || empty($metricType)) {
            return $returner;
        }

        if (is_scalar($metricType)) {
            $metricType = [$metricType];
        }

        foreach ($reportPlugins as $reportPlugin) {
            /** @var ReportPlugin $reportPlugin */
            $pluginMetricTypes = $reportPlugin->getMetricTypes();
            $metricTypeMatches = array_intersect($pluginMetricTypes, $metricType);
            if (!empty($metricTypeMatches)) {
                $returner = $reportPlugin;
                break;
            }
        }

        return $returner;
    }

    /**
     * Get metric type display strings implemented by all
     * available report plugins.
     *
     * @return array Metric type as index and the display string
     * as values.
     */
    public function getAllMetricTypeStrings()
    {
        $allMetricTypes = [];
        $reportPlugins = PluginRegistry::loadCategory('reports', true, \PKP\core\PKPApplication::CONTEXT_SITE);
        if (!empty($reportPlugins)) {
            foreach ($reportPlugins as $reportPlugin) {
                /** @var ReportPlugin $reportPlugin */
                $reportMetricTypes = $reportPlugin->getMetricTypes();
                foreach ($reportMetricTypes as $metricType) {
                    $allMetricTypes[$metricType] = $reportPlugin->getMetricDisplayType($metricType);
                }
            }
        }

        return $allMetricTypes;
    }

    /**
    * Get report column name.
    *
    * @param string $column (optional)
    *
    * @return array|string|null
    */
    public function getColumnNames($column = null)
    {
        $columns = $this->getReportColumnsArray();

        if ($column) {
            if (isset($columns[$column])) {
                return $columns[$column];
            } else {
                return null;
            }
        } else {
            return $columns;
        }
    }

    /**
    * Get object type string.
    *
    * @param mixed $assocType int or null (optional)
    *
    * @return mixed string or array
    */
    public function getObjectTypeString($assocType = null)
    {
        $objectTypes = $this->getReportObjectTypesArray();

        if (is_null($assocType)) {
            return $objectTypes;
        } else {
            if (isset($objectTypes[$assocType])) {
                return $objectTypes[$assocType];
            } else {
                assert(false);
            }
        }
    }

    /**
     * Get file type string.
     *
     * @param mixed $fileType int or null (optional)
     *
     * @return mixed string or array
     */
    public function getFileTypeString($fileType = null)
    {
        $fileTypeArray = $this->getFileTypesArray();

        if (is_null($fileType)) {
            return $fileTypeArray;
        } else {
            if (isset($fileTypeArray[$fileType])) {
                return $fileTypeArray[$fileType];
            } else {
                assert(false);
            }
        }
    }

    /**
     * Get an url that requests a statiscs report,
     * using the passed parameters as request arguments.
     *
     * @param PKPRequest $request
     * @param string $metricType Report metric type.
     * @param array $columns Report columns
     * @param array $filter Report filters.
     * @param array $orderBy (optional) Report order by values.
     *
     * @return string
     */
    public function getReportUrl($request, $metricType, $columns, $filter, $orderBy = [])
    {
        $dispatcher = $request->getDispatcher(); /** @var Dispatcher $dispatcher */
        $args = [
            'metricType' => $metricType,
            'columns' => $columns,
            'filters' => json_encode($filter)
        ];

        if (!empty($orderBy)) {
            $args['orderBy'] = json_encode($orderBy);
        }

        return $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'stats', 'reports', 'generateReport', $args);
    }


    /**
    * Get the geo location tool.
    *
    * @return mixed GeoLocationTool object or null
    */
    public function &getGeoLocationTool()
    {
        $geoLocationTool = null;
        $plugin = PluginRegistry::getPlugin('generic', 'usagestatsplugin'); /** @var UsageStatsPlugin $plugin */
        if ($plugin) {
            $geoLocationTool = $plugin->getGeoLocationTool();
        }
        return $geoLocationTool;
    }


    //
    // Protected methods.
    //
    /**
     * Get all statistics report columns, with their respective
     * names as array values.
     *
     * @return array
     */
    protected function getReportColumnsArray()
    {
        return [
            self::STATISTICS_DIMENSION_ASSOC_ID => __('common.id'),
            self::STATISTICS_DIMENSION_ASSOC_TYPE => __('common.type'),
            self::STATISTICS_DIMENSION_FILE_TYPE => __('common.fileType'),
            self::STATISTICS_DIMENSION_SUBMISSION_ID => $this->getAppColumnTitle(self::STATISTICS_DIMENSION_SUBMISSION_ID),
            self::STATISTICS_DIMENSION_CONTEXT_ID => $this->getAppColumnTitle(self::STATISTICS_DIMENSION_CONTEXT_ID),
            self::STATISTICS_DIMENSION_PKP_SECTION_ID => $this->getAppColumnTitle(self::STATISTICS_DIMENSION_PKP_SECTION_ID),
            self::STATISTICS_DIMENSION_CITY => __('manager.statistics.city'),
            self::STATISTICS_DIMENSION_REGION => __('manager.statistics.region'),
            self::STATISTICS_DIMENSION_COUNTRY => __('common.country'),
            self::STATISTICS_DIMENSION_DAY => __('common.day'),
            self::STATISTICS_DIMENSION_MONTH => __('common.month'),
            self::STATISTICS_DIMENSION_METRIC_TYPE => __('common.metric'),
            self::STATISTICS_METRIC => __('common.count')
        ];
    }

    /**
     * Get all statistics report public objects, with their
     * respective names as array values.
     *
     * @return array
     */
    protected function getReportObjectTypesArray()
    {
        return [
            ASSOC_TYPE_SUBMISSION_FILE => __('submission.submit.submissionFiles')
        ];
    }

    /**
     * Get all file types that have statistics, with
     * their respective names as array values.
     *
     * @return array
     */
    public function getFileTypesArray()
    {
        return [
            self::STATISTICS_FILE_TYPE_PDF => 'PDF',
            self::STATISTICS_FILE_TYPE_HTML => 'HTML',
            self::STATISTICS_FILE_TYPE_OTHER => __('common.other'),
            self::STATISTICS_FILE_TYPE_DOC => 'DOC',
        ];
    }

    /**
     * Get an application specific column name.
     *
     * @param string $column One of the statistics column constant.
     *
     * @return string A localized text.
     */
    abstract protected function getAppColumnTitle($column);
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\statistics\PKPStatisticsHelper', '\PKPStatisticsHelper');
    foreach ([
        'STATISTICS_DIMENSION_CONTEXT_ID',
        'STATISTICS_DIMENSION_PKP_SECTION_ID',
        'STATISTICS_DIMENSION_ASSOC_OBJECT_TYPE',
        'STATISTICS_DIMENSION_ASSOC_OBJECT_ID',
        'STATISTICS_DIMENSION_SUBMISSION_ID',
        'STATISTICS_DIMENSION_REPRESENTATION_ID',
        'STATISTICS_DIMENSION_ASSOC_TYPE',
        'STATISTICS_DIMENSION_ASSOC_ID',
        'STATISTICS_DIMENSION_FILE_TYPE',
        'STATISTICS_DIMENSION_MONTH',
        'STATISTICS_DIMENSION_DAY',
        'STATISTICS_DIMENSION_COUNTRY',
        'STATISTICS_DIMENSION_REGION',
        'STATISTICS_DIMENSION_CITY',
        'STATISTICS_DIMENSION_METRIC_TYPE',
        'STATISTICS_METRIC',
        'STATISTICS_ORDER_ASC',
        'STATISTICS_ORDER_DESC',
        'STATISTICS_FILE_TYPE_HTML',
        'STATISTICS_FILE_TYPE_PDF',
        'STATISTICS_FILE_TYPE_OTHER',
        'STATISTICS_FILE_TYPE_DOC',
        'STATISTICS_UNKNOWN_COUNTRY_ID',
        'STATISTICS_YESTERDAY',
        'STATISTICS_CURRENT_MONTH',
        'STATISTICS_EARLIEST_DATE',
    ] as $constantName) {
        define($constantName, constant('\PKPStatisticsHelper::' . $constantName));
    }
}
