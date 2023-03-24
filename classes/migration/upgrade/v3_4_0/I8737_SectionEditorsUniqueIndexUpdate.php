<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8737_SectionEditorsUniqueIndexUpdate.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8737_SectionEditorsUniqueIndexUpdate
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I8737_SectionEditorsUniqueIndexUpdate extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->dropUnique('section_editors_unique');
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id', 'user_group_id'], 'section_editors_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->dropUnique('section_editors_unique');
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_unique');
        });
    }
}
