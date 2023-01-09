<?php

declare(strict_types=1);

/**
 * @file classes/job/models/FailedJob.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FailedJob
 *
 * @brief Laravel Eloquent model for Failed Jobs table
 */

namespace PKP\job\models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PKP\job\traits\Attributes;

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
     * The attributes that are not mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = [];

    /**
     * Casting attributes to their native types
     *
     * @var string[]
     */
    protected $casts = [
        'connection'    => 'string',
        'payload'       => 'array',
        'queue'         => 'string',
        'exception'     => 'string',
        'failed_at'     => 'datetime',
    ];

    /**
     * Add a local scope to handle jobs associated in a queue
     */
    public function scopeQueuedAt(Builder $query, string $queue): Builder 
    {
        return $query->where('queue', $queue);
    }

    /**
     * Return the core exception message without the full exception trace
     */
    public function exceptionMessage(): string
    {
        return preg_replace('/\s+/', ' ', trim(explode('Stack trace', $this->exception)[0]));
    }
}
