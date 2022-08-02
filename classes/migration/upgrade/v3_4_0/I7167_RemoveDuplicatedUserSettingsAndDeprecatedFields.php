<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7167_RemoveDuplicatedUserSettingsAndDeprecatedFields.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7167_RemoveDuplicatedUserSettingsAndDeprecatedFields
 * @brief Removes duplicated user_settings, as well as the deprecated fields "assoc_id" and "assoc_type".
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7167_RemoveDuplicatedUserSettingsAndDeprecatedFields extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('user_settings', 'assoc_id') && !Schema::hasColumn('user_settings', 'assoc_type')) {
            return;
        }

        // Locates and removes duplicated user_settings
        // The latest code stores settings using assoc_id = 0 and assoc_type = 0. Which means entries using null or anything else are outdated.
        // Note: Old versions (e.g. OJS <= 2.x) made use of these fields to store some settings, but they have been removed years ago, which means they are safe to be discarded.
        if (DB::connection() instanceof MySqlConnection) {
            DB::unprepared(
                "DELETE s
                FROM user_settings s
                -- Locates all duplicated settings (same key fields, except the assoc_type/assoc_id)
                INNER JOIN user_settings duplicated
                    ON s.setting_name = duplicated.setting_name
                    AND s.user_id = duplicated.user_id
                    AND s.locale = duplicated.locale
                    AND (
                        COALESCE(s.assoc_type, -999999) <> COALESCE(duplicated.assoc_type, -999999)
                        OR COALESCE(s.assoc_id, -999999) <> COALESCE(duplicated.assoc_id, -999999)
                    )
                -- Attempts to find a better fitting record among the duplicates (preference is given to the smaller assoc_id/assoc_type values)
                LEFT JOIN user_settings best
                    ON best.setting_name = duplicated.setting_name
                    AND best.user_id = duplicated.user_id
                    AND best.locale = duplicated.locale
                    AND (
                        COALESCE(best.assoc_id, 999999) < COALESCE(duplicated.assoc_id, 999999)
                        OR (
                            COALESCE(best.assoc_id, 999999) = COALESCE(duplicated.assoc_id, 999999)
                            AND COALESCE(best.assoc_type, 999999) < COALESCE(duplicated.assoc_type, 999999)
                        )
                    )
                -- Ensures a better record was found (if not found, it means the current duplicated record is the best and shouldn't be removed)
                WHERE best.user_id IS NOT NULL"
            );
        } else {
            DB::unprepared(
                "DELETE FROM user_settings s
                USING user_settings duplicated
                -- Attempts to find a better fitting record among the duplicates (preference is given to the smaller assoc_id/assoc_type values)
                LEFT JOIN user_settings best
                    ON best.setting_name = duplicated.setting_name
                    AND best.user_id = duplicated.user_id
                    AND best.locale = duplicated.locale
                    AND (
                        COALESCE(best.assoc_id, 999999) < COALESCE(duplicated.assoc_id, 999999)
                        OR (
                            COALESCE(best.assoc_id, 999999) = COALESCE(duplicated.assoc_id, 999999)
                            AND COALESCE(best.assoc_type, 999999) < COALESCE(duplicated.assoc_type, 999999)
                        )
                    )
                -- Locates all duplicated settings (same key fields, except the assoc_type/assoc_id)
                WHERE s.setting_name = duplicated.setting_name
                    AND s.user_id = duplicated.user_id
                    AND s.locale = duplicated.locale
                    AND (
                        COALESCE(s.assoc_type, -999999) <> COALESCE(duplicated.assoc_type, -999999)
                        OR COALESCE(s.assoc_id, -999999) <> COALESCE(duplicated.assoc_id, -999999)
                    )
                    -- Ensures a better record was found (if not found, it means the current duplicated record is the best and shouldn't be removed)
                    AND best.user_id IS NOT NULL"
            );
        }

        // Here we should be free of duplicates, so it's safe to remove the columns without creating duplicated entries.
        // Depending on whether the schema was created with ADODB or Laravel schema management, user_settings_pkey
        // will either be a constraint or an index. See https://github.com/pkp/pkp-lib/issues/7670.
        try {
            Schema::table('user_settings', fn (Blueprint $table) => $table->dropUnique('user_settings_pkey'));
        } catch (Exception $e) {
            error_log('Failed to drop unique index "user_settings_pkey" from table "user_settings", another attempt will be done.');
            try {
                Schema::table('user_settings', fn (Blueprint $table) => $table->dropIndex('user_settings_pkey'));
            } catch (Exception $e) {
                error_log('Second attempt to remove the index has failed, perhaps it doesn\'t exist.');
            }
        }

        Schema::table(
            'user_settings',
            function (Blueprint $table): void {
                $table->dropColumn('assoc_id', 'assoc_type');
                // Restore the primary/unique index, using the previous field order
                $table->unique(['user_id', 'locale', 'setting_name'], 'user_settings_pkey');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to removed data');
    }
}
