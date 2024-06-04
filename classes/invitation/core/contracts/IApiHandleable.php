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

namespace PKP\invitation\core\contacts;

interface IApiHandleable
{
    public function getCreateInvitationController(): CreateInvitationController;
    public function getReceiveInvitationController(): ReceiveInvitationController;
}
