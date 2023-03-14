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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            $this->dropUniqueIndexKey();
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id', 'user_group_id'], 'section_editors_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $this->dropUniqueIndexKey();
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_pkey');
        });
    }

    /**
     * Drop the unique index key if exists
     * 
     * @return bool
     */
    protected function dropUniqueIndexKey(): bool
    {
        $keyExists = Collection::make(DB::select('SHOW INDEXES FROM subeditor_submission_group'))
                ->pluck('Key_name')
                ->contains('section_editors_pkey');

        if ($keyExists) {
            DB::statement('ALTER TABLE subeditor_submission_group DROP CONSTRAINT section_editors_pkey');
            return true;
        }

        return false;
    }
}
