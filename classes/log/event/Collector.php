<?php

/**
 * @file classes/log/event/Collector.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of log entries
 */

namespace PKP\log\event;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

class Collector implements CollectorInterface
{
    public DAO $dao;
    protected ?int $assocType = null;
    protected ?array $assocIds = null;
    protected ?array $userIds = null;
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
     *
     * @param null|int $assocType One of the Application::ASSOC_TYPE_ constants
     * @param null|array $assocIds Match for the specified assoc type
     */
    public function filterByAssoc(?int $assocType, ?array $assocIds = null): self
    {
        $this->assocType = $assocType;
        $this->assocIds = $assocIds;
        return $this;
    }

    public function filterByUserIds(?array $userIds): self
    {
        $this->userIds = $userIds;
        return $this;
    }

    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @inheritDoc
     *
     * @hook EventLog::Collector::getQueryBuilder [[&$q, $this]]
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table($this->dao->table . ' as e')
            ->select(['e.*'])
            ->when(!is_null($this->assocType), function (Builder $q) {
                return $q->where('assoc_type', $this->assocType);
            })
            ->when(!is_null($this->assocIds), function (Builder $q) {
                return $q->whereIn('assoc_id', $this->assocIds);
            })
            ->when(!is_null($this->userIds), function (Builder $q) {
                return $q->whereIn('user_id', $this->userIds);
            })
            ->orderBy('date_logged', 'desc')
            ->when(!is_null($this->count), function (Builder $q) {
                return $q->limit($this->count);
            })
            ->when(!is_null($this->count) && !is_null($this->offset), function (Builder $q) {
                return $q->offset($this->offset);
            });

        Hook::call('EventLog::Collector::getQueryBuilder', [&$q, $this]);

        return $q;
    }
}
