<?php

declare(strict_types=1);

/**
 * @file classes/job/traits/JobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobResource
 *
 * @brief JobResource trait
 */

namespace PKP\job\traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

trait JobResource
{
    protected string $dateFormat = 'Y-m-d G:i:s T Z';

    public static function toResourceArray(Model $modelInstance): array
    {
        return (new static($modelInstance))->toArray(app()->get('request'));
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function getCreatedAt(): string
    {
        if (!isset($this->getResource()->created_at)) {
            return '-';
        }

        return $this->formatDate($this->getResource()->created_at);
    }

    public function getReservedAt(): string
    {
        if (!isset($this->getResource()->reserved_at)) {
            return '-';
        }

        return $this->formatDate($this->getResource()->reserved_at);
    }

    public function getAvailableAt(): string
    {
        if (!isset($this->getResource()->available_at)) {
            return '-';
        }

        return $this->formatDate($this->getResource()->available_at);
    }

    public function getFailedAt(): string
    {
        if (!isset($this->getResource()->failed_at)) {
            return '-';
        }

        return $this->formatDate($this->getResource()->failed_at);
    }

    public function getJobName(): ?string
    {
        if (!isset($this->getResource()->payload)) {
            return '-';
        }

        return $this->getResource()->payload['displayName'] ?? '-';
    }

    protected function formatDate(Carbon $date): string
    {
        return $date->format($this->dateFormat);
    }
}
