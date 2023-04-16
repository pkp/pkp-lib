<?php

declare(strict_types=1);

/**
 * @file classes/job/models/Job.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Job
 *
 * @brief Laravel Eloquent model for Jobs table
 */

namespace PKP\job\models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\InteractsWithTime;
use PKP\config\Config;
use PKP\job\casts\DatetimeToInt;
use PKP\job\traits\Attributes;

class Job extends Model
{
    use Attributes;
    use InteractsWithTime;

    protected const DEFAULT_MAX_ATTEMPTS = 3;

    public const TESTING_QUEUE = 'queuedTestJob';

    /**
     * Default queue
     *
     * @var string
     */
    protected $defaultQueue;

    /**
     * Max Attempts
     *
     * @var int
     */
    protected $maxAttempts;

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'jobs';

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
        'queue' => 'string',
        'payload' => 'array',
        'attempts' => 'int',
        'reserved_at' => DatetimeToInt::class,
        'available_at' => DatetimeToInt::class,
        'created_at' => DatetimeToInt::class,
    ];

    /**
     * Instantiate the Job model
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setDefaultQueue(Config::getVar('queues', 'default_queue', 'queue'));
        $this->setMaxAttempts(self::DEFAULT_MAX_ATTEMPTS);
    }

    /**
     * Set the default queue
     */
    public function setDefaultQueue(?string $value): self
    {
        $this->defaultQueue = $value;

        return $this;
    }

    /**
     * Get Queue name, in case of nullable value, will be the default one
     */
    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }

    /**
     * Set the Job's max attempts
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    /**
     * Get the Job's max attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Add a local scope for not exceeded attempts
     */
    public function scopeNotExceededAttempts(Builder $query): Builder
    {
        return $query->where('attempts', '<', $this->getMaxAttempts());
    }

    /**
     * Add a local scope to handle jobs associated in a queue
     */
    public function scopeQueuedAt(
        Builder $query,
        ?string $queue = null
    ): Builder {
        return $query->where('queue', $this->getQueue($queue));
    }

    /**
     * Add a local scope to get jobs with queue must defined
     */
    public function scopeNonEmptyQueue(Builder $query): Builder
    {
        return $query->whereNotNull('queue');
    }

    /**
     * Add a local scope to filter jobs by not given queue
     */
    public function scopeNotQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', '!=', $queue);
    }

    /**
     * Add a local scope to filter jobs by given queue
     */
    public function scopeOnQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', '=', $queue);
    }

    /**
     * Add a local scope to filter jobs by non reserved
     */
    public function scopeNonReserved(Builder $query): Builder
    {
        return $query->whereNull('reserved_at');
    }

    /**
     * Retrieve available jobs
     */
    public function scopeIsAvailable(Builder $query): Builder
    {
        return $query->whereNull('reserved_at')
            ->where('available_at', '<=', $this->currentTime());
    }

    /**
     * Get queue's size
     */
    public function size(?string $queue = null): int
    {
        return $this->queuedAt($this->getQueue($queue))
            ->count();
    }

    /**
     * Increment the Job attempts
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Mark a job as reserved to avoid being run by another process
     */
    public function markJobAsReserved(): void
    {
        $this->update([
            'reserved_at' => $this->currentTime(),
            'attempts' => $this->attempts++,
        ]);
    }

    /**
     * Set the reserved_at attribute
     */
    public function setReservedAtAttribute(int $value): self
    {
        if (!$value) {
            $this->attributes['reserved_at'] = null;

            return $this;
        }

        $this->attributes['reserved_at'] = $value;

        return $this;
    }
}
