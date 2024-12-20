<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10292_UpdateControlledVocabAssocId.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10292_UpdateControlledVocabAssocId
 *
 * @brief Change of column `assoc_id` of table `controlled_vocabs` to nullable
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use PKP\user\interest\UserInterest;

class I10292_UpdateControlledVocabAssocId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('controlled_vocabs', function (Blueprint $table) {
            $table->bigInteger('assoc_id')->nullable(true)->change();
        });

        DB::table('controlled_vocabs')
            ->where('symbolic', UserInterest::CONTROLLED_VOCAB_INTEREST)
            ->update(['assoc_id' => Application::SITE_CONTEXT_ID]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('controlled_vocabs')
            ->where('symbolic', UserInterest::CONTROLLED_VOCAB_INTEREST)
            ->update(['assoc_id' => 0]);

        Schema::table('controlled_vocabs', function (Blueprint $table) {
            $table->bigInteger('assoc_id')->nullable(false)->change();
        });
    }
}
