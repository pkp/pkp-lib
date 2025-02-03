<?php

/**
 * @file classes/citation/Collector.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Citation
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    public ?int $count = null;

    public ?int $offset = null;

    /** @var int[]|null */
    public ?array $publicationIds = null;

    public ?string $searchPhrase = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
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
     * Filter by publications
     */
    public function filterByPublicationIds(?array $publicationIds): self
    {
        $this->publicationIds = $publicationIds;
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
        $qb = DB::table($this->dao->table . ' as a')
            ->select('a.*');

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        if (!is_null($this->publicationIds)) {
            $qb->whereIn('a.publication_id', $this->publicationIds);
        }

        // Add app-specific query statements
        Hook::call('Citation::Collector', [&$qb, $this]);

        return $qb;
    }
}
