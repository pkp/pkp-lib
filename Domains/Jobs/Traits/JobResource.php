<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Traits/JobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobResource
 * @ingroup domains
 *
 * @brief JobResource trait
 */

namespace PKP\Domains\Jobs\Traits;

use Carbon\Carbon;

trait JobResource
{
    protected $dateFormat = 'Y-m-d G:i:s T Z';

    protected function formatDate(Carbon $date): string
    {
        return $date->format($this->dateFormat);
    }

    protected function localizedFormatDate(Carbon $date): string
    {
        return $date->formatLocalized($this->dateFormat);
    }

    protected function getJobName(): ?string
    {
        if (!isset($this->resource->payload)) {
            return '-';
        }

        return $this->resource->payload['displayName'] ?? '-';
    }
}
