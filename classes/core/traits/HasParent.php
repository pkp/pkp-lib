<?php

/**
 * @file classes/core/traits/HasParent.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasParent
 * @ingroup core_traits
 *
 * @brief HasParent trait.
 *
 */

namespace PKP\core\traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\DataObject;

trait HasParent
{
    /**
     * Get the parent object ID column name
     */
    abstract public function getParentColumn(): string;

    /**
     * Check if an object exists with this ID, and optional parent ID
     */
    public function exists(int $id, int $parentId = null): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->when($parentId !== null, fn (Builder $query, int $parentId) => $query->where($this->getParentColumn(), $parentId))
            ->exists();
    }

    /**
     * Get an object by its ID, and optionaly by its parent ID
     */
    public function get(int $id, int $parentId = null): ?DataObject
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->when($parentId !== null, fn (Builder $query, int $parentId) => $query->where($this->getParentColumn(), $parentId))
            ->first();
        return $row ? $this->fromRow($row) : null;
    }
}
