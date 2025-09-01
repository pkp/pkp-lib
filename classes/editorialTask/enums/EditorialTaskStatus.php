<?php

/**
 * @file classes/editorialTask/enums/EditorialTaskStatus.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum EditorialTaskStatus
 *
 * @brief The status of the task
 */

namespace PKP\editorialTask\enums;

enum EditorialTaskStatus: int
{
    case NEW = 1;
    case STARTED = 2;
    case CLOSED = 3;
}
