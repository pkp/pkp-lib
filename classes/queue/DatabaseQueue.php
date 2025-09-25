<?php

/**
 * @file classes/queue/DatabaseQueue.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DatabaseQueue
 *
 * @brief Override the default database queue implementation to account for context-aware job processing.
 */

namespace PKP\queue;

use Illuminate\Queue\DatabaseQueue as IlluminateDatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use PKP\queue\JobRunner;

class DatabaseQueue extends IlluminateDatabaseQueue
{
    /**
     * Indicates if the queue is context-aware and should consider this when picking jobs.
     * By default it is not context-aware.
     */
    protected bool $contextAware = false;

    /**
     * The context id to use for context-aware job picking
     */
    protected ?int $contextId = null;
    
    /**
     * Enable context-aware job picking.
     */
    public function enableJobInContextAwareMode(): self
    {
        $this->contextAware = true;

        return $this;
    }

    /**
     * Set the context ID for context-aware job picking.
     */
    public function setContextId(?int $contextId): self
    {
        $this->contextId = $contextId;

        return $this;
    }

    /**
     * Pop the next job off of the queue.
     * Override the default implementation to account for context-aware job picking.
     * @see \Illuminate\Queue\DatabaseQueue::pop()
     *
     * @param  string|null  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     *
     * @throws \Throwable
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        // Determine the job popping method to use considering the context awareness
        $jobPoppingMethod = $this->contextAware
            ? 'getNextAvailableContextAwareJob'
            : 'getNextAvailableJob';

        return $this->database->transaction(function () use ($queue, $jobPoppingMethod) {
            if ($job = $this->{$jobPoppingMethod}($queue)) {
                return $this->marshalJob($queue, $job);
            }
        });
    }

    /**
     * Get the next available job for the queue which is context aware e.g. match the following conditions
     *  - payload->context_id match the prop contextId
     *  - or payload->context_id is NULL
     *  - or payload->context_id is not present
     *
     * @param  string|null  $queue
     * @return \Illuminate\Queue\Jobs\DatabaseJobRecord|null
     */
    protected function getNextAvailableContextAwareJob($queue)
    {
        $jobPickQuery = $this->database->table($this->table)
                    ->lock($this->getLockForPopping())
                    ->where('queue', $this->getQueue($queue))
                    ->where(function ($query) {
                        $this->isAvailable($query);
                        $this->isReservedButExpired($query);
                    });

        $job =  JobRunner::applyJobContextAwareFilter($jobPickQuery, $this->contextId)->orderBy('id', 'asc')->first();

        return $job ? new DatabaseJobRecord((object) $job) : null;
    }
}
