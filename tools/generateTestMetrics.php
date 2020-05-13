<?php

/**
 * @file tools/generateTestMetrics.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class generateTestMetrics
 * @ingroup tools
 *
 * @brief Generate example metric data.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class generateTestMetrics extends CommandLineTool {

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);
	}

	/**
	 * Generate some test metrics.
	 */
	function execute() {
		// Fix missing schema constant error
		import('classes.core.Services');
		Services::get('schema');

		// Generate some usage statistics for the last 90 days
		import('lib.pkp.classes.submission.PKPSubmission');
		import('classes.statistics.StatisticsHelper');
		$metricsDao = DAORegistry::getDAO('MetricsDAO');
		$submissionIds = array_map(
			function($submission) {
				return $submission->getId();
			},
			iterator_to_array(Services::get('submission')->getMany(['contextId' => 1, 'status' => STATUS_PUBLISHED]))
		);
		$currentDate = new DateTime();
		$currentDate->sub(new DateInterval('P90D'));
		$dateEnd = new DateTime();
		while ($currentDate->getTimestamp() < $dateEnd->getTimestamp()) {
			foreach ($submissionIds as $submissionId) {
				$metricsDao->insertRecord([
					'load_id' => 'test_events_' . $currentDate->format('Ymd'),
					'assoc_type' => ASSOC_TYPE_SUBMISSION,
					'assoc_id' => $submissionId,
					'submission_id' => $submissionId,
					'metric_type' => METRIC_TYPE_COUNTER,
					'metric' => rand(5, 10),
					'day' => $currentDate->format('Ymd'),
				]);
			}
			$currentDate->add(new DateInterval('P1D'));
		}
	}
}

$tool = new generateTestMetrics(isset($argv) ? $argv : array());
$tool->execute();


