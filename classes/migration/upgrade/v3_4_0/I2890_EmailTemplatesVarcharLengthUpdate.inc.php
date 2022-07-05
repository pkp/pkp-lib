<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I2890_EmailTemplatesVarcharLengthUpdate.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I2890_EmailTemplatesVarcharLengthUpdate
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I2890_EmailTemplatesVarcharLengthUpdate extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('email_key', 255)->change();
        });

        Schema::table('email_templates_default', function (Blueprint $table) {
            $table->string('email_key', 255)->change();
        });

        Schema::table('email_templates_default_data', function (Blueprint $table) {
            $table->string('email_key', 255)->change();
            $table->string('subject', 255)->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('email_key', 64)->change();
        });

        Schema::table('email_templates_default', function (Blueprint $table) {
            $table->string('email_key', 64)->change();
        });

        Schema::table('email_templates_default_data', function (Blueprint $table) {
            $table->string('email_key', 64)->change();
            $table->string('subject', 120)->change();
        });
    }
}
