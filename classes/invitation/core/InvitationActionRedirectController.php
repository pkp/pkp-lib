<?php

/**
 * @file classes/invitation/core/InvitationActionRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationActionRedirectController
 *
 * @brief Declares the accept/decline url handlers.
 */

namespace PKP\invitation\core;

use APP\core\Request;
use Illuminate\Routing\Controller;
use PKP\invitation\core\enums\InvitationAction;

abstract class InvitationActionRedirectController extends Controller
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
