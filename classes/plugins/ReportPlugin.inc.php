<?php

/**
 * @file classes/plugins/ReportPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for report plugins
 */

namespace PKP\plugins;

use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;

abstract class ReportPlugin extends Plugin
{
    //
    // Public methods to be implemented by subclasses.
    //
    /**
     * Retrieve a range of aggregate, filtered, ordered metric values, i.e.
     * a statistics report.
     *
     * @see <https://pkp.sfu.ca/wiki/index.php/OJSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
     * for a full specification of the input and output format of this method.
     *
     * @param null|string|array $metricType metrics selection
     * @param string|array $columns column (aggregation level) selection
     * @param array $filters report-level filter selection
     * @param array $orderBy order criteria
     * @param null|DBResultRange $range paging specification
     *
     * @return null|array The selected data as a simple tabular result set or
     *  null if metrics are not supported by this plug-in, the specified report
     *  is invalid or cannot be produced or another error occurred.
     */
    public function getMetrics($metricType = null, $columns = [], $filters = [], $orderBy = [], $range = null)
    {
        return null;
    }

    /**
     * Metric types available from this plug-in.
     *
     * @return array An array of metric identifiers (strings) supported by
     *   this plugin.
     */
    public function getMetricTypes()
    {
        return [];
    }

    /**
     * Public metric type that will be displayed to end users.
     *
     * @param string $metricType One of the values returned from getMetricTypes()
     *
     * @return null|string The metric type or null if the plug-in does not support
     *  standard metric retrieval or the metric type was not found.
     */
    public function getMetricDisplayType($metricType)
    {
        return null;
    }

    /**
     * Full name of the metric type.
     *
     * @param string $metricType One of the values returned from getMetricTypes()
     *
     * @return null|string The full name of the metric type or null if the
     *  plug-in does not support standard metric retrieval or the metric type
     *  was not found.
     */
    public function getMetricFullName($metricType)
    {
        return null;
    }

    /**
     * Get the columns used in reports by the passed
     * metric type.
     *
     * @param string $metricType One of the values returned from getMetricTypes()
     *
     * @return null|array Return an array with STATISTICS_DIMENSION_...
     * constants.
     */
    public function getColumns($metricType)
    {
        return null;
    }

    /**
     * Get optional columns that are not required for this report
     * to implement the passed metric type.
     *
     * @param string $metricType One of the values returned from getMetricTypes()
     *
     * @return array Return an array with STATISTICS_DIMENSION_...
     * constants.
     */
    public function getOptionalColumns($metricType)
    {
        return [];
    }

    /**
     * Get the object types that the passed metric type
     * counts statistics for.
     *
     * @param string $metricType One of the values returned from getMetricTypes()
     *
     * @return null|array Return an array with ASSOC_TYPE_...
     * constants.
     */
    public function getObjectTypes($metricType)
    {
        return null;
    }

    /**
     * Get the default report templates that each report
     * plugin can implement, with an string to represent it.
     * Subclasses can override this method to add/remove
     * default formats.
     *
     * @param string|array|null $metricTypes Define one or more metric types
     * if you don't want to use all the implemented report metric types.
     *
     * @return array
     */
    public function getDefaultReportTemplates($metricTypes = null)
    {
        return [];
    }


    //
    // Public methods.
    //
    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $dispatcher = $request->getDispatcher();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new RedirectAction($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'stats',
                        'reports',
                        'report',
                        ['pluginName' => $this->getName()]
                    )),
                    __('manager.statistics.reports'),
                    null
                )
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\ReportPlugin', '\ReportPlugin');
}
