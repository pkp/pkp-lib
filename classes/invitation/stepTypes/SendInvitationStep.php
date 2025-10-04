<?php

/**
 * @file classes/invitation/stepType/SendInvitationStep.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendInvitationStep
 *
 * @brief create accept invitation steps.
 */

namespace PKP\invitation\stepTypes;

use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationContext;
use PKP\user\User;

class SendInvitationStep extends InvitationStepTypes
{
    private InvitationContext $invitationContext;
    private ?Invitation $invitation;
    private Context $context;
    private ?User $user;

    public function __construct(
        InvitationContext $invitationContext,
        ?Invitation $invitation,
        Context $context,
        ?User $user
    ) {
        $this->invitationContext = $invitationContext;
        $this->invitation = $invitation;
        $this->context = $context;
        $this->user = $user;
    }

    /**
     * Build the steps for sending an invitation.
     *
     * @return array
     */
    public function getSteps(): array
    {
        return $this->invitationContext->getSendSteps(
            $this->invitation,
            $this->context,
            $this->user
        );
    }
}
