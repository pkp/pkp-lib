<?php

/**
 * @file classes/invitation/invitations/contracts/IApiHandleable.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IApiHandleable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations\contacts;

interface IApiHandleable
{
    public function getCreateInvitationController(): PKPCreateInvitationController;
    public function getReceiveInvitationController(): PKPReceiveInvitationController;
}