<?php

/**
 * @file classes/migration/install/ControlledVocabMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ControlledVocabMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Controlled vocabularies
        Schema::create('controlled_vocabs', function (Blueprint $table) {
            $table->comment('Every word or phrase used in a controlled vocabulary. Controlled vocabularies are used for submission metadata like keywords and subjects, reviewer interests, and wherever a similar dictionary of words or phrases is required. Each entry corresponds to a word or phrase like "cellular reproduction" and a type like "submissionKeyword".');
            $table->bigInteger('controlled_vocab_id')->autoIncrement();
            $table->string('symbolic', 64);
            $table->bigInteger('assoc_type')->default(0);
            $table->bigInteger('assoc_id')->default(0);
            $table->unique(['symbolic', 'assoc_type', 'assoc_id'], 'controlled_vocab_symbolic');
        });

        // Controlled vocabulary entries
        Schema::create('controlled_vocab_entries', function (Blueprint $table) {
            $table->comment('The order that a word or phrase used in a controlled vocabulary should appear. For example, the order of keywords in a publication.');
            $table->bigInteger('controlled_vocab_entry_id')->autoIncrement();

            $table->bigInteger('controlled_vocab_id');
            $table->foreign('controlled_vocab_id')->references('controlled_vocab_id')->on('controlled_vocabs')->onDelete('cascade');
            $table->index(['controlled_vocab_id'], 'controlled_vocab_entries_controlled_vocab_id');

            $table->float('seq', 8, 2)->nullable();
            $table->index(['controlled_vocab_id', 'seq'], 'controlled_vocab_entries_cv_id');
        });

        // Controlled vocabulary entry settings
        Schema::create('controlled_vocab_entry_settings', function (Blueprint $table) {
            $table->comment('More data about a controlled vocabulary entry, including localized properties such as the actual word or phrase.');
            $table->bigInteger('controlled_vocab_entry_id');
            $table->foreign('controlled_vocab_entry_id', 'c_v_e_s_entry_id')->references('controlled_vocab_entry_id')->on('controlled_vocab_entries')->onDelete('cascade');
            $table->index(['controlled_vocab_entry_id'], 'c_v_e_s_entry_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->unique(['controlled_vocab_entry_id', 'locale', 'setting_name'], 'c_v_e_s_pkey');
        });

        // Reviewer Interests Associative Table
        Schema::create('user_interests', function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'user_interests_user_id');

            $table->bigInteger('controlled_vocab_entry_id');
            $table->foreign('controlled_vocab_entry_id')->references('controlled_vocab_entry_id')->on('controlled_vocab_entries')->onDelete('cascade');
            $table->index(['controlled_vocab_entry_id'], 'user_interests_controlled_vocab_entry_id');

            $table->unique(['user_id', 'controlled_vocab_entry_id'], 'u_e_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('user_interests');
        Schema::drop('controlled_vocab_entry_settings');
        Schema::drop('controlled_vocab_entries');
        Schema::drop('controlled_vocabs');
    }
}
