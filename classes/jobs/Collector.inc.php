<?php
/**
 * @file classes/jobs/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class jobs
 *
 * @brief A helper class to configure a Query Builder to get a collection of jobs
 */

namespace PKP\jobs;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var null|array get jobs associated with one or more queues */
    protected $queues = null;

    /** @var null|array get jobs with one or more attempts */
    protected $attempts = null;

    /** @var null|int */
    public $count = null;

    /** @var null|int */
    public $offset = null;

    /** @var null|boolean Filtering the jobs by empty queue value */
    public $withEmptyQueue = true;

    /** @var null|boolean Retrieve jobs reserved?*/
    public $withReserved = true;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Filtering by queues
     */
    public function filterByQueues(?array $queues): self
    {
        $this->queues = $queues;

        return $this;
    }

    /**
     * Set attempts filter
     */
    public function filterByAttempts(?array $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Filter jobs with empty queue
     */
    public function filterWithEmptyQueue(?bool $emptyQueue = true): self
    {
        $this->withEmptyQueue = $emptyQueue;

        return $this;
    }

    public function filterWithReserved(?bool $reserved = true): self
    {
        $this->withReserved = $reserved;

        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as jb')
            ->select(['jb.*']);

        if ($this->withEmptyQueue !== null && !$this->withEmptyQueue) {
            $qb->whereNotNull('queue');
        }

        if ($this->withReserved !== null && !$this->withReserved) {
            $qb->whereNull('reserved_at');
        }

        if ($this->queues !== null) {
            $qb->whereIn('jb.queue', $this->queues);
        }

        if ($this->attempts !== null) {
            $qb->whereIn('jb.attempts', $this->attempts);
        }

        $qb->orderBy('jb.created_at', 'desc');

        if ($this->count !== null) {
            $qb->limit($this->count);
        }

        if ($this->offset !== null) {
            $qb->offset($this->offset);
        }

        HookRegistry::call('Jobs::Collector::getQueryBuilder', [&$qb, $this]);

        return $qb;
    }
}
