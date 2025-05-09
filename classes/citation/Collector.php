<?php

/**
 * @file classes/citation/Collector.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @ingroup citation
 *
 * @brief A helper class to configure a Query Builder to get a collection of citations
 */

namespace PKP\citation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Citation
 */
class Collector implements CollectorInterface
{
    public const ORDERBY_SEQUENCE = 'sequence';

    /** @var string The default orderBy value for authors collector */
    public string $orderBy = self::ORDERBY_SEQUENCE;

    public DAO $dao;

    public ?int $count = null;

    public ?int $offset = null;

    public ?int $publicationId = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @copydoc DAO::getMany()
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter by single publication
     */
    public function filterByPublicationId(?int $publicationId): self
    {
        $this->publicationId = $publicationId;
        return $this;
    }

    /**
     * Include orderBy columns to the collector query
     */
    public function orderBy(?string $orderBy): self
    {
        $this->orderBy = $orderBy;
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
        $qb = DB::table($this->dao->table . ' as c')
            ->select('c.*');

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        if (!is_null($this->publicationId)) {
            $qb->where('c.publication_id', $this->publicationId);
        }

        switch ($this->orderBy) {
            case self::ORDERBY_SEQUENCE:
            default:
                $qb->orderBy('c.seq', 'asc');
                break;
        }

        // Add app-specific query statements
        Hook::call('Citation::Collector', [&$qb, $this]);

        return $qb;
    }
}
