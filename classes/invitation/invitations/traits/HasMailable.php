<?php

/**
 * @file classes/invitation/invitations/traits/HasMailable.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasMailable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations\traits;

use Illuminate\Mail\Mailable;

trait HasMailable
{
    protected ?Mailable $mailable = null;

    public function setMailable(Mailable $mailable): void
    {
        $this->mailable = $mailable;
    }

    abstract public function getMailable(): Mailable;
}