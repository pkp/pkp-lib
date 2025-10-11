<?php

/**
 * @file classes/editorialTask/enums/EditorialTaskDueInterval.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum EditorialTaskDueInterval
 *
 * @brief The status of the task
 */

namespace PKP\editorialTask\enums;

enum EditorialTaskDueInterval: string
{
    case P1W = 'P1W';
    case P2W = 'P2W';
    case P3W = 'P3W';
    case P4W = 'P4W';
    case P1M = 'P1M';
    case P1M15D = 'P1M15D';
    case P2M = 'P2M';
    case P2M15D = 'P2M15D';
    case P3M = 'P3M';


    public function label(): string
    {
        return match ($this) {
            self::P1W => __('submission.task.dueInterval.1week'),
            self::P2W => __('submission.task.dueInterval.2weeks'),
            self::P3W => __('submission.task.dueInterval.3weeks'),
            self::P4W => __('submission.task..dueInterval.4weeks'),
            self::P1M => __('submission.task.dueInterval.1month'),
            self::P1M15D => __('submission.task.dueInterval.1month.15days'),
            self::P2M => __('submission.task.dueInterval.2months'),
            self::P2M15D => __('submission.task.dueInterval.2months.15days'),
            self::P3M => __('submission.task.dueInterval.3months'),
        };
    }
}
