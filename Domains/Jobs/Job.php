<?php

declare(strict_types=1);

namespace PKP\Domains\Jobs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\InteractsWithTime;

use PKP\config\Config;
use PKP\Domains\Jobs\Traits\Attributes;
use PKP\Domains\Jobs\Traits\Worker;
use PKP\Support\Database\Model;

class Job extends Model
{
    use Attributes;
    use InteractsWithTime;
    use Worker;

    protected const DEFAULT_MAX_ATTEMPTS = 3;

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
     * Following attributes could be mass assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'attempts',
        'payload',
        'queue',
        'reserved_at',
    ];

    /**
     * Following attributes will be hidden from arrays
     *
     * @var string[]
     */
    protected $hidden = [
        'available_at',
        'created_at',
        'reserved_at',
    ];

    /**
     * Casting attributes to their native types
     *
     * @var string[]
     */
    protected $casts = [
        'attempts' => 'int',
        'available_at' => 'datetime',
        'created_at' => 'datetime',
        'payload' => 'array',
        'queue' => 'string',
        'reserved_at' => 'datetime',
    ];

    /**
     * Instantiate the Job model
     *
     *
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setDefaultQueue(Config::getVar('queues', 'default_queue', null));
        $this->setMaxAttempts(self::DEFAULT_MAX_ATTEMPTS);
    }

    public function setDefaultQueue($value): self
    {
        $this->defaultQueue = $value;

        return $this;
    }

    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }

    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function scopeNotExcedeedAttempts(Builder $query): Builder
    {
        return $query->where('attempts', '<', $this->getMaxAttempts());
    }

    public function scopeQueuedAt(
        Builder $query,
        ?string $queue = null
    ): Builder {
        return $query->where('queue', $this->getQueue($queue));
    }

    /**
     * Retrieve available jobs
     *
     *
     */
    public function scopeIsAvailable(Builder $query): Builder
    {
        return $query->whereNull('reserved_at')
            ->where('available_at', '<=', $this->currentTime());
    }

    /**
     * Get queue's size
     *
     * @param string|null $queue
     *
     */
    public function size($queue = null): int
    {
        return $this->queuedat($this->getQueue($queue))
            ->count();
    }

    /**
     * Increment the Job attempts
     *
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Mark a job as reserved to avoid being run by another process
     *
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
     *
     *
     * @return this
     */
    public function setReservedAtAttribute(int $value): self
    {
        if (!$value) {
            $this->attributes['reserved_at'] = null;

            return $this;
        }

        $this->attributes['reserved_at'] = (int) $value;

        return $this;
    }
}
