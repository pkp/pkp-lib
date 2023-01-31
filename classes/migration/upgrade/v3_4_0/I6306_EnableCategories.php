<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6306_EnableCategories.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6306_EnableCategories
 * @brief Set the new context setting,`submitWithCategories`, to `true` for
 *   existing journals to preserve the pre-existing behaviour.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

abstract class I6306_EnableCategories extends Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    public function up(): void
    {
        $contextIds = DB::table($this->getContextTable())
            ->get([$this->getContextIdColumn()])
            ->pluck($this->getContextIdColumn());

        if (!$contextIds->count()) {
            return;
        }


        DB::table($this->getContextSettingsTable())->insert(
            $contextIds->map(fn(int $id) => [
                $this->getContextIdColumn() => $id,
                'setting_name' => 'submitWithCategories',
                'setting_value' => '1',
            ])
            ->toArray()
        );
    }

    public function down(): void
    {
        DB::table($this->getContextSettingsTable())
            ->where('setting_name', 'submitWithCategories')
            ->delete();
    }
}
