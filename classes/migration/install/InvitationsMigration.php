<?php

/**
 * @file classes/migration/install/InvitationsMigration.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationsMigration
 *
 * @brief Changes for the access_keys table to support invitations.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InvitationsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('access_keys', function (Blueprint $table) {
            $table->json('payload')->nullable();
            $table->integer('status')->default(0);
            $table->string('type')->nullable();
            $table->string('invitation_email')->nullable();
            $table->string('context_id')->nullable();
            $table->string('assoc_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::drop('access_keys');
    }
}
