<?php

declare(strict_types=1);

namespace PKP\Domains\Jobs\Traits;

trait Worker
{
    /**
     * Job's worker delay configuration
     *
     * @var int
     */
    protected $delay = 0;

    /**
     * Job's worker allowed memory configuration
     *
     * @var int
     */
    protected $allowedMemory = 128;

    /**
     * Job's worker timeout configuration
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Job's worker sleep configuration
     *
     * @var int
     */
    protected $sleep = 3;

    /**
     * Job's worker force configuration
     *
     * @var int
     */
    protected $forceFlag = false;

    /**
     * Job's worker stopWhenEmpty configuration
     *
     * @var int
     */
    protected $stopWhenEmptyFlag = true;

    public function setDelay(int $value): self
    {
        $this->delay = $value;

        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function setAllowedMemory(int $value): self
    {
        $this->allowedMemory = $value;

        return $this;
    }

    public function getAllowedMemory(): int
    {
        return $this->allowedMemory;
    }

    public function setTimeout(int $value): self
    {
        $this->timeout = $value;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setSleep(int $value): self
    {
        $this->sleep = $value;

        return $this;
    }

    public function getSleep(): int
    {
        return $this->sleep;
    }

    public function setForceFlag(bool $force = false): self
    {
        $this->forceFlag = $force;

        return $this;
    }

    public function getForceFlag(): bool
    {
        return $this->forceFlag;
    }

    public function setStopWhenEmptyFlag(bool $stopWhenEmptyFlag = false): self
    {
        $this->stopWhenEmptyFlag = $stopWhenEmptyFlag;

        return $this;
    }

    public function getStopWhenEmptyFlag(): bool
    {
        return $this->stopWhenEmptyFlag;
    }
}
