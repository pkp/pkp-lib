<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I5887_AddAuthorSettingsNameValueIndex.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5887_AddAuthorSettingsNameValueIndex
 *
 * @brief Index author_settings by setting name and value.
 *
 * Author names are looked up by value rather than by author: given a name,
 * find the authors called that (Author\Collector::filterByName, used by the
 * Recommend Articles by Author plugin and by author queries in the API).
 * The only indexes on the table are on author_id and the (author_id, locale,
 * setting_name) unique key, so that lookup reads the whole table.
 *
 * This mirrors the index publication_settings already carries.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class I5887_AddAuthorSettingsNameValueIndex extends Migration
{
    private const INDEX = 'author_settings_name_value';

    /**
     * Run the migration.
     */
    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        match (DB::getDriverName()) {
            'mysql', 'mariadb' =>
                DB::unprepared('CREATE INDEX ' . self::INDEX . ' ON author_settings (setting_name(50), setting_value(150))'),
            'pgsql' =>
                DB::unprepared('CREATE INDEX ' . self::INDEX . " ON author_settings (setting_name, setting_value) WHERE setting_name IN ('givenName', 'familyName', 'orcid')")
        };
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    private function indexExists(): bool
    {
        return Schema::hasIndex('author_settings', self::INDEX);
    }
}
