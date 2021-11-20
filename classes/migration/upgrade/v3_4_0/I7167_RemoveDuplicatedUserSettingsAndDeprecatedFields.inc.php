<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7167_RemoveDuplicatedUserSettingsAndDeprecatedFields.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7167_RemoveDuplicatedUserSettingsAndDeprecatedFields
 * @brief Describe upgrade/downgrade Removes duplicated user_settings, as well as the deprecated fields "assoc_id" and "assoc_type".
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $isMysql = DB::connection() instanceof MySqlConnection;
        $deleteFrom = 'DELETE FROM user_settings s USING';
        $joinFilter = 'WHERE';
        if ($isMysql) {
            // The optimizer might cut-off the sub-queries/closures below and make MySQL fail to delete from the table which is selecting from
            DB::unprepared("SET optimizer_switch = 'derived_merge=off'");
            $deleteFrom = 'DELETE s FROM user_settings s INNER JOIN';
            $joinFilter = 'ON';
        }
        // Locates and removes duplicated user_settings
        // The latest code stores settings using assoc_id = 0 and assoc_type = 0. Which means entries using null or anything else are outdated.
        // Note: Old versions (e.g. OJS <= 2.x) made use of these fields, but these settings were removed years ago.
        DB::unprepared(
            "${deleteFrom}
            (
                SELECT best_duplicated.*
                FROM (
                    SELECT best.*
                    -- Find the duplicated entries
                    FROM (
                        SELECT s.setting_name, s.user_id, s.locale
                        FROM user_settings s
                        GROUP BY
                            s.setting_name, s.user_id, s.locale
                        HAVING COUNT(0) > 1
                    ) AS duplicated
                    -- Find the best matching records for each entry
                    INNER JOIN user_settings best
                        ON best.setting_name = duplicated.setting_name
                        AND best.user_id = duplicated.user_id
                        AND best.locale = duplicated.locale
                        AND CONCAT(COALESCE(best.assoc_id, -9999), '@', COALESCE(best.assoc_type, -9999)) = (
                            SELECT CONCAT(COALESCE(s.assoc_id, -9999), '@', COALESCE(s.assoc_type, -9999))
                            FROM user_settings s
                            WHERE s.setting_name = duplicated.setting_name
                            AND s.user_id = duplicated.user_id
                            AND s.locale = duplicated.locale
                            ORDER BY
                                CASE s.assoc_id
                                    WHEN 0 THEN 0
                                    WHEN NULL THEN 1
                                    ELSE 2
                                END,
                                CASE s.assoc_type
                                    WHEN 0 THEN 0
                                    WHEN NULL THEN 1
                                    ELSE 2
                                END, s.assoc_id, s.assoc_type
                            LIMIT 1
                        )
                ) best_duplicated
            ) best_duplicated
                -- The record matches the key fields, which means it's part of the duplicated set
                ${joinFilter} s.setting_name = best_duplicated.setting_name
                AND s.user_id = best_duplicated.user_id
                AND s.locale = best_duplicated.locale
                -- But unfortunately it's not the best match and thus will be removed
                AND (
                    COALESCE(s.assoc_id, -9999) <> COALESCE(best_duplicated.assoc_id, -9999)
                    OR COALESCE(s.assoc_type, -9999) <> COALESCE(best_duplicated.assoc_type, -9999)
                )"
        );

        if ($isMysql) {
            // Restore the optimizer setting to its default
            DB::unprepared("SET optimizer_switch = 'derived_merge=default'");
        }

        // Here we should be free of duplicates, so it's safe to remove the columns without creating duplicated entries.
        Schema::table(
            'user_settings',
            function (Blueprint $table): void {
                // Drop the unique index
                $table->dropUnique('user_settings_pkey');
                // Drop deprecated fields
                $table->dropColumn('assoc_id', 'assoc_type');
                // Add an ID field for the sake of normalization
                $table->bigInteger('user_settings_id')->autoIncrement();
                // Restore the unique index, using the previous field order
                $table->unique(['user_id', 'locale', 'setting_name'], 'user_settings_pkey');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new Exception('Downgrade unsupported due to removed data');
    }
}
