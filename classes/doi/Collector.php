<?php
/**
 * @file classes/doi/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class doi
 *
 * @brief A helper class to configure a Query Builder to get a collection of DOI
 */

namespace PKP\doi;

use APP\doi\DAO;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    public ?array $contextIds = null;

    public ?int $count = 30;

    public int $offset = 0;

    public ?array $statuses = null;

    public ?string $identifier = null;

    public function __construct(\APP\doi\DAO $dao)
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
     * Filter DOI by one or more contexts
     */
    public function filterByContextIds(array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by one or more of the DOI_STATUS_* constants
     */
    public function filterByStatus(array $statuses): self
    {
        $this->statuses = $statuses;
        return $this;
    }

    /**
     * Filter by actual identifier (DOI) or the DOI object
     */
    public function filterByIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
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
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc collectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table($this->dao->table, 'd');

        $q->when($this->contextIds != null, function (Builder $q) {
            $q->whereIn('d.context_id', $this->contextIds);
        });

        $q->when($this->statuses != null, function (Builder $q) {
            $q->whereIn('d.status', $this->statuses);
        });

        $q->when($this->identifier !== null, function (Builder $q) {
            $q->where('d.doi', '=', $this->identifier);
        });

        if (!empty($this->count)) {
            $q->limit($this->count);
        }

        if (!empty($this->offset)) {
            $q->offset($this->count);
        }

        Hook::call('Doi::Collector', [&$q, $this]);

        return $q;
    }
}
