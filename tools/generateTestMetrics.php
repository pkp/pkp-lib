<?php

/**
 * @file tools/generateTestMetrics.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class generateTestMetrics
 * @ingroup tools
 *
 * @brief Generate example metric data.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class generateTestMetrics extends CommandLineTool {

	var $contextId;
	var $dateStart;
	var $dateEnd;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		if (sizeof($this->argv) < 3) {
			$this->usage();
			exit(1);
		}

		$this->contextId = (int) $argv[1];
		$this->dateStart = $argv[2];
		$this->dateEnd = $argv[3];
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Generate fake usage data in the metrics table.\n"
			. "Usage: {$this->scriptName} [contextId] [dateStart] [dateEnd]\n"
			. "contextId      The context to add metrics for.\n"
			. "dateStart      Add metrics after this date. YYYY-MM-DD\n"
			. "dateEnd        Add metrics after this date. YYYY-MM-DD\n";
	}

	/**
	 * Generate test metrics
	 */
	function execute() {
		$submissionIds = $this->getPublishedSubmissionIds();

		$currentDate = new DateTime($this->dateStart);
		$endDate = new DateTime($this->dateEnd);
		$endDateTimeStamp = $endDate->getTimestamp();

		$metricsDao = DAORegistry::getDao('MetricsDAO');

		$count = 0;
		while ($currentDate->getTimestamp() < $endDateTimeStamp) {
			foreach ($submissionIds as $submissionId) {
				$metricsDao->insertRecord([
					'load_id' => 'test_events_' . $currentDate->format('Ymd'),
					'assoc_type' => ASSOC_TYPE_SUBMISSION,
					'assoc_id' => $submissionId,
					'submission_id' => $submissionId,
					'metric_type' => METRIC_TYPE_COUNTER,
					'metric' => rand(1, 10),
					'day' => $currentDate->format('Ymd'),
				]);
				$count++;
			}
			$currentDate->add(new DateInterval('P1D'));
		}

		echo $count . " records added for " . count($submissionIds) . " submissions.\n";
	}

	/**
	 * Get an array of all published submission IDs in the database
	 */
	public function getPublishedSubmissionIds() {
		import('classes.submission.Submission');
		$submissionsIterator = Services::get('submission')->getMany(['contextId' => $this->contextId, 'status' => STATUS_PUBLISHED]);
		$submissionIds = [];
		foreach ($submissionsIterator as $submission) {
			$submissionIds[] = $submission->getId();
		}
		return $submissionIds;
	}
}

$tool = new generateTestMetrics(isset($argv) ? $argv : array());
$tool->execute();


