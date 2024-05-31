<?php

/**
 * @file invitation/invitations/enums/InvitationAction.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationAction
 *
 * @brief Enumeration for invitation actions
 */

namespace PKP\invitation\invitations\enums;

enum InvitationAction: string
{
    case ACCEPT = 'accept';
    case DECLINE = 'decline';
}
