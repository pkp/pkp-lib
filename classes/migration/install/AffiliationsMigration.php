<?php
/**
 * @file classes/migration/install/AffiliationsMigration.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AffiliationsMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class AffiliationsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('author_affiliations', function (Blueprint $table) {
            $table->comment('Author affiliations');
            $table->bigInteger('author_affiliation_id')->autoIncrement();
            $table->bigInteger('author_id');
            $table->string('ror')->nullable();

            $table->index(['ror'], 'author_affiliations_ror');
            $table->foreign('author_id')->references('author_id')->on('authors')->cascadeOnDelete();
        });

        Schema::create('author_affiliation_settings', function (Blueprint $table) {
            $table->comment('More data about author affiliations');
            $table->bigInteger('author_affiliation_setting_id')->autoIncrement();
            $table->bigInteger('author_affiliation_id');
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->index(['author_affiliation_id'], 'author_affiliation_settings_author_affiliation_id');
            $table->unique(['author_affiliation_id', 'locale', 'setting_name'], 'author_affiliation_settings_unique');
            $table->foreign('author_affiliation_id')->references('author_affiliation_id')->on('author_affiliations')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('author_affiliation_settings');
        Schema::drop('author_affiliations');
    }
}
