<?php
/**
 * @file classes/section/Collector.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class section
 *
 * @brief A helper class to configure a Query Builder to get a collection of sections
 */

namespace PKP\section;

use APP\section\DAO;
use APP\submission\Submission;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;

abstract class Collector implements CollectorInterface
{
    public DAO $dao;
    //public string $parentColumnName;
    public ?array $contextIds = null;
    public ?array $titles = null;
    public ?array $abbrevs = null;
    public bool $submittableOnly = false;
    public bool $activeOnly = false;
    public bool $withPublicationsOnly = false;
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

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter sections by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): \APP\section\Collector
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter sections by one or more titles
     */
    public function filterByTitles(?array $titles): \APP\section\Collector
    {
        $this->titles = $titles;
        return $this;
    }

    /**
     * Filter sections by one or more abbreviations
     */
    public function filterByAbbrevs(?array $abbrevs): \APP\section\Collector
    {
        $this->abbrevs = $abbrevs;
        return $this;
    }

    /**
     * Filter only submittable sections
     */
    public function submittableOnly(bool $submittableOnly = true): \APP\section\Collector
    {
        $this->submittableOnly = $submittableOnly;
        return $this;
    }

    /**
     * Filter only submittable sections
     */
    public function activeOnly(bool $activeOnly = true): \APP\section\Collector
    {
        $this->activeOnly = $activeOnly;
        return $this;
    }

    /**
     * Filter only series that contain publications
     */
    public function withPublicationsOnly(bool $withPublicationsOnly = true): Collector
    {
        $this->withPublicationsOnly = $withPublicationsOnly;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): \APP\section\Collector
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): \APP\section\Collector
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table, 's')->select('s.*');

        if (!is_null($this->contextIds)) {
            $qb->whereIn('s.' . $this->dao->getParentColumn(), $this->contextIds);
        }

        if (!is_null($this->titles) || !is_null($this->abbrevs)) {
            $qb->join($this->dao->settingsTable . ' ss', 'ss.' . $this->dao->primaryKeyColumn, '=', 's.' . $this->dao->primaryKeyColumn);
            if (!is_null($this->titles)) {
                $qb->where('ss.setting_name', 'title')
                    ->whereIn('ss.setting_value', $this->titles);
            }
            if (!is_null($this->abbrevs)) {
                $qb->where('setting_name', 'abbrev')
                    ->whereIn('setting_value', $this->abbrevs);
            }
        }

        if ($this->submittableOnly) {
            $qb->where('s.editor_restricted', 0)->where('s.is_inactive', 0);
        }

        if ($this->activeOnly) {
            $qb->where('s.is_inactive', 0);
        }

        if ($this->withPublicationsOnly) {
            $publicationsCountSql = '(SELECT COUNT(*) FROM publications AS p WHERE p.series_id = s.series_id AND p.status = ' . Submission::STATUS_PUBLISHED . ')';
            $qb->where(DB::raw($publicationsCountSql), '>', 0);
        }

        $qb->orderBy('s.seq');

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        return $qb;
    }
}
