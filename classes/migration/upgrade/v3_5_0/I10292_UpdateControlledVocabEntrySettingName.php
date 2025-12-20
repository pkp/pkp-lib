<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10292_UpdateControlledVocabEntrySettingName.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10292_UpdateControlledVocabEntrySettingName
 *
 * @brief update the `setting_name` from sybmolics to `name` for `controlled_vocab_entry_settings` table
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10292_UpdateControlledVocabEntrySettingName extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('controlled_vocab_entry_settings')
            ->whereIn('setting_name', [
                'submissionAgency',
                'submissionDiscipline',
                'submissionKeyword',
                'submissionLanguage',
                'submissionSubject',
                'interest',
            ])
            ->update(['setting_name' => 'name']);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
