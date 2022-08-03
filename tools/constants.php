<?php

/**
 * @file tools/constants.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class constants
 * @ingroup tools
 *
 * @brief Get the value of application constants.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class constants extends \PKP\cliTool\CommandLineTool
{
    public $value;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (isset($argv[1]) && in_array($argv[1], ['--help', '-h'])) {
            $this->usage();
            exit;
        }

        if (isset($argv[1])) {
            $this->value = $argv[1];
        }
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Get the value of application constants.\n"
            . "\n"
            . "Usage: {$this->scriptName} [value]\n"
            . "[value]      Get the name of constants that match this value.\n"
            . "\n"
            . "This will only match application-wide constants available after the initial bootstrap.\n";
    }

    /**
     * Generate test metrics
     */
    public function execute()
    {
        $all = get_defined_constants(true);
        $app = $all['user'];

        if ($this->value) {
            $constants = [];
            foreach ($app as $const => $value) {
                if ($this->value == $value) {
                    $constants[] = $const;
                }
            }
            if (empty($constants)) {
                echo 'No constants were found with that value. This tool only matches constants loaded in the application bootstrap process and may miss other constants.';
            } else {
                print_r($constants);
            }
        } else {
            print_r($app);
        }
    }
}

$tool = new constants($argv ?? []);
$tool->execute();
