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

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use Throwable;

class I8737_SectionEditorsUniqueIndexUpdate extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropUniqueIndexKey();
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id', 'user_group_id'], 'section_editors_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->dropUniqueIndexKey();
        Schema::table('subeditor_submission_group', function (Blueprint $table) {
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_unique');
        });
    }

    /**
     * Drop the unique index key
     */
    protected function dropUniqueIndexKey(): void
    {
        if (DB::connection() instanceof PostgresConnection) {
            try {
                Schema::table(
                    'subeditor_submission_group',
                    fn ($table) => $table->dropUnique('section_editors_unique')
                );
            } catch (Throwable $exception) {
                $this->_installer->log('Failed to drop unique index "section_editors_unique" from table "subeditor_submission_group", another attempt will be done.');

                try {
                    Schema::table(
                        'subeditor_submission_group',
                        fn ($table) => $table->dropIndex('section_editors_unique')
                    );
                } catch (Throwable $exception) {
                    $this->_installer->log('Second attempt to remove the index has failed, perhaps it doesn\'t exist.');
                }
            }

            return;
        }

        Schema::table(
            'subeditor_submission_group',
            fn ($table) => $table->dropIndex('section_editors_unique')
        );
    }
}
