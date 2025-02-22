<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10692_MigrateCitations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10692_MigrateCitations
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10692_CitationMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('citation_settings', 'setting_type')) {
            Schema::table('citation_settings', function (Blueprint $table) {
                $table->dropColumn('setting_type');
            });
        }

        Schema::table('citations', function (Blueprint $table) {
            $table->text('raw_citation')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
