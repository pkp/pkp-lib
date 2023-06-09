<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8333_AddMissingForeignKeys.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8333_AddMissingForeignKeys
 *
 * @brief Upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I8333_AddMissingForeignKeys extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setupFieldSchema();
        $this->updateSpecialValues();
        $this->clearOrphanedEntities();
        $this->setupForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    protected function updateSpecialValues(): void
    {
        DB::table('filters')
            ->where('parent_filter_id', '=', 0)
            ->update(['parent_filter_id' => null]);
        DB::table('navigation_menu_item_assignments')
            ->where('parent_id', '=', 0)
            ->update(['parent_id' => null]);
        DB::table('email_log')
            ->where('sender_id', '=', 0)
            ->update(['sender_id' => null]);
        // Only the administrator user group (role_id = 1) is allowed to have a null context_id
        DB::table('user_groups')
            ->where('role_id', '=', 1)
            ->where('context_id', '=', 0)
            ->update(['context_id' => null]);
        foreach (['navigation_menu_items', 'navigation_menus', 'plugin_settings', 'filters'] as $tableName) {
            DB::table($tableName)
                ->where('context_id', '=', 0)
                ->update(['context_id' => null]);
        }
    }

    /**
     * Setup the field schema
     */
    protected function setupFieldSchema(): void
    {
        Schema::table('filters', function (Blueprint $table) {
            // Only needed to fix the default value (0)
            $table->bigInteger('filter_group_id')->default(null)->change();
            $table->bigInteger('parent_filter_id')->nullable()->default(null)->change();
        });

        Schema::table(
            'navigation_menu_item_assignments',
            fn (Blueprint $table) => $table->bigInteger('parent_id')->nullable()->default(null)->change()
        );

        foreach (['navigation_menu_items', 'navigation_menus', 'plugin_settings', 'user_groups', 'filters'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->bigInteger('context_id')->nullable()->default(null)->change());
        }

        Schema::table('email_log', fn (Blueprint $table) => $table->bigInteger('sender_id')->nullable()->default(null)->change());
    }

    /**
     * Clear orphaned entities
     */
    protected function clearOrphanedEntities(): void
    {
        // user_groups
        $ignoreAdministratorUserGroup = fn (Builder $q) => $q->where(
            fn (Builder $where) => $where->where('s.context_id', '!=', 0)->orWhere('s.role_id', '!=', 1)
        );
        $this->deleteOptionalReference('user_groups', 'context_id', $this->getContextTable(), $this->getContextKeyField(), $ignoreAdministratorUserGroup);

        // filters
        $this->deleteOptionalReference('filters', 'context_id', $this->getContextTable(), $this->getContextKeyField());
        // Ensures the relationship is cleared properly
        while ($this->deleteOptionalReference('filters', 'parent_filter_id', 'filters', 'filter_id'));

        // navigation_menus
        $this->deleteOptionalReference('navigation_menus', 'context_id', $this->getContextTable(), $this->getContextKeyField());

        // navigation_menu_items
        $this->deleteOptionalReference('navigation_menu_items', 'context_id', $this->getContextTable(), $this->getContextKeyField());

        // navigation_menu_item_assignments
        $this->deleteOptionalReference('navigation_menu_item_assignments', 'parent_id', 'navigation_menu_items', 'navigation_menu_item_id');

        // email_log
        $this->cleanOptionalReference('email_log', 'sender_id', 'users', 'user_id');

        // plugin_settings
        $this->deleteOptionalReference('plugin_settings', 'context_id', $this->getContextTable(), $this->getContextKeyField());
    }

    /**
     * Resets optional/nullable foreign key fields from the source table to NULL when the field contains invalid values
     * Used for NULLABLE relationships
     * @param $filter callable(Builder): Builder
     */
    protected function cleanOptionalReference(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn, ?callable $filter = null): int
    {
        $filter ??= fn(Builder $q) => $q;
        $ids = $filter(
            DB::table("{$sourceTable} AS s")
                ->leftJoin("{$referenceTable} AS r", "s.{$sourceColumn}", '=', "r.{$referenceColumn}")
                ->whereNotNull("s.{$sourceColumn}")
                ->whereNull("r.{$referenceColumn}")
                ->distinct()
        )
            ->pluck("s.{$sourceColumn}");

        if (!$ids->count()) {
            return 0;
        }

        $updated = 0;
        $this->_installer->log("Cleaning orphaned entries from \"{$sourceTable}\" with an invalid value for the column \"{$sourceColumn}\". The following IDs do not exist at the reference table \"{$referenceTable}\" and will be reset to NULL: {$ids->join(', ')}");
        foreach ($ids->chunk(1000) as $chunkedIds) {
            $updated += DB::table($sourceTable)
                ->whereIn($sourceColumn, $chunkedIds)
                ->update([$sourceColumn => null]);
        }
        $this->_installer->log("{$updated} entries updated");
        return $updated;
    }

    /**
     * Deletes rows from the source table where the foreign key field contains invalid values
     * Used for NULLABLE relationships, where the source record lose the meaning without its relationship
     * @param $filter callable(Builder): Builder
     */
    protected function deleteOptionalReference(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn, ?callable $filter = null): int
    {
        $filter ??= fn(Builder $q) => $q;
        $ids = $filter(
            DB::table("{$sourceTable} AS s")
                ->leftJoin("{$referenceTable} AS r", "s.{$sourceColumn}", '=', "r.{$referenceColumn}")
                ->whereNotNull("s.{$sourceColumn}")
                ->whereNull("r.{$referenceColumn}")
                ->distinct()
        )
            ->pluck("s.{$sourceColumn}");

        if (!$ids->count()) {
            return 0;
        }
        $this->_installer->log("Removing orphaned entries from \"{$sourceTable}\" with an invalid value for the column \"{$sourceColumn}\". The following IDs do not exist at the reference table \"{$referenceTable}\": {$ids->join(', ')}");
        $removed = 0;
        foreach ($ids->chunk(1000) as $chunkedIds) {
            $removed += DB::table($sourceTable)
                ->whereIn($sourceColumn, $chunkedIds)
                ->delete();
        }
        $this->_installer->log("{$removed} entries removed");
        return $removed;
    }

    /**
     * Setup the foreign keys and indexes
     */
    protected function setupForeignKeys(): void
    {
        Schema::table('filters', function (Blueprint $table) {
            $table->foreign('parent_filter_id', 'filters_parent_filter_id')->references('filter_id')->on('filters')->onDelete('cascade');
            if (!DB::getDoctrineSchemaManager()->introspectTable('filters')->hasIndex('filters_parent_filter_id')) {
                $table->index(['parent_filter_id'], 'filters_parent_filter_id');
            }
        });

        Schema::table(
            'navigation_menu_item_assignments',
            function (Blueprint $table) {
                $table->foreign('parent_id', 'navigation_menu_item_assignments_parent_id')
                    ->references('navigation_menu_item_id')->on('navigation_menu_items')
                    ->onDelete('cascade');
                if (!DB::getDoctrineSchemaManager()->introspectTable('navigation_menu_item_assignments')->hasIndex('navigation_menu_item_assignments_parent_id')) {
                    $table->index(['parent_id'], 'navigation_menu_item_assignments_parent_id');
                }
            }
        );

        // Entities missing the context_id foreign key
        foreach (['navigation_menu_items', 'navigation_menus', 'plugin_settings', 'user_groups', 'filters'] as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table) use ($tableName) {
                    $table->foreign('context_id', "{$tableName}_context_id")
                        ->references($this->getContextKeyField())
                        ->on($this->getContextTable())->onDelete('cascade');
                    if (!DB::getDoctrineSchemaManager()->introspectTable($tableName)->hasIndex("{$tableName}_context_id")) {
                        $table->index(['context_id'], "{$tableName}_context_id");
                    }
                }
            );
        }

        Schema::table(
            'email_log',
            function (Blueprint $table) {
                $table->foreign('sender_id', 'email_log_sender_id')
                    ->references('user_id')->on('users')
                    ->onDelete('cascade');
                if (!DB::getDoctrineSchemaManager()->introspectTable('email_log')->hasIndex('email_log_sender_id')) {
                    $table->index(['sender_id'], 'email_log_sender_id');
                }
            }
        );
    }
}
