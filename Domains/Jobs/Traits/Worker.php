<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Traits/Worker.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Worker
 * @ingroup domains
 *
 * @brief Worker trait for Jobs model
 */

namespace PKP\Domains\Jobs\Traits;

trait Worker
{
    /**
     * Job's worker delay configuration, as the number of seconds before a released job will be available.
     *
     * @var int
     */
    protected $delay = 0;

    /**
     * Job's worker allowed memory configuration, as the maximum amount of RAM (in megabytes) the worker may consume.
     *
     * @var int
     */
    protected $allowedMemory = 128;

    /**
     * Job's worker timeout configuration, as the maximum number of seconds a child worker may run.
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Job's worker sleep configuration, as the number of seconds to wait in between polling the queue.
     *
     * @var int
     */
    protected $sleep = 3;

    /**
     * Job's worker force configuration, indicates if the worker should run even in Laravel's maintenance mode.
     *
     * @var bool
     */
    protected $forceFlag = false;

    /**
     * Job's worker stopWhenEmpty configuration, indicates if the worker should stop when queue is empty.
     *
     * @var bool
     */
    protected $stopWhenEmptyFlag = true;

    /**
     * The number of seconds before a released job will be available.
     */
    public function setDelay(int $value): self
    {
        $this->delay = $value;

        return $this;
    }

    /**
     * Get the Job's delay value
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * The maximum amount of RAM the worker may consume.
     */
    public function setAllowedMemory(int $value): self
    {
        $this->allowedMemory = $value;

        return $this;
    }

    /**
     * Get Job's allowed memory value
     */
    public function getAllowedMemory(): int
    {
        return $this->allowedMemory;
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
     * Indicates if the worker should run in maintenance mode.
     */
    public function setForceFlag(bool $value = false): self
    {
        $this->forceFlag = $value;

        return $this;
    }

    /**
     * Get Job's force flag value
     */
    public function getForceFlag(): bool
    {
        return $this->forceFlag;
    }

    /**
     * Indicates if the worker should stop when queue is empty.
     */
    public function setStopWhenEmptyFlag(bool $value = false): self
    {
        $this->stopWhenEmptyFlag = $value;

        return $this;
    }

    /**
     * Get Job's stop when empty flag value
     */
    public function getStopWhenEmptyFlag(): bool
    {
        return $this->stopWhenEmptyFlag;
    }
}
