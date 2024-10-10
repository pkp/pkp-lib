<?php
/**
 * @file classes/migration/upgrade/v3_4_0/I7135_CreateAuthorAffiliationsTables.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7135_CreateAuthorAffiliationsTables
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7135_CreateAuthorAffiliationsTables extends Migration
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
            $table->string('ror')->nullable(true);

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
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
