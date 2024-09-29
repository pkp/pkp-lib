<?php

/**
 * @file classes/invitation/core/contracts/IApiHandleable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IApiHandleable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\core\contracts;

use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\ReceiveInvitationController;

interface IApiHandleable
{
    public function getCreateInvitationController(Invitation $invitation): CreateInvitationController;
    public function getReceiveInvitationController(Invitation $invitation): ReceiveInvitationController;
}
