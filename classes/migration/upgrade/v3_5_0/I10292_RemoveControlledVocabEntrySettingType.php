<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10292_RemoveControlledVocabEntrySettingType.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10292_RemoveControlledVocabEntrySettingType
 *
 * @brief Remove the `setting_type` column from `controlled_vocab_entry_settings` table
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I10292_RemoveControlledVocabEntrySettingType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('controlled_vocab_entry_settings', function (Blueprint $table) {
            $table->dropColumn('setting_type');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('controlled_vocab_entry_settings', function (Blueprint $table) {
            $table->string('setting_type', 6);
        });
    }
}
