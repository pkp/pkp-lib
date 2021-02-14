<?php

/**
 * @defgroup server Server
 * Extensions to the pkp-lib "context" concept to specialize it for use in OPS
 * in representing Server objects and server-specific concerns.
 */

/**
 * @file classes/server/Server.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Server
 * @ingroup server
 * @see ServerDAO
 *
 * @brief Describes basic server properties.
 */


define('PUBLISHING_MODE_OPEN', 0);
define('PUBLISHING_MODE_NONE', 2);

import('lib.pkp.classes.context.Context');

class Server extends Context {

	/**
	 * Get "localized" server page title (if applicable).
	 * @return string|null
	 * @deprecated 3.3.0, use getLocalizedData() instead
	 */
	function getLocalizedPageHeaderTitle() {
		$titleArray = $this->getData('name');
		$title = null;

		foreach (array(AppLocale::getLocale(), AppLocale::getPrimaryLocale()) as $locale) {
			if (isset($titleArray[$locale])) return $titleArray[$locale];
		}
		return null;
	}

	/**
	 * Get "localized" server page logo (if applicable).
	 * @return array|null
	 * @deprecated 3.3.0, use getLocalizedData() instead
	 */
	function getLocalizedPageHeaderLogo() {
		$logoArray = $this->getData('pageHeaderLogoImage');
		foreach (array(AppLocale::getLocale(), AppLocale::getPrimaryLocale()) as $locale) {
			if (isset($logoArray[$locale])) return $logoArray[$locale];
		}
		return null;
	}

	//
	// Get/set methods
	//

	/**
	 * Get the association type for this context.
	 * @return int
	 */
	public function getAssocType() {
		return ASSOC_TYPE_SERVER;
	}

	/**
	 * @copydoc DataObject::getDAO()
	 */
	function getDAO() {
		return DAORegistry::getDAO('ServerDAO');
	}


	//
	// Statistics API
	//
	/**
	 * Return all metric types supported by this server.
	 *
	 * @return array An array of strings of supported metric type identifiers.
	 */
	function getMetricTypes($withDisplayNames = false) {
		// Retrieve report plugins enabled for this server.
		$reportPlugins = PluginRegistry::loadCategory('reports', true, $this->getId());

		// Run through all report plugins and retrieve all supported metrics.
		$metricTypes = array();
		foreach ($reportPlugins as $reportPlugin) {
			$pluginMetricTypes = $reportPlugin->getMetricTypes();
			if ($withDisplayNames) {
				foreach ($pluginMetricTypes as $metricType) {
					$metricTypes[$metricType] = $reportPlugin->getMetricDisplayType($metricType);
				}
			} else {
				$metricTypes = array_merge($metricTypes, $pluginMetricTypes);
			}
		}

		return $metricTypes;
	}

	/**
	 * Returns the currently configured default metric type for this server.
	 * If no specific metric type has been set for this server then the
	 * site-wide default metric type will be returned.
	 *
	 * @return null|string A metric type identifier or null if no default metric
	 *   type could be identified.
	 */
	function getDefaultMetricType() {
		$defaultMetricType = $this->getData('defaultMetricType');

		// Check whether the selected metric type is valid.
		$availableMetrics = $this->getMetricTypes();
		if (empty($defaultMetricType)) {
			if (count($availableMetrics) === 1) {
				// If there is only a single available metric then use it.
				$defaultMetricType = $availableMetrics[0];
			} else {
				// Use the site-wide default metric.
				$application = Application::get();
				$defaultMetricType = $application->getDefaultMetricType();
			}
		} else {
			if (!in_array($defaultMetricType, $availableMetrics)) return null;
		}
		return $defaultMetricType;
	}

	/**
	 * Retrieve a statistics report pre-filtered on this server.
	 *
	 * @see <http://pkp.sfu.ca/wiki/index.php/OPSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
	 * for a full specification of the input and output format of this method.
	 *
	 * @param $metricType null|integer|array metrics selection
	 * @param $columns integer|array column (aggregation level) selection
	 * @param $filters array report-level filter selection
	 * @param $orderBy array order criteria
	 * @param $range null|DBResultRange paging specification
	 *
	 * @return null|array The selected data as a simple tabular
	 *  result set or null if metrics are not supported by this server.
	 */
	function getMetrics($metricType = null, $columns = array(), $filter = array(), $orderBy = array(), $range = null) {
		// Add a server filter and run the report.
		$filter[STATISTICS_DIMENSION_CONTEXT_ID] = $this->getId();
		$application = Application::get();
		return $application->getMetrics($metricType, $columns, $filter, $orderBy, $range);
	}
}


