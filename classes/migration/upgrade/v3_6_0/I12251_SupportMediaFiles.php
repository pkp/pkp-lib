<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12251_SupportMediaFiles.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12251_SupportMediaFiles.php
 *
 * @brief Migration to add variant group support for submission files.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12251_SupportMediaFiles extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::create('variant_groups', function (Blueprint $table) {
            $table->bigInteger('variant_group_id')->autoIncrement();
        });

        Schema::table('submission_files', function (Blueprint $table) {
            $table->bigInteger('variant_group_id')->nullable();
            $table->string('variant_type', 255)->nullable();

            $table->foreign('variant_group_id')
                ->references('variant_group_id')
                ->on('variant_groups')
                ->onDelete('set null');
            $table->index(['variant_group_id'], 'submission_files_variant_group_id');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropForeign(['variant_group_id']);
            $table->dropIndex('submission_files_variant_group_id');
            $table->dropColumn(['variant_group_id', 'variant_type']);
        });
        Schema::dropIfExists('variant_groups');
    }
}
