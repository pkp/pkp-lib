<?php

namespace PKP\invitation\core;

use PKP\context\Context;
use PKP\user\User;

interface InvitationContext
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
