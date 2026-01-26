<?php

/**
 * @file classes/editorialTask/enums/EditorialTaskType.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum EditorialTaskType
 *
 * @brief The type of the task, either a simple discussion or a task.
 */

namespace PKP\editorialTask\enums;

enum EditorialTaskType: int
{
    case DISCUSSION = 1;
    case TASK = 2;

    public function label(): string
    {
        return match ($this) {
            self::DISCUSSION => __('submission.task.typeDiscussion'),
            self::TASK => __('submission.task.typeTask'),
        };
    }
}
