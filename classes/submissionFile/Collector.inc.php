<?php
/**
 * @file classes/submissionFile/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submissionFile
 *
 * @brief A helper class to configure a Query Builder to get a collection of submission files
 */

namespace PKP\submissionFile;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $contextIds = null;

    /** @var array|null */
    public $submissionIds = null;

    /** @var int */
    public $count;

    /** @var int */
    public $offset;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Filter submission files by one or more contexts
     */
    public function filterByContextIds(array $contextIds = []): self
    {
        $this->contextIds = $contextIds;

        return $this;
    }

    /**
     * Filter submission files by one or more submission_id values
     */
    public function filterBySubmissionIds(array $submissionIds = []): self
    {
        $this->submissionIds = $submissionIds;

        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): self
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

        if (is_array($this->contextIds)) {
            $qb->whereIn('a.assoc_id', $this->contextIds);
        }

        if (is_array($this->submissionIds)) {
            $qb->whereIn('a.submission_id', $this->submissionIds);
        }

        $qb->orderBy('a.created_at', 'desc');
        $qb->groupBy('a.submission_id');

        if (!empty($this->count)) {
            $qb->limit($this->count);
        }

        if (!empty($this->offset)) {
            $qb->offset($this->count);
        }

        HookRegistry::call('SubmissionFile::Collector', [&$qb, $this]);

        return $qb;
    }
}
