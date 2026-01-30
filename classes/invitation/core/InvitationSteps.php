<?php

/**
 * @file classes/invitation/core/InvitationSteps.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationSteps
 *
 * @brief Interface for defining steps in the invitation process
 */

namespace PKP\invitation\core;

use PKP\context\Context;
use PKP\user\User;

interface InvitationSteps
{
    /**
     * Steps for sending an invitation.
     */
    public function getSendSteps(?Invitation $invitation, Context $context, ?User $user): array;

    /**
     * Steps for accepting an invitation.
     */
    public function getAcceptSteps(Invitation $invitation, Context $context, User $user): array;
}
