<?php

/**
 * @file classes/invitation/core/PKPInvitationActionRedirectController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationActionRedirectController
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\core;

use APP\core\Request;
use Illuminate\Routing\Controller;
use PKP\invitation\core\enums\InvitationAction;

abstract class PKPInvitationActionRedirectController extends Controller
{
    protected Invitation $invitation;

    public function __construct(Invitation $invitation) 
    {
        $this->invitation = $invitation;
    }

    abstract public function preRedirectActions(InvitationAction $action);

    abstract public function acceptHandle(Request $request): void;
    abstract public function declineHandle(Request $request): void;
}