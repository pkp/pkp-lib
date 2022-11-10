<?php
declare(strict_types=1);

/**
 * @file classes/queue/WorkerConfiguration.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkerConfiguration
 *
 * @brief Laravel worker configuration manager for managing worker options
 */

namespace PKP\queue;

use Exception;

class WorkerConfiguration
{
    /**
     * The name of the worker.
     *
     * @var string
     */
    protected $name = 'default';

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @var int
     */
    protected $backoff = 0;

    /**
     * Job's worker allowed memory configuration, as the maximum amount of RAM (in megabytes) the worker may consume.
     *
     * @var int
     */
    protected $memory = 128;

    /**
     * Job's worker timeout configuration, as the maximum number of seconds a child worker may run.
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Job's worker sleep configuration, as the number of seconds to wait/sleep in between polling the queue.
     *
     * @var int
     */
    protected $sleep = 3;

    /**
     * Number of maximum times to attempt a job before logging it failed
     *
     * @var int
     */
    protected $maxTries = 3;

    /**
     * Job's worker force configuration, indicates if the worker should run even in Laravel's maintenance mode.
     *
     * @var bool
     */
    protected $force = false;

    /**
     * Job's worker stopWhenEmpty configuration, indicates if the worker should stop when queue is empty.
     *
     * @var bool
     */
    protected $stopWhenEmpty = false;

    /**
     * The number of jobs to process before stopping
     *
     * @var int
     */
    protected $maxJobs = 0;


    /**
     * The maximum number of seconds the worker should run
     *
     * @var int
     */
    protected $maxTime = 0;


    /**
     * Number of seconds to rest between jobs
     *
     * @var int
     */
    protected $rest = 0;

    /**
     * Set the configurations
     *
     * @param array $options
     * @return self
     */
    public static function withOptions(array $options = []): self
    {
        $self = new static;

        collect($options)
            ->each(fn($value, $option) => method_exists($self, 'set' . ucfirst($option)) 
                ? $self->{'set' . ucfirst($option)}($value) 
                : throw new Exception(sprintf('Unknown option "%s"', $option))
            );
        
        return $self;
    }

    /**
     * Get the worker options
     *
     * @return array
     */
    public function getWorkerOptions(): array 
    {
        return [
            'name'          => $this->getName(),
            'backoff'       => $this->getBackoff(),
            'memory'        => $this->getMemory(),
            'timeout'       => $this->getTimeout(),
            'sleep'         => $this->getSleep(),
            'maxTries'      => $this->getMaxTries(),
            'force'         => $this->getForce(),
            'stopWhenEmpty' => $this->getStopWhenEmpty(),
            'maxJobs'       => $this->getMaxJobs(),
            'maxTime'       => $this->getMaxTime(),
            'rest'          => $this->getRest(),
        ];
    }

    /**
     * Set the worker name
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the worker name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * The number of seconds to wait before retrying the job
     */
    public function setBackoff(int $value): self
    {
        $this->backoff = $value;

        return $this;
    }

    /**
     * Get the number of seconds to wait before retrying the job
     */
    public function getBackoff(): int
    {
        return $this->backoff;
    }

    /**
     * The maximum amount of RAM(in megabytes) the worker may consume.
     */
    public function setMemory(int $value): self
    {
        $this->memory = $value;

        return $this;
    }

    /**
     * Get Job's allowed memory value(in megabytes)
     */
    public function getMemory(): int
    {
        return $this->memory;
    }

    /**
     * The maximum number of seconds a child worker may run.
     */
    public function setTimeout(int $value): self
    {
        $this->timeout = $value;

        return $this;
    }

    /**
     * Get Job's timeout value
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * The number of seconds to wait in between polling the queue.
     */
    public function setSleep(int $value): self
    {
        $this->sleep = $value;

        return $this;
    }

    /**
     * Get Job's sleep value
     */
    public function getSleep(): int
    {
        return $this->sleep;
    }

    /**
     * Set the Job's max attempts
     */
    public function setMaxTries(int $maxTries): self
    {
        $this->maxTries = $maxTries;

        return $this;
    }

    /**
     * Get the Job's max attempts
     */
    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    /**
     * Indicates if the worker should run in maintenance mode.
     */
    public function setForce(bool $value = false): self
    {
        $this->force = $value;

        return $this;
    }

    /**
     * Get Job's force flag value
     */
    public function getForce(): bool
    {
        return $this->force;
    }

    /**
     * Indicates if the worker should stop when queue is empty.
     */
    public function setStopWhenEmpty(bool $value = false): self
    {
        $this->stopWhenEmpty = $value;

        return $this;
    }

    /**
     * Get Job's stop when empty settings value
     */
    public function getStopWhenEmpty(): bool
    {
        return $this->stopWhenEmpty;
    }

    /**
     * Set max number of jobs to process before stopping
     */
    public function setMaxJobs(int $maxJobs): self
    {
        $this->maxJobs = $maxJobs;

        return $this;
    }

    /**
     * Get max number of jobs to process before stopping
     */
    public function getMaxJobs(): int
    {
        return $this->maxJobs;
    }

    /**
     * Set maximum number of seconds the worker should run
     */
    public function setMaxTime(int $maxTime): self
    {
        $this->maxTime = $maxTime;

        return $this;
    }

    /**
     * Get maximum number of seconds the worker should run
     */
    public function getMaxTime(): int
    {
        return $this->maxTime;
    }

    /**
     * Set number of seconds to rest between jobs
     */
    public function setRest(int $rest): self
    {
        $this->rest = $rest;

        return $this;
    }

    /**
     * Get number of seconds to rest between jobs
     */
    public function getRest(): int
    {
        return $this->rest;
    }
}