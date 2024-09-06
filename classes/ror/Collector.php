<?php
/**
 * @file classes/ror/Collector.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
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
    public ?string $searchPhrase = null;
    public ?int $count = null;
    public ?int $offset = null;

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
        $qb = DB::table($this->dao->table . ' as r')->select('r.*');

        if ($this->searchPhrase !== null) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                foreach ($words as $word) {
                    $word = addcslashes($word, '%_');
                    $qb->where(function ($qb) use ($word) {
                        $qb->whereIn('r.ror_id', function ($qb) use ($word) {
                            $qb->select('rss.ror_id')
                                ->from($this->dao->settingsTable . ' as rss')
                                ->where('rss.setting_name', '=', 'name')
                                ->where(DB::raw('lower(rss.setting_value)'), 'LIKE', DB::raw("lower('%{$word}%')"));
                        })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where('r.ror', 'like', '%'. $word . '%');
                            });
                    });
                }
            }
        }

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        return $qb;
    }
}
