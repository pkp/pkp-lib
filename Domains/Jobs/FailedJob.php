<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/FailedJob.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FailedJob
 * @ingroup domains
 *
 * @brief Laravel Eloquent model for Failed Jobs table
 */

namespace PKP\Domains\Jobs;

use Illuminate\Database\Eloquent\Builder;

use PKP\Domains\Jobs\Traits\Attributes;
use PKP\Support\Database\Model;

class FailedJob extends Model
{
    use Attributes;

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'failed_jobs';

    /**
     * Model's primary key
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Model's timestamp fields
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Following attributes could be mass assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'connection',
        'payload',
        'queue',
        'exception',
    ];

    /**
     * Casting attributes to their native types
     *
     * @var string[]
     */
    protected $casts = [
        'connection' => 'string',
        'payload' => 'array',
        'queue' => 'string',
        'exception' => 'string',
        'failed_at' => 'datetime',
    ];

    /**
     * Add a local scope to handle jobs associated in a queue
     */
    public function scopeQueuedAt(
        Builder $query,
        string $queue
    ): Builder {
        return $query->where('queue', $queue);
    }

    /**
     * Get queue's size
     */
    public function size(string $queue): int
    {
        return $this->queuedAt($queue)
            ->count();
    }
}
