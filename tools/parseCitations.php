<?php

/**
 * @file tools/parseCitations.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationsParsingTool
 *
 * @ingroup tools
 *
 * @brief CLI tool to parse existing citations
 */

use APP\core\Application;
use APP\facades\Repo;
use PKP\db\DAORegistry;

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class CitationsParsingTool extends \PKP\cliTool\CommandLineTool
{
    public $parameters;
    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
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
        echo "Parse and save submission(s) citations.\n"
            . "Usage:\n"
            . "{$this->scriptName} all\n"
            . "{$this->scriptName} context context_id [...]\n"
            . "{$this->scriptName} submission submission_id [...]\n";
    }

    /**
     * Parse citations
     */
    public function execute()
    {
        $contextDao = Application::getContextDAO();

        switch (array_shift($this->parameters)) {
            case 'all':
                $contexts = $contextDao->getAll();
                while ($context = $contexts->next()) {
                    $submissions = Repo::submission()->getCollector()->filterByContextIds([$context->getId()])->getMany();
                    foreach ($submissions as $submission) {
                        $this->_parseSubmission($submission);
                    }
                }
                break;
            case 'context':
                foreach ($this->parameters as $contextId) {
                    $context = $contextDao->getById($contextId);
                    if (!isset($context)) {
                        printf("Error: Skipping {$contextId}. Unknown context.\n");
                        continue;
                    }
                    $submissions = Repo::submission()->getCollector()->filterByContextIds([$context->getId()])->getMany();
                    foreach ($submissions as $submission) {
                        $this->_parseSubmission($submission);
                    }
                }
                break;
            case 'submission':
                foreach ($this->parameters as $submissionId) {
                    $submission = Repo::submission()->get($submissionId);
                    if (!isset($submission)) {
                        printf("Error: Skipping {$submissionId}. Unknown submission.\n");
                        continue;
                    }
                    $this->_parseSubmission($submission);
                }
                break;
            default:
                $this->usage();
                break;
        }
    }

    /**
     * Parse the citations of one submission
     *
     * @param Submission $submission
     */
    private function _parseSubmission($submission)
    {
        /** @var CitationDAO */
        $citationDao = DAORegistry::getDAO('CitationDAO');
        foreach ($submission->getData('publications') as $publication) {
            if (!empty($publication->getData('citationsRaw'))) {
                $citationDao->importCitations($publication->getId(), $publication->getData('citationsRaw'));
            }
        }
    }
}

$tool = new CitationsParsingTool($argv ?? []);
$tool->execute();
