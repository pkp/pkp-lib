<?php

/**
 * @file classes/core/traits/EntityWithParent.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityWithParent
 *
 * @ingroup core_traits
 *
 * @brief A trait for DAO classes that can be used with entities that have a parent entity. For example, a Submission always has a parent Context.
 *
 */

namespace PKP\core\traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\DataObject;

/**
 * @template T of DataObject
 */
trait EntityWithParent
{
    /**
     * Get the parent object ID column name
     */
    abstract public function getParentColumn(): string;

    /**
     * @copydoc EntityDAO::fromRow()
     *
     * @return T
     */
    abstract public function fromRow(object $row): DataObject;

    /**
     * Check if an object exists.
     *
     * Optionally, pass the ID of a parent entity to check if the object
     * exists and is assigned to that parent.
     */
    public function exists(int $id, ?int $parentId = null): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->when($parentId !== null, fn (Builder $query) => $query->where($this->getParentColumn(), $parentId))
            ->exists();
    }

    /**
     * Get an object.
     *
     * Optionally, pass the ID of a parent entity to only get an object
     * if it exists and is assigned to that parent.
     *
     * @return ?T
     */
    public function get(int $id, ?int $parentId = null): ?DataObject
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->when($parentId !== null, fn (Builder $query) => $query->where($this->getParentColumn(), $parentId))
            ->first();
        return $row ? $this->fromRow($row) : null;
    }
}
