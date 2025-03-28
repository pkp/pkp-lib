<?php

/**
 * @file classes/invitation/core/InvitationUIActionRedirectController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationUIActionRedirectController
 *
 * @brief Declares the create/edit url handlers.
 */

namespace PKP\invitation\core;

use APP\core\Request;
use Illuminate\Routing\Controller;

abstract class InvitationUIActionRedirectController extends Controller
{
    /** @var TInvitation */
    protected Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }
    abstract public function createHandle(Request $request, $userId = null): void;
    abstract public function editHandle(Request $request): void;
}
