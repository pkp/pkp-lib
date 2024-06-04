<?php

/**
 * @file classes/invitation/core/contracts/IMailableUrlUpdateable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IMailableUrlUpdateable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\core\contracts;

use Illuminate\Mail\Mailable;

interface IMailableUrlUpdateable
{
    public function updateMailableWithUrl(Mailable $mailable): void;
}
