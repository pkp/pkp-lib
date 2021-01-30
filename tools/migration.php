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

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class migrationTool extends CommandLineTool {
	/** @var string Name (fully qualified) of migration class */
	protected $class;

	/** @var string "up" or "down" */
	protected $direction;

	/**
	 * Constructor
	 */
	public function __construct($argv = []) {
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
	public function usage() {
		echo "Run a migration.\n\n"
			. "Usage: {$this->scriptName} qualified.migration.name [up|down]\n\n";
	}

	/**
	 * Execute the specified migration.
	 */
	public function execute() {
		try {
			$migration = instantiate($this->class, ['Illuminate\Database\Migrations\Migration']);
			if (!$migration) throw new Exception('Could not instantiate "' . $this->class . "\"!");

			$direction = $this->direction;
			$migration->$direction();
		} catch (Exception $e) {
			echo 'ERROR: ' . $e->getMessage() . "\n\n";
			exit(2);
		}
	}
}

$tool = new migrationTool(isset($argv) ? $argv : []);
$tool->execute();

