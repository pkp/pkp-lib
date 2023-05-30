<?php
/**
 * @file classes/section/Collector.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of sections
 */

namespace PKP\section;

use APP\section\DAO;
use APP\section\Section;
use APP\submission\Submission;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;

/**
 * @template T of Section
 */
class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds = null;
    public ?array $titles = null;
    public ?array $abbrevs = null;
    public bool $editorOnly = false;
    public bool $excludeInactive = false;
    public bool $withPublished = false;
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
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter sections by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter sections by one or more titles
     */
    public function filterByTitles(?array $titles): self
    {
        $this->titles = $titles;
        return $this;
    }

    /**
     * Filter sections by one or more abbreviations
     */
    public function filterByAbbrevs(?array $abbrevs): self
    {
        $this->abbrevs = $abbrevs;
        return $this;
    }

    /**
     * Only include sections that all users can submit to
     */
    public function excludeEditorOnly(bool $editorOnly = true): self
    {
        $this->editorOnly = $editorOnly;
        return $this;
    }

    /**
     * Only include active sections
     */
    public function excludeInactive(bool $excludeInactive = true): self
    {
        $this->excludeInactive = $excludeInactive;
        return $this;
    }

    /**
     * Only include sections that contain published items
     */
    public function withPublished(bool $withPublished = true): self
    {
        $this->withPublished = $withPublished;
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

    public function getQueryBuilder(): Builder
    {
        return DB::table($this->dao->table, 's')->select('s.*')
            ->when(!is_null($this->contextIds), function (Builder $qb) {
                $qb->whereIn('s.' . $this->dao->getParentColumn(), $this->contextIds);
            })
            ->when(!is_null($this->titles) || !is_null($this->abbrevs), function (Builder $qb) {
                $qb->join($this->dao->settingsTable . ' AS ss', 'ss.' . $this->dao->primaryKeyColumn, '=', 's.' . $this->dao->primaryKeyColumn)
                    ->when(!is_null($this->titles), function (Builder $qb) {
                        $qb->where('ss.setting_name', 'title')
                            ->whereIn('ss.setting_value', $this->titles);
                    })
                    ->when(!is_null($this->abbrevs), function (Builder $qb) {
                        $qb->where('setting_name', 'abbrev')
                            ->whereIn('setting_value', $this->abbrevs);
                    });
            })
            ->when($this->editorOnly, function (Builder $qb) {
                $qb->where('s.editor_restricted', 0)
                    ->where('s.is_inactive', 0);
            })
            ->when($this->excludeInactive, function (Builder $qb) {
                $qb->where('s.is_inactive', 0);
            })
            ->when($this->withPublished, function (Builder $qb) {
                $qb->whereExists(function (Builder $qb) {
                    $qb->select('p.*')
                        ->from('publications AS p')
                        ->whereNotNull('p.' . $this->dao->primaryKeyColumn)
                        ->whereColumn('p.' . $this->dao->primaryKeyColumn, '=', 's.' . $this->dao->primaryKeyColumn)
                        ->where('p.status', '=', Submission::STATUS_PUBLISHED);
                });
            })
            ->orderBy('s.seq')
            ->when(!is_null($this->count), function (Builder $qb) {
                $qb->limit($this->count);
            })
            ->when(!is_null($this->offset), function (Builder $qb) {
                $qb->offset($this->offset);
            });
    }
}
