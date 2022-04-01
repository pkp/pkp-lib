<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Traits/Attributes.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Attributes
 * @ingroup domains
 *
 * @brief Attributes trait for Jobs model
 */

namespace PKP\Domains\Jobs\Traits;

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
    public function getDisplayNameAttribute(): ?string
    {
        if (!$this->payload['displayName']) {
            return null;
        }

        return $this->payload['displayName'];
    }

    /**
     * Return the job's max tries value
     *
     */
    public function getMaxTriesAttribute(): ?string
    {
        if (!$this->payload['maxTries']) {
            return null;
        }

        return $this->payload['maxTries'];
    }

    /**
     * Return the job's delay value
     *
     */
    public function getDelayAttribute(): ?string
    {
        if (!$this->payload['delay']) {
            return null;
        }

        return $this->payload['delay'];
    }

    /**
     * Return the job's timeout value
     *
     */
    public function getTimeoutAttribute(): ?string
    {
        if (!$this->payload['timeout']) {
            return null;
        }

        return $this->payload['timeout'];
    }

    /**
     * Return the job's timeout at value
     *
     */
    public function getTimeoutAtAttribute(): ?string
    {
        if (!$this->payload['timeout_at']) {
            return null;
        }

        $obj = new Carbon($this->payload['timeout_at']);

        return Date::instance($obj);
    }

    /**
     * Return the job's command name value
     *
     */
    public function getCommandNameAttribute(): ?string
    {
        if (!$this->payload['data']['commandName']) {
            return null;
        }

        return $this->payload['data']['commandName'];
    }

    /**
     * Return the job's command value
     *
     */
    public function getCommandAttribute(): array
    {
        if (!$this->payload['data']['command']) {
            return [];
        }

        return (array) unserialize($this->payload['data']['command']);
    }
}
