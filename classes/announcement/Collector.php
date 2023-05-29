<?php
/**
 * @file classes/announcement/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\announcement;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Announcement
 */
class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds = null;
    public ?string $isActive = null;
    public ?string $searchPhrase = null;
    public ?array $typeIds = null;
    public ?int $count = null;
    public ?int $offset = null;

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
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /**
     * @copydoc DAO::getMany()
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter announcements by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter announcements by those that have not expired
     *
     * @param string $date Optionally filter announcements by those
     *   not expired until $date (YYYY-MM-DD).
     */
    public function filterByActive(string $date = ''): self
    {
        $this->isActive = empty($date)
            ? Core::getCurrentDate()
            : $date;
        return $this;
    }

    /**
     * Filter announcements by one or more announcement types
     */
    public function filterByTypeIds(array $typeIds): self
    {
        $this->typeIds = $typeIds;
        return $this;
    }

    /**
     * Filter announcements by those matching a search query
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
            ->select(['a.*']);

        if (isset($this->contextIds)) {
            $qb->whereIn('a.assoc_id', $this->contextIds);
        }

        if (isset($this->typeIds)) {
            $qb->whereIn('a.type_id', $this->typeIds);
        }

        if (isset($this->isActive)) {
            $qb->where('date_expire', '<=', $this->isActive)
                ->orWhereNull('date_expire');
        }

        if ($this->searchPhrase !== null) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                $qb->whereIn('a.announcement_id', function ($query) use ($words) {
                    $query->select('announcement_id')->from($this->dao->settingsTable);
                    foreach ($words as $word) {
                        $word = strtolower(addcslashes($word, '%_'));
                        $query->where(function ($query) use ($word) {
                            $query->where(function ($query) use ($word) {
                                $query->where('setting_name', 'title');
                                $query->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                            })
                                ->orWhere(function ($query) use ($word) {
                                    $query->where('setting_name', 'descriptionShort');
                                    $query->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                                })
                                ->orWhere(function ($query) use ($word) {
                                    $query->where('setting_name', 'description');
                                    $query->where(DB::raw('lower(setting_value)'), 'LIKE', "%{$word}%");
                                });
                        });
                    }
                });
            }
        }

        if (isset($this->count)) {
            $qb->limit($this->count);
        }

        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        Hook::call('Announcement::Collector', [&$qb, $this]);

        return $qb;
    }
}
