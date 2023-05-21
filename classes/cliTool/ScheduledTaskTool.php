<?php

/**
 * @file classes/cliTool/ScheduledTaskTool.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskTool
 *
 * @ingroup tools
 *
 * @brief CLI tool to execute a set of scheduled tasks.
 */

namespace PKP\cliTool;

use PKP\db\DAORegistry;
use PKP\scheduledTask\ScheduledTaskDAO;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\xml\PKPXMLParser;

/** Default XML tasks file to parse if none is specified */
define('TASKS_REGISTRY_FILE', 'registry/scheduledTasks.xml');

class ScheduledTaskTool extends \PKP\cliTool\CommandLineTool
{
    /** @var string the XML file listing the tasks to be executed */
    public $file;

    /** @var ScheduledTaskDAO the DAO object */
    public $taskDao;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     * 		If specified, the first parameter should be the path to
     *		a tasks XML descriptor file (other than the default)
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (isset($this->argv[0])) {
            $this->file = $this->argv[0];
        } else {
            $this->file = TASKS_REGISTRY_FILE;
        }

        if (!file_exists($this->file) || !is_readable($this->file)) {
            printf("Tasks file \"%s\" does not exist or is not readable!\n", $this->file);
            exit(1);
        }

        $this->taskDao = DAORegistry::getDAO('ScheduledTaskDAO');
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Script to run a set of scheduled tasks\n"
            . "Usage: {$this->scriptName} [tasks_file]\n";
    }

    /**
     * Parse and execute the scheduled tasks.
     */
    public function execute()
    {
        $this->parseTasks($this->file);
    }

    /**
     * Parse and execute the scheduled tasks in the specified file.
     *
     * @param string $file
     */
    public function parseTasks($file)
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($file);

        if (!$tree) {
            printf("Unable to parse file \"%s\"!\n", $file);
            exit(1);
        }

        foreach ($tree->getChildren() as $task) {
            $className = $task->getAttribute('class');

            $frequency = $task->getChildByName('frequency');
            if (isset($frequency)) {
                $canExecute = ScheduledTaskHelper::checkFrequency($className, $frequency);
            } else {
                // Always execute if no frequency is specified
                $canExecute = true;
            }

            if ($canExecute) {
                $this->executeTask($className, ScheduledTaskHelper::getTaskArgs($task));
            }
        }
    }

    /**
     * Execute the specified task.
     *
     * @param string $className the class name to execute
     * @param array $args the array of arguments to pass to the class constructors
     */
    public function executeTask($className, $args)
    {
        // Load and execute the task
        if (preg_match('/^[a-zA-Z0-9_.]+$/', $className)) {
            // DEPRECATED as of 3.4.0: Use old class.name.style and import() function (pre-PSR classloading) pkp/pkp-lib#8186
            if (!is_object($task = instantiate($className, null, null, 'execute', $args))) {
                fatalError('Cannot instantiate task class.');
            }
        } else {
            $task = new $className($args);
        }
        $this->taskDao->updateLastRunTime($className);
        $task->execute();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\cliTool\ScheduledTaskTool', '\ScheduledTaskTool');
}
