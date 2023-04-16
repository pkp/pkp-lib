<?php

/**
 * @file tools/upgrade.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class upgradeTool
 *
 * @ingroup tools
 *
 * @brief CLI tool for upgrading OPS.
 *
 * Note: Some functions require fopen wrappers to be enabled.
 */

require(dirname(__FILE__) . '/bootstrap.php');

use PKP\cliTool\UpgradeTool;

$tool = new UpgradeTool($argv ?? []);
$tool->execute();
