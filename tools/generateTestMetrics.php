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

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;

class generateTestMetrics extends \PKP\cliTool\CommandLineTool
{
    public $contextId;
    public $dateStart;
    public $dateEnd;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
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
    public function usage()
    {
        echo "Generate fake usage data in the metrics table.\n"
            . "Usage: {$this->scriptName} [contextId] [dateStart] [dateEnd]\n"
            . "contextId      The context to add metrics for.\n"
            . "dateStart      Add metrics after this date. YYYY-MM-DD\n"
            . "dateEnd        Add metrics after this date. YYYY-MM-DD\n";
    }

    /**
     * Generate test metrics
     */
    public function execute()
    {
        $submissionIds = $this->getPublishedSubmissionIds();

        $currentDate = new DateTime($this->dateStart);
        $endDate = new DateTime($this->dateEnd);
        $endDateTimeStamp = $endDate->getTimestamp();

        $count = 0;
        while ($currentDate->getTimestamp() < $endDateTimeStamp) {
            foreach ($submissionIds as $submissionId) {
                DB::table('metrics_submission')->insert([
                    'load_id' => 'test_events_' . $currentDate->format('Ymd'),
                    'context_id' => $this->contextId,
                    'submission_id' => $submissionId,
                    'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
                    'date' => $currentDate->format('Y-m-d'),
                    'metric' => rand(1, 10),
                ]);
                $count++;
            }
            $currentDate->add(new DateInterval('P1D'));
        }

        echo $count . ' records added for ' . count($submissionIds) . " submissions.\n";
    }

    /**
     * Get an array of all published submission IDs in the database
     */
    public function getPublishedSubmissionIds()
    {
        return Repo::submission()
            ->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->getIds();
    }
}

$tool = new generateTestMetrics($argv ?? []);
$tool->execute();
