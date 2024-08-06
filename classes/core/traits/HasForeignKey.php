<?php

/**
 * @file classes/core/traits/HasForeignKey.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasForeignKey
 *
 * @brief A trait to determine the existence foreign key in a table
 *
 */

namespace PKP\core\traits;

use Illuminate\Support\Facades\Schema;

trait HasForeignKey
{
    public function hasForeignKey(string $tableName, string $keyName): bool
    {
        return collect(Schema::getForeignKeys($tableName))
            ->pluck('name')
            ->contains($keyName);
    }
}
