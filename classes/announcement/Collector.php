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

use APP\core\Application;
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
    public const ORDERBY_DATE_POSTED = 'date_posted';
    public const ORDERBY_DATE_EXPIRE = 'date_expire';
    public const ORDER_DIR_ASC = 'ASC';
    public const ORDER_DIR_DESC = 'DESC';
    public const SITE_ONLY = 'site';
    public const SITE_AND_CONTEXTS = 'all';

    public DAO $dao;
    public ?array $contextIds = null;
    public ?string $isActive = null;
    public ?string $searchPhrase = null;
    public ?array $typeIds = null;
    public ?string $includeSite = null;
    public ?int $count = null;
    public ?int $offset = null;
    public string $orderBy = self::ORDERBY_DATE_POSTED;
    public string $orderDirection = self::ORDER_DIR_DESC;

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
     * Include site-level announcements in the results
     */
    public function withSiteAnnouncements(?string $includeMethod = self::SITE_AND_CONTEXTS): self
    {
        $this->includeSite = $includeMethod;
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
     * Order the results
     *
     * Results are ordered by the date posted by default.
     *
     * @param string $sorter One of the self::ORDERBY_ constants
     * @param string $direction One of the self::ORDER_DIR_ constants
     */
    public function orderBy(?string $sorter, string $direction = self::ORDER_DIR_DESC): self
    {
        $this->orderBy = $sorter;
        $this->orderDirection = $direction;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as a')
            ->select(['a.*']);

        if (isset($this->contextIds) && $this->includeSite !== self::SITE_ONLY) {
            $qb->where('a.assoc_type', Application::get()->getContextAssocType());
            $qb->whereIn('a.assoc_id', $this->contextIds);
            if ($this->includeSite === self::SITE_AND_CONTEXTS) {
                $qb->orWhereNull('a.assoc_id');
            }
        } elseif ($this->includeSite === self::SITE_ONLY) {
            $qb->where('a.assoc_type', Application::get()->getContextAssocType());
            $qb->whereNull('a.assoc_id');
        }

        if (isset($this->typeIds)) {
            $qb->whereIn('a.type_id', $this->typeIds);
        }

        $qb->when($this->isActive, fn ($qb) => $qb->where(function ($qb) {
            $qb->where('a.date_expire', '>', $this->isActive)
                ->orWhereNull('a.date_expire');
        }));

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

        $qb->orderByDesc('a.date_posted');

        if (isset($this->count)) {
            $qb->limit($this->count);
        }

        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        if (isset($this->orderBy)) {
            $qb->orderBy('a.' . $this->orderBy, $this->orderDirection);
            // Add a secondary sort by id to catch cases where two
            // announcements share the same date
            if (in_array($this->orderBy, [SELF::ORDERBY_DATE_EXPIRE, SELF::ORDERBY_DATE_POSTED])) {
                $qb->orderBy('a.announcement_id', $this->orderDirection);
            }
        }

        Hook::call('Announcement::Collector', [&$qb, $this]);

        return $qb;
    }
}
