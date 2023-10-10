<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8992_FixEmptyUrlPaths.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8992_FixEmptyUrlPaths
 *
 * @brief Standardize the url column to hold NULL instead of NULL/empty string.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

abstract class I8992_FixEmptyUrlPaths extends \PKP\migration\Migration
{
    /**
     * Retrieve the tables and fields to update
     * @return string[]
     */
    protected function getFieldset(): array
    {
        return [['publications', 'url_path']];
    }

    /**
     * Run the migration
     */
    public function up(): void
    {
        foreach ($this->getFieldset() as [$table, $column]) {
            DB::table($table)->whereRaw("TRIM({$column}) = ''")->update([$column => null]);
        }
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
