<?php

declare(strict_types=1);

/**
 * @file classes/Commands/ReviewReminderCommand.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminderCommand
 * @ingroup classes_Commands
 *
 * @brief CLI Command to execute ReviewReminder task
 */

namespace PKP\Commands;

use PKP\cliTool\CommandLineTool;
use PKP\task\ReviewReminder;

class ReviewReminderCommand extends CommandLineTool
{
    public function execute()
    {
        (new ReviewReminder())->execute();
    }
}
