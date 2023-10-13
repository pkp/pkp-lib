<?php
/**
 * @file classes/publication/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\publication;

use APP\publication\Publication;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Publication
 */
class Collector implements CollectorInterface
{
    public \APP\publication\DAO $dao;
    public ?array $contextIds;
    public ?array $submissionIds;
    public ?array $doiIds = null;
    public ?int $count;
    public ?int $offset;

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
     * Filter by contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by submissions
     */
    public function filterBySubmissionIds(?array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    public function filterByDoiIds(?array $doiIds): self
    {
        $this->doiIds = $doiIds;
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
     *
     * @hook Publication::Collector [[&$qb, $this]]
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table('publications as p')
            ->select(['p.*']);

        if (isset($this->contextIds)) {
            $qb->join('submissions as s', 'p.submission_id', '=', 's.submission_id');
            $qb->whereIn('s.context_id', $this->contextIds);
        }

        if (isset($this->submissionIds)) {
            $qb->whereIn('p.submission_id', $this->submissionIds);
        }

        $qb->when($this->doiIds !== null, function (Builder $qb) {
            $qb->whereIn('p.doi_id', $this->doiIds);
        });

        if (isset($this->count)) {
            $qb->limit($this->count);
        }
        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        $qb->orderBy('p.version', 'asc');

        // Add app-specific query statements
        Hook::call('Publication::Collector', [&$qb, $this]);

        return $qb;
    }
}
