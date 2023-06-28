<?php

declare(strict_types=1);

/**
 * @file classes/invitation/traits/Attributes.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Attributes
 *
 * @brief Attributes trait for Invitation model
 */

namespace PKP\invitation\traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Those attributes become from payload array
 */
trait Attributes
{
    /**
     * Return the job's display name value
     *
     */
    public function getHandlerClassNameAttribute(): ?string
    {
        if (!$this->payload['className']) {
            return null;
        }

        return $this->payload['className'];
    }

    /**
     * Return the job's max tries value
     *
     */
    public function getHandlerDataAttribute(): ?array
    {
        if (!$this->payload['data']) {
            return null;
        }

        return $this->payload['data'];
    }
}
