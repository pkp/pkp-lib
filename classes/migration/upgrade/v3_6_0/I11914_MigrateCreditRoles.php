<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11914_MigrateCreditRoles.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11914_MigrateCreditRoles
 *
 * @brief Adds migration for Credit Roles data originating from the Credit plugin
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;
use PKP\install\DowngradeNotSupportedException;

class I11914_MigrateCreditRoles extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $creditRoleSettings = DB::table('author_settings')
            ->where('setting_name', '=', 'creditRoles')
            ->whereNotNull('setting_value')
            ->where('locale', '=', '')
            ->get();

        if ($creditRoleSettings->isEmpty()) {
            $this->cleanUpPlugin();
            return;
        }

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

            $identifiers = array_unique(
                array_map(fn($id) => str_replace('http://', 'https://', $id), $identifiers)
            );

            foreach ($identifiers as $identifier) {
                $identifier = str_replace('http://', 'https://', $identifier);
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

        if (empty($newRows)) {
            $this->cleanUpPlugin();
            return;
        }

        DB::transaction(function () use ($newRows) {
            foreach (array_chunk($newRows, 500) as $chunk) {
                DB::table('credit_contributor_roles')->insert($chunk);
            }

            DB::table('author_settings')
                ->where('setting_name', '=', 'creditRoles')
                ->delete();
        });

        $this->cleanUpPlugin();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Clean up plugin settings and version entries for the Credit plugin.
     */
    private function cleanUpPlugin(): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'creditplugin')
            ->delete();

        DB::table('versions')
            ->where('product_class_name', '=', 'CreditPlugin')
            ->delete();
    }
}
