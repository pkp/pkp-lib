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

class InvitationStatus
{
    public const INVITATION_STATUS_PENDING = 0;
    public const INVITATION_STATUS_ACCEPTED = 1;
    public const INVITATION_STATUS_DECLINED = 2;
    public const INVITATION_STATUS_EXPIRED = 3;
    public const INVITATION_STATUS_CANCELLED = 4;
}
