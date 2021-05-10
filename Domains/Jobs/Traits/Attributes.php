<?php

declare(strict_types=1);

namespace PKP\Domains\Jobs\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Those attributes become from payload array
 */
trait Attributes
{
    public function getDisplayNameAttribute()
    {
        if (!$this->payload['displayName']) {
            return null;
        }

        return $this->payload['displayName'];
    }

    public function getMaxTriesAttribute()
    {
        if (!$this->payload['maxTries']) {
            return null;
        }

        return $this->payload['maxTries'];
    }

    public function getDelayAttribute()
    {
        if (!$this->payload['delay']) {
            return null;
        }

        return $this->payload['delay'];
    }

    public function getTimeoutAttribute()
    {
        if (!$this->payload['timeout']) {
            return null;
        }

        return $this->payload['timeout'];
    }

    public function getTimeoutAtAttribute()
    {
        if (!$this->payload['timeout_at']) {
            return null;
        }

        $obj = new Carbon($this->payload['timeout_at']);

        return Date::instance($obj);
    }

    public function getCommandNameAttribute()
    {
        if (!$this->payload['data']['commandName']) {
            return null;
        }

        return $this->payload['data']['commandName'];
    }

    public function getCommandAttribute()
    {
        if (!$this->payload['data']['command']) {
            return [];
        }

        return unserialize($this->payload['data']['command']);
    }
}
