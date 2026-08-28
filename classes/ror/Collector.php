<?php

/**
 * @file classes/ror/Collector.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of rors
 */

namespace PKP\ror;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;

/**
 * @template T of Ror
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    /** Get rors with a search phrase */
    public ?string $searchPhrase = null;

    /** Get rors with a name */
    public ?string $name = null;

    /** Get rors with is active */
    public ?int $isActive = null;

    /** Get rors with given ROR */
    public ?string $ror = null;

    public ?array $authorAffiliationIds = null;

    public ?int $count = null;

    public ?int $offset = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc: DAO::getCount() */
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
     * Filter rors by those matching a search query
     */
    public function filterBySearchPhrase(?string $searchPhrase): self
    {
        $this->searchPhrase = $searchPhrase;
        return $this;
    }

    /**
     * Filter rors by isActive
     */
    public function filterByIsActive(?bool $isActive): self
    {
        $this->isActive = ($isActive) ? ROR::STATUS_ACTIVE : ROR::STATUS_INACTIVE;
        return $this;
    }

    public function filterByAuthorAffiliationIds(?array $authorAffiliationIds): self
    {
        $this->authorAffiliationIds = $authorAffiliationIds;
        return $this;
    }

    /**
     * Filter rors by those matching given ror
     */
    public function filterByRor(?string $ror): self
    {
        $this->ror = $ror;
        return $this;
    }

    /**
     * Filter by ror name.
     */
    public function filterByName(?string $name): self
    {
        $this->name = $name;
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

    /**@copydoc CollectorInterface::getQueryBuilder() */
    public function getQueryBuilder(): Builder
    {
        $collector = $this;
        $qb = DB::table($this->dao->table . ' as r')
            ->select('r.*')
            ->distinct()
            ->when($this->searchPhrase !== null, function ($qb) use ($collector) {
                foreach (explode(' ', $collector->searchPhrase) as $word) {
                    $qb->where('r.search_phrase', 'like', '%' . addcslashes($word, '%_') . '%');
                }
            })
            ->when($this->name !== null, function (Builder $qb) {
                $qb->whereIn('r.ror_id', function (Builder $qb) {
                    $qb->select('rs.ror_id')
                        ->from($this->dao->settingsTable . ' as rs')
                        ->where('rs.setting_name', '=', 'name')
                        ->where('rs.setting_value', '=', $this->name);
                });
            })
            ->when($this->authorAffiliationIds !== null, function ($q) {
                $q->whereIn('r.ror', DB::table('author_affiliations')->select(['ror'])->whereIn('author_affiliation_id', $this->authorAffiliationIds));
            })
            ->when($this->isActive !== null, fn ($q) => $q->where('r.is_active', $this->isActive))
            ->when($this->ror !== null, fn ($q) => $q->where('r.ror', $this->ror))
            ->when(!is_null($this->count), fn ($q) => $q->limit($this->count))
            ->when(!is_null($this->offset), fn ($q) => $q->offset($this->offset));

        return $qb;
    }
}
