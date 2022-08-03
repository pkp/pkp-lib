<?php

/**
 * @file tools/migration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class migrationTool
 * @ingroup tools
 */


require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class migrationTool extends \PKP\cliTool\CommandLineTool
{
    /** @var string Name (fully qualified) of migration class */
    protected $class;

    /** @var string "up" or "down" */
    protected $direction;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        array_shift($argv); // Shift the tool name off the top

        $this->class = array_shift($argv);
        $this->direction = array_shift($argv);

        // The source file/directory must be specified and exist.
        if (empty($this->class)) {
            $this->usage();
            exit(2);
        }

        // The migration direction.
        if (!in_array($this->direction, ['up', 'down'])) {
            $this->usage();
            exit(3);
        }
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Run a migration.\n\n"
            . "Usage: {$this->scriptName} \\fully\\qualified\\migration\\Name [up|down]\n\n";
    }

    /**
     * Log install message to stdout.
     *
     * @param $message string
     */
    public function log($message)
    {
        printf("[%s]\n", $message);
    }

    /**
     * Execute the specified migration.
     */
    public function execute()
    {
        $upgrade = new \APP\install\Upgrade([]);
        $upgrade->setLogger($this);
        $migration = new $this->class($upgrade, []);
        try {
            $direction = $this->direction;
            $migration->$direction();
        } catch (Exception $e) {
            echo 'ERROR: ' . $e->getMessage() . "\n\n";
            exit(2);
        }
    }
}

$tool = new migrationTool($argv ?? []);
$tool->execute();
