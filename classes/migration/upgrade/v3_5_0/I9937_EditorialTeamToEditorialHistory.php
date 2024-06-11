<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9937_EditorialTeamToEditorialHistory.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9937_EditorialTeamToEditorialHistory
 *
 * @brief Migrate/rename editorialTeam to editorialHistory context setting and remove Editorial Team navigation menu item.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I9937_EditorialTeamToEditorialHistory extends Migration
{
    abstract protected function getContextSettingsTable(): string;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        DB::table($this->getContextSettingsTable())
            ->where('setting_name', '=', 'editorialTeam')
            ->update(['setting_name' => 'editorialHistory']);

        // Because of the foreign keys constrains it is enough to
        // only remove the entries from the table navigation_menu_items.
        DB::table('navigation_menu_items')
            ->where('type', 'NMI_TYPE_EDITORIAL_TEAM')
            ->delete();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
