<?php
/**
 * @file classes/publication/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class publication
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\publication;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds;
    public ?array $submissionIds;
    public ?int $count;
    public ?int $offset;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
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
        $qb = DB::table('publications as p');

        if (isset($this->contextIds)) {
            $qb->join('submissions as s', 'p.submission_id', '=', 's.submission_id');
            $qb->whereIn('s.context_id', $this->contextIds);
        }

        if (isset($this->submissionIds)) {
            $qb->whereIn('p.submission_id', $this->submissionIds);
        }

        if (isset($this->count)) {
            $qb->limit($this->count);
        }
        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        $qb->orderBy('p.version', 'asc');

        // Add app-specific query statements
        Hook::call('Publication::Collector', [&$qb, $this]);

        $qb->select(['p.*']);

        return $qb;
    }
}
