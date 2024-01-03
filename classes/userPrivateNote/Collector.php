<?php
/**
 * @file classes/userPrivateNote/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userPrivateNote\Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of UserPrivateNote
 */

namespace PKP\userPrivateNote;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of UserPrivateNote
 */
class Collector implements CollectorInterface
{
    public const ORDERBY_ID = 'id';

    public ?string $orderBy = null;

    /** @var DAO */
    public DAO $dao;

    public ?array $userPrivateNoteIds = null;

    public ?array $contextIds = null;

    public ?array $userIds = null;

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
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter by multiple ids
     */
    public function filterByUserPrivateNoteIds(?array $ids): self
    {
        $this->userPrivateNoteIds = $ids;
        return $this;
    }

    /**
     * Filter by context IDs
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by user ids
     */
    public function filterByUserIds(?array $userIds): self
    {
        $this->userIds = $userIds;
        return $this;
    }

    /**
     * Include orderBy columns to the collector query
     */
    public function orderBy(?string $orderBy): self
    {
        $this->orderBy = $orderBy;
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
     * @hook UserGroup::Collector [[&$q, $this]]
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table('user_private_notes as upn')
            ->select('upn.*');

        if (isset($this->userPrivateNoteIds)) {
            $q->whereIn('upn.user_private_note_id', $this->userPrivateNoteIds);
        }

        if (isset($this->contextIds)) {
            $q->whereIn('upn.context_id', $this->contextIds);
        }

        if (isset($this->userIds)) {
            $q->whereIn('upn.user_id', $this->userIds);
        }

        if (isset($this->count)) {
            $q->limit($this->count);
        }

        if (isset($this->offset)) {
            $q->offset($this->offset);
        }

        if ($this->orderBy == self::ORDERBY_ID) {
            $q->orderBy('upn.user_private_note_id');
        }

        // Add app-specific query statements
        Hook::call('UserPrivateNote::Collector', [&$q, $this]);

        return $q;
    }
}
