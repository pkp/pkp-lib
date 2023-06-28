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

class InvitationType
{
    public const INVITATION_CONTEXT = 'invitation';
    public const NEW_ROLE_INVITATION_TYPE = 'role';
    public const NEW_BOARD_INVITATION_TYPE = 'board';
    public const NEW_REVIEWER_INVITATION_TYPE = 'reviewer';
}
