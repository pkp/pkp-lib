<?php

/**
 * @file invitation/invitations/enums/InvitationStatus.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationStatus
 *
 * @ingroup invitations
 *
 * @brief Enumeration for invitation statuses
 */

namespace PKP\invitation\invitations\enums;

enum InvitationStatus: int
{
    case PENDING = 0;
    case ACCEPTED = 1;
    case DECLINED = 2;
    case EXPIRED = 3;
    case CANCELLED = 4;
}
