<?php
/**
 * @file classes/invitation/stepType/AcceptInvitationStep.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AcceptInvitationStep
 *
 * @brief create accept invitation steps.
 */
namespace PKP\invitation\stepTypes;

use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationContext;
use PKP\user\User;

class AcceptInvitationStep extends InvitationStepTypes
{
    private InvitationContext $contextStrategy;
    private Invitation $invitation;
    private Context $context;
    private User $user;

    public function __construct(
        InvitationContext $contextStrategy,
        Invitation $invitation,
        Context $context,
        User $user
    ) {
        $this->contextStrategy = $contextStrategy;
        $this->invitation = $invitation;
        $this->context = $context;
        $this->user = $user;
    }

    public function getSteps(): array
    {
        return $this->contextStrategy->getAcceptSteps($this->invitation, $this->context, $this->user);
    }
}
