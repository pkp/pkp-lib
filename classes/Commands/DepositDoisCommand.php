<?php

declare(strict_types=1);

/**
 * @file classes/Commands/DepositDoisCommand.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositDoisCommand
 * @ingroup classes_Commands
 *
 * @brief CLI Command to execute DepositDois task
 */

namespace PKP\Commands;

use PKP\cliTool\CommandLineTool;
use PKP\task\DepositDois;

class DepositDoisCommand extends CommandLineTool
{
    public function execute()
    {
        (new DepositDois())->execute();
    }
}
