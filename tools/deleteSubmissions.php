<?php

/**
 * @file tools/deleteSubmissions.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class deleteSubmissions
 * @ingroup tools
 *
 * @brief CLI tool to delete submissions
 */

require(dirname(__FILE__) . '/bootstrap.inc.php');

class SubmissionDeletionTool extends CommandLineTool
{
    public $preprintIds;

    /**
     * Constructor.
     *
     * @param $argv array command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!sizeof($this->argv)) {
            $this->usage();
            exit(1);
        }

        $this->parameters = $this->argv;
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Permanently removes submission(s) and associated information.  USE WITH CARE.\n"
            . "Usage: {$this->scriptName} submission_id [...]\n";
    }

    /**
     * Delete submission data and associated files
     */
    public function execute()
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        foreach ($this->parameters as $preprintId) {
            $preprint = $submissionDao->getById($preprintId);
            if (!isset($preprint)) {
                printf("Error: Skipping ${preprintId}. Unknown submission.\n");
                continue;
            }
            $submissionDao->deleteById($preprintId);
        }
    }
}

$tool = new SubmissionDeletionTool($argv ?? []);
$tool->execute();
