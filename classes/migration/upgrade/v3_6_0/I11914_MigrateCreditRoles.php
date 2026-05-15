<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11914_MigrateCreditRoles.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11914_MigrateCreditRoles.php
 *
 * @brief Adds migration for Credit Roles data originating from the Credit plugin
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I11914_MigrateCreditRoles extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all existing credit role settings for authors, 
        // and migrate them to the new credit_contributor_roles table
        $creditRoleSettings = DB::table('author_settings')
            ->where('setting_name', '=', 'creditRoles')
            ->whereNotNull('setting_value')
            ->get();

        // If there are no credit role settings, we can skip this migration
        if ($creditRoleSettings->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($creditRoleSettings) {

            // Get a mapping of credit role identifiers to their IDs for quick lookup
            $creditRoleIdByIdentifier = DB::table('credit_roles')
                ->pluck('credit_role_id', 'credit_role_identifier');

            $newRows = [];

            foreach ($creditRoleSettings as $setting) {
                $identifiers = json_decode($setting->setting_value, true);

                if (!is_array($identifiers)) {
                    $this->_installer->log(
                        "Invalid credit role identifiers for author_id "
                        . "{$setting->author_id}. Expected JSON array. Skipping."
                    );
                    continue;
                }

                // For each identifier, find the corresponding credit_role_id and prepare a new row for insertion
                foreach ($identifiers as $identifier) {
                    $creditRoleId = $creditRoleIdByIdentifier->get($identifier);

                    if ($creditRoleId === null) {
                        $this->_installer->log(
                            "Credit role identifier '{$identifier}' for author_id "
                            . "{$setting->author_id} not found in credit_roles table. Skipping."
                        );
                        continue;
                    }

                    $newRows[] = [
                        'contributor_id'      => $setting->author_id,
                        'credit_role_id'      => $creditRoleId,
                        'credit_degree'       => null,
                        'contributor_role_id' => null,
                    ];
                }
            }

            foreach (array_chunk($newRows, 100) as $chunk) {
                DB::table('credit_contributor_roles')->insert($chunk);
            }

            // Clean up old settings
            DB::table('author_settings')
                ->where('setting_name', '=', 'creditRoles')
                ->delete();

            // Clean up plugin settings for the Credit plugin, as they are no longer needed
            DB::table('plugin_settings')
                ->where('plugin_name', '=', 'creditplugin')
                ->delete();

        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
