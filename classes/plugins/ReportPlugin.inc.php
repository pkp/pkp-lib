<?php

/**
 * @file classes/plugins/ReportPlugin.inc.php
 *
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for report plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class ReportPlugin extends Plugin {

	function ReportPlugin() {
		parent::Plugin();
	}


	//
	// Abstract public methods to be implemented by subclasses.
	//
	/**
	* Retrieve a range of aggregate, filtered, ordered metric values, i.e.
	* a statistics report.
	*
	* @see <http://pkp.sfu.ca/wiki/index.php/OJSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
	* for a full specification of the input and output format of this method.
	*
	* @param $metricType null|string|array metrics selection
	* @param $columns string|array column (aggregation level) selection
	* @param $filters array report-level filter selection
	* @param $orderBy array order criteria
	* @param $range null|DBResultRange paging specification
	*
	* @return null|array The selected data as a simple tabular result set or
	*  null if metrics are not supported by this plug-in, the specified report
	*  is invalid or cannot be produced or another error occurred.
	*/
	abstract function getMetrics($metricType = null, $columns = array(), $filters = array(), $orderBy = array(), $range = null);

	/**
	 * Metric types available from this plug-in.
	 *
	 * @return array An array of metric identifiers (strings) supported by
	 *   this plugin.
	 */
	abstract function getMetricTypes();

	/**
	 * Public metric type that will be displayed to end users.
	 * @param $metricType string One of the values returned from getMetricTypes()
	 * @return null|string The metric type or null if the plug-in does not support
	 *  standard metric retrieval or the metric type was not found.
	 */
	abstract function getMetricDisplayType($metricType);

	/**
	 * Full name of the metric type.
	 * @param $metricType string One of the values returned from getMetricTypes()
	 * @return null|string The full name of the metric type or null if the
	 *  plug-in does not support standard metric retrieval or the metric type
	 *  was not found.
	 */
	abstract function getMetricFullName($metricType);

	/**
	 * Get the columns used in reports by the passed
	 * metric type.
	 * @param $metricType string One of the values returned from getMetricTypes()
	 * @return null|array Return an array with STATISTICS_DIMENSION_...
	 * constants.
	 */
	abstract function getColumns($metricType);

	/**
	 * Get the object types that the passed metric type
	 * counts statistics for.
	 * @param $metricType string One of the values returned from getMetricTypes()
	 * @return null|array Return an array with ASSOC_TYPE_...
	 * constants.
	 */
	abstract function getObjectTypes($metricType);

	/**
	* Get the default report templates that each report
	* plugin can implement, with an string to represent it.
	* Subclasses can override this method to add/remove
	* default formats.
	* @param $metricTypes string|array|null Define one or more metric types
	* if you don't want to use all the implemented report metric types.
	* @return array
	*/
	abstract function getDefaultReportTemplates($metricTypes = null);


	//
	// Public methods.
	//
	/**
	 * Set the page's breadcrumbs, given the plugin's tree of items
	 * to append.
	 * @param $crumbs Array ($url, $name, $isTranslated)
	 * @param $subclass boolean
	 */
	function setBreadcrumbs($crumbs = array(), $isSubclass = false) {
		$templateMgr = TemplateManager::getManager();
		$pageCrumbs = array(
			array(
				Request::url(null, 'user'),
				'navigation.user'
			),
			array(
				Request::url(null, 'manager'),
				'user.role.manager'
			),
			array (
				Request::url(null, 'manager', 'reports'),
				'manager.statistics.reports'
			)
		);
		if ($isSubclass) $pageCrumbs[] = array(
			Request::url(null, 'manager', 'reports', array('plugin', $this->getName())),
			$this->getDisplayName(),
			true
		);

		$templateMgr->assign('pageHierarchy', array_merge($pageCrumbs, $crumbs));
	}

	/**
	 * Display the import/export plugin UI.
	 * @param $args Array The array of arguments the user supplied.
	 */
	function display($args) {
		$templateManager = TemplateManager::getManager();
		$templateManager->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
	}

	/**
	 * Display verbs for the management interface.
	 */
	function getManagementVerbs() {
		return array(
			array(
				'reports',
				__('manager.statistics.reports')
			)
		);
	}

	/**
	 * Perform management functions
	 */
	function manage($verb, $args) {
		if ($verb === 'reports') {
			Request::redirect(null, 'manager', 'report', $this->getName());
		}
		return false;
	}

	/**
	 * Extend the {url ...} smarty to support reporting plugins.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array('plugin', $this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}
		return $smarty->smartyUrl($params, $smarty);
	}
}

?>
