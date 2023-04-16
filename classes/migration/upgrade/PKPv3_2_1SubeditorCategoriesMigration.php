<?php

/**
 * @file classes/migration/upgrade/PKPv3_2_1SubeditorCategoriesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPv3_2_1SubeditorCategoriesMigration
 *
 * @brief pkp/pkp-lib#5694 Allow subeditors to be assigned to Categories
 */

namespace PKP\migration\upgrade;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PKPv3_2_1SubeditorCategoriesMigration extends \PKP\migration\Migration
{
    private const ASSOC_TYPE_SECTION = 0x0000212;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema changes
        Schema::rename('section_editors', 'subeditor_submission_group');
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            // Change section_id to assoc_type/assoc_id
            $table->bigInteger('assoc_type')->nullable();
            $table->renameColumn('section_id', 'assoc_id');

            // Drop indexes
            $table->dropIndex('section_editors_pkey');
            $table->dropIndex('section_editors_context_id');
            $table->dropIndex('section_editors_section_id');
            $table->dropIndex('section_editors_user_id');

            // Create indexes
            $table->index(['context_id'], 'section_editors_context_id');
            $table->index(['assoc_id', 'assoc_type'], 'subeditor_submission_group_assoc_id');
            $table->index(['user_id'], 'subeditor_submission_group_user_id');
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_pkey');
        });

        // Populate the assoc_type data in the newly created column
        DB::table('subeditor_submission_group')->update(['assoc_type' => self::ASSOC_TYPE_SECTION]);
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
