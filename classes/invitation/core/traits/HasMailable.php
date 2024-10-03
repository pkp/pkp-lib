<?php

/**
 * @file classes/invitation/core/traits/HasMailable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasMailable
 *
 * @brief Trait for all invitations that have their own distinct mailables
 */

namespace PKP\invitation\core\traits;

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
