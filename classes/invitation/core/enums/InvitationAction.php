<?php

/**
 * @file invitation/core/enums/InvitationAction.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationAction
 *
 * @brief Enumeration for invitation actions
 */

namespace PKP\invitation\core\enums;

enum InvitationAction: string
{
    case ACCEPT = 'accept';
    case DECLINE = 'decline';
}
