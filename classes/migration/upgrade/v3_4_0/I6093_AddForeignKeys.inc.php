<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6093_AddForeignKeys.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Describe upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I6093_AddForeignKeys extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcement_types', function (Blueprint $table) {
            // Drop the old assoc_type column and assoc-based index
            $table->dropIndex('announcement_types_assoc');
            $table->dropColumn('assoc_type');

            // Rename assoc_id to context_id and introduce foreign key constraint
            $table->renameColumn('assoc_id', 'context_id');
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable());

            // Introduce new index
            $table->index(['context_id'], 'announcement_types_context_id');
        });

        Schema::table('announcement_type_settings', function (Blueprint $table) {
            $table->foreign('type_id')->references('type_id')->on('announcement_types');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreign('type_id')->references('type_id')->on('announcement_types');
        });

        Schema::table('announcement_settings', function (Blueprint $table) {
            $table->foreign('announcement_id')->references('announcement_id')->on('announcements');
        });

        Schema::table('category_settings', function (Blueprint $table) {
            $table->dropColumn('setting_type');
        });
        // Permit nulls in categories.parent_id where previously 0 was used for "no parent"
        Schema::table('categories', function (Blueprint $table) {
            $table->bigInteger('parent_id')->nullable()->change();
        });
        DB::table('categories')->where('parent_id', '=', 0)->update(['parent_id' => null]);
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable());
            $table->foreign('parent_id')->references('category_id')->on('categories')->onDelete('set null');
        });
        Schema::table('publication_categories', function (Blueprint $table) {
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->foreign('publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
        });
        Schema::table('item_views', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to removed data');
    }
}
