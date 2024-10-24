<?php
/**
 * @file classes/affiliation/Collector.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of rors
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
    /** @var DAO */
    public DAO $dao;

    public ?int $count = null;

    public ?int $offset = null;

    /** @var int[]|null */
    public ?array $authorIds = null;

    /** Get affiliations with a name */
    public ?string $name = null;

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
     * Filter by authors
     */
    public function filterByAuthorIds(?array $authorIds): self
    {
        $this->authorIds = $authorIds;
        return $this;
    }

    /**
     * Filter by affiliation name.
     *
     * @param string|null $name
     *
     * @return $this
     */
    public function filterByName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Filter rors by those matching a search query
     */
    public function searchPhrase(?string $phrase): self
    {
        $this->searchPhrase = $phrase;
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

        if (!is_null($this->authorIds)) {
            $qb->whereIn('a.author_id', $this->authorIds);
        }

        $qb->when($this->name !== null, function (Builder $qb) {
            $qb->whereIn('a.author_affiliation_id', function (Builder $qb) {
                $qb->select('author_affiliation_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'name')
                    ->where('setting_value', $this->name);
            });
        });

        // Add app-specific query statements
        Hook::call('Affiliation::Collector', [&$qb, $this]);

        return $qb;
    }
}
