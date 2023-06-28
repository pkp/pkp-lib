<?php

/**
 * @file classes/migration/upgrade/v3_4_0/InvitationsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationsMigration
 *
 * @brief Change Locales from locale_countryCode localization folder notation to locale localization folder notation
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class InvitationsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
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
     */
    public function down(): void
    {
        Schema::table('access_keys', function (Blueprint $table) {
            $table->dropColumn('payload');
            $table->dropColumn('status');
            $table->dropColumn('type');
            $table->dropColumn('invitation_email');
            $table->dropColumn('context_id');
            $table->string('assoc_id')->nullable(false)->change();
            $table->dropTimestamps();
        });
    }
}
