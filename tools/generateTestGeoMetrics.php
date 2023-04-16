<?php

/**
 * @file tools/generateTestMGeoetrics.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class generateTestGeoMetrics
 *
 * @ingroup tools
 *
 * @brief Generate example Geo metric data.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Sokil\IsoCodes\IsoCodesFactory;

class generateTestGeoMetrics extends \PKP\cliTool\CommandLineTool
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
        echo "Generate fake usage data in the DB table metrics_submission_geo_monthly.\n"
            . "Usage: {$this->scriptName} [contextId] [dateStart] [dateEnd]\n"
            . "contextId      The context to add metrics for.\n"
            . "dateStart      Add monthly metrics after this date. YYYY-MM-DD\n"
            . "dateEnd        Add monthly metrics before this date. YYYY-MM-DD\n";
    }

    /**
     * Generate test metrics
     */
    public function execute()
    {
        $isoCodes = app(IsoCodesFactory::class);
        $countries = $isoCodes->getCountries()->toArray();
        $subDivisions = $isoCodes->getSubdivisions();

        $submissionIds = $this->getPublishedSubmissionIds();

        $currentDate = new DateTime($this->dateStart);
        $endDate = new DateTime($this->dateEnd);
        $endDateTimeStamp = $endDate->getTimestamp();

        $count = 0;
        while ($currentDate->getTimestamp() < $endDateTimeStamp) {
            foreach ($submissionIds as $submissionId) {
                $randomCountryIndex = array_rand($countries);
                $randomCountry = $countries[$randomCountryIndex];

                $countryRegions = $subDivisions->getAllByCountryCode($randomCountry->getAlpha2());

                $randomRegion = '';
                if (!empty($countryRegions)) {
                    $randomSubDivisionIndex = array_rand($countryRegions);
                    $randomSubDivision = $countryRegions[$randomSubDivisionIndex];
                    $regionIsoCodeArray = explode('-', $randomSubDivision->getCode());
                    $randomRegion = $regionIsoCodeArray[1];
                }

                $randomMetric = rand(1, 10);

                DB::table('metrics_submission_geo_monthly')->insert([
                    'context_id' => $this->contextId,
                    'submission_id' => $submissionId,
                    'country' => $randomCountry->getAlpha2(),
                    'region' => $randomRegion,
                    'month' => $currentDate->format('Ym'),
                    'metric' => $randomMetric,
                    'metric_unique' => rand(1, $randomMetric)
                ]);
                $count++;
            }
            $currentDate->add(new DateInterval('P1M'));
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

$tool = new generateTestGeoMetrics($argv ?? []);
$tool->execute();
