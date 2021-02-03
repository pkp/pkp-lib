<?php
/**
 * @file components/PKPStatsEditorialPage.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialPage
 * @ingroup classes_controllers_stats
 *
 * @brief A class to prepare the data object for the editorial statistics
 *   UI component
 */
namespace PKP\components;

use PKP\components\PKPStatsComponent;

import('classes.statistics.StatisticsHelper');

class PKPStatsEditorialPage extends PKPStatsComponent {
	/** @var array A key/value array of active submissions by stage */
	public $activeByStage = [];

	/** @var string The URL to get the averages from the API */
	public $averagesApiUrl = [];

	/** @var array List of stats that should be converted to percentages */
	public $percentageStats = [];

	/** @var array List of stats details to display in the table */
	public $tableRows = [];

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		$config = parent::getConfig();

		$config = array_merge(
			$config,
			[
				'activeByStage' => $this->activeByStage,
				'averagesApiUrl' => $this->averagesApiUrl,
				'percentageStats' => $this->percentageStats,
				'tableRows' => $this->tableRows,
			]
		);

		return $config;
	}
}
