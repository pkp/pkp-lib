<?php

/**
 * @file invitation/core/enums/InvitationStatus.php
 *
 * Copyright (c) 2023-2024 Simon Fraser University
 * Copyright (c) 2023-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationStatus
 *
 * @brief Enumeration for invitation statuses
 */

namespace PKP\invitation\core\enums;

enum InvitationStatus: string
{
    case INITIALIZED = 'INITIALIZED';
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case CANCELLED = 'CANCELLED';
}
