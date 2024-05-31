<?php

/**
 * @file classes/invitation/invitations/contracts/IBackofficeHandleable.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IBackofficeHandleable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations\contracts;

interface IBackofficeHandleable
{
    public function finalise(): void;
    public function decline(): void;
}