<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7706_AssociateTemplatesWithMailables.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7706_AssociateTemplatesWithMailables
 * @brief Refactors relationship between Mailables and Email Templates
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I7706_AssociateTemplatesWithMailables extends Migration
{
    public function up(): void
    {
        Schema::create('mailable_templates', function (Blueprint $table) {
            $table->bigInteger('email_id');
            $table->string('mailable_id', 255);
            $table->foreign('email_id')->references('email_id')->on('email_templates')->onDelete('cascade');
            $table->primary(['email_id', 'mailable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('mailable_templates', function (Blueprint $table) {
            $table->drop();
        });
    }
}
