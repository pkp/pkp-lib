<?php

/**
 * @file classes/invitation/invitations/contracts/IMailableUrlUpdateable.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IMailableUrlUpdateable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations\contracts;

use Illuminate\Mail\Mailable;

interface IMailableUrlUpdateable
{
    function updateMailableWithUrl(Mailable $mailable): void;
}