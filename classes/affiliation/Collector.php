<?php

/**
 * @file classes/affiliation/Collector.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of affiliations
 */

namespace PKP\affiliation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Affiliation
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    public ?int $count = null;

    public ?int $offset = null;

    public ?array $authorIds = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::getCount() */
    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @copydoc DAO::getIds()
     *
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
     * Filter by single author
     */
    public function filterByAuthorIds(?array $authorIds): self
    {
        $this->authorIds = $authorIds;
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

    /** @copydoc CollectorInterface::getQueryBuilder() */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as a')
            ->select(['a.*', 's.locale AS submission_locale'])
            ->join('authors AS au', 'a.author_id', 'au.author_id')
            ->join('publications as p', 'au.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id');

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        if (!is_null($this->authorIds)) {
            $qb->whereIn('a.author_id', $this->authorIds);
        }

        // Add app-specific query statements
        Hook::call('Affiliation::Collector', [&$qb, $this]);

        return $qb;
    }
}
