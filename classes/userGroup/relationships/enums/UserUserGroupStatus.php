<?php

/**
 * @file classes/userGroup/relationships/enums/UserUserGroupStatus.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserUserGroupStatus
 *
 * @brief Enumeration for user user group statuses
 */

namespace PKP\userGroup\relationships\enums;

enum UserUserGroupStatus: string
{
    case STATUS_ACTIVE = 'active';
    case STATUS_ENDED = 'ended';
    case STATUS_ALL = 'all';
}
