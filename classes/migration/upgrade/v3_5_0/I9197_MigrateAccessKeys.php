<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9197_MigrateAccessKeys.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9197_MigrateAccessKeys
 *
 * @brief Convert access keys to invitations.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class I9197_MigrateAccessKeys extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('access_keys', function (Blueprint $table) {
            $table->json('payload')->nullable();
            $table->integer('status')->default(0);
            $table->string('type')->nullable();
            $table->string('invitation_email')->nullable();
            $table->string('context_id')->nullable();
            $table->string('assoc_id')->nullable()->change();
            $table->timestamps();
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
