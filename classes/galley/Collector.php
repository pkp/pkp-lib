<?php

/**
 * @file classes/galley/Collector.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of galleys
 */

namespace PKP\galley;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Galley
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    public ?array $publicationIds = null;

    public ?array $contextIds = null;

    public ?array $doiIds = null;

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

    public function filterByPublicationIds(?array $publicationIds): self
    {
        $this->publicationIds = $publicationIds;
        return $this;
    }

    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    public function filterByDoiIds(?array $doiIds): self
    {
        $this->doiIds = $doiIds;
        return $this;
    }

    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as g')
            ->select(['g.*'])
            ->when(!is_null($this->publicationIds), function (Builder $qb) {
                $qb->whereIn('g.publication_id', $this->publicationIds);
            })
            ->when(!is_null($this->contextIds), function (Builder $qb) {
                $qb->join('publications as p', 'p.publication_id', '=', 'g.publication_id')
                    ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
                    ->whereIn('s.context_id', $this->contextIds);
            })
            ->when(!is_null($this->doiIds), function (Builder $qb) {
                $qb->whereIn('g.doi_id', $this->doiIds);
            })
            ->orderBy('g.seq', 'asc');

        Hook::call('Galley::Collector', [&$qb, $this]);

        return $qb;
    }
}
