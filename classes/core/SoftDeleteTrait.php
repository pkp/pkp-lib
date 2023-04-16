<?php

/**
 * @file classes/core/SoftDeleteTrait.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SoftDeleteTrait
 *
 * @ingroup core
 *
 * @brief Implements the methods for soft deletion that can be used in entity daos that support it
 */

namespace PKP\core;

use Illuminate\Support\Facades\DB;

trait SoftDeleteTrait
{
    /**
     * Soft delete an object from the database
     */
    protected function _softDelete(DataObject $object): void
    {
        $this->softDeleteById($object->getId());
    }

    /**
     * Soft delete an object from the database by its id
     */
    public function softDeleteById(int $id): void
    {
        DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->update(['deleted_at' => Core::getCurrentDate()]);
    }
}
