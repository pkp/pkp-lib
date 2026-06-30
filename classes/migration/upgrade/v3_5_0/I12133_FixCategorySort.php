<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I12133_FixCategorySort.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12133_FixCategorySort
 *
 * @brief Fix the category sort options (numeric mapping to ASC/DESC)
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I12133_FixCategorySort extends Migration
{
    public $mapping = [
        'title-1' => 'title-ASC',
        'title-2' => 'title-DESC',
        'datePublished-1' => 'datePublished-ASC',
        'datePublished-2' => 'datePublished-DESC',
        'seriesPosition-1' => 'seriesPosition-ASC', // OMP only
        'seriesPosition-2' => 'seriesPosition-DESC', // OMP only
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        foreach ($this->mapping as $from => $to) {
            DB::table('category_settings')
                ->where('setting_name', 'sortOption')
                ->where('setting_value', $from)
                ->update(['setting_value' => $to]);
        }
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        foreach ($this->mapping as $from => $to) {
            DB::table('category_settings')
                ->where('setting_name', 'sortOption')
                ->where('setting_value', $to)
                ->update(['setting_value' => $from]);
        }
    }
}
