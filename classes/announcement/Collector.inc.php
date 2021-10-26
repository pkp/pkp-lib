<?php
/**
 * @file classes/announcement/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\announcement;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds;
    public string $isActive;
    public string $searchPhrase = null;
    public ?array $typeIds;
    public ?int $count;
    public ?int $offset;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
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
        $qb = DB::table($this->dao->table . ' as a');

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
                $qb->leftJoin($this->dao->settingsTable . ' as asrch', 'a.announcement_id', '=', 'asrch.announcement_id');
                foreach ($words as $word) {
                    $word = strtolower(addcslashes($word, '%_'));
                    $qb->where(function ($qb) use ($word) {
                        $qb->where(function ($qb) use ($word) {
                            $qb->where('asrch.setting_name', 'title');
                            $qb->where(DB::raw('lower(asrch.setting_value)'), 'LIKE', "%{$word}%");
                        })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where('asrch.setting_name', 'descriptionShort');
                                $qb->where(DB::raw('lower(asrch.setting_value)'), 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where('asrch.setting_name', 'description');
                                $qb->where(DB::raw('lower(asrch.setting_value)'), 'LIKE', "%{$word}%");
                            });
                    });
                }
            }
        }

        $qb->orderBy('a.date_posted', 'desc');
        $qb->groupBy('a.announcement_id');

        if (isset($this->count)) {
            $qb->limit($this->count);
        }

        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        HookRegistry::call('Announcement::Collector', [&$qb, $this]);

        return $qb;
    }
}
