<?php

/**
 * @file classes/migration/install/InstitutionsMigration.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstitutionsMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use APP\core\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;

class InstitutionsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->comment('Institutions for statistics and subscriptions.');
            $table->bigInteger('institution_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'institutions_context_id');

            $table->string('ror', 255)->nullable()->comment('ROR (Research Organization Registry) ID');
            $table->softDeletes('deleted_at', 0);
        });

        Schema::create('institution_settings', function (Blueprint $table) {
            $table->comment('More data about institutions, including localized properties like names.');
            $table->bigIncrements('institution_setting_id');
            $table->bigInteger('institution_id');
            $table->foreign('institution_id')->references('institution_id')->on('institutions')->onDelete('cascade');
            $table->index(['institution_id'], 'institution_settings_institution_id');

            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['institution_id', 'locale', 'setting_name'], 'institution_settings_unique');
        });

        // Institution IPs and IP ranges.
        Schema::create('institution_ip', function (Blueprint $table) {
            $table->comment('Records IP address ranges and associates them with institutions.');
            $table->bigInteger('institution_ip_id')->autoIncrement();

            $table->bigInteger('institution_id');
            $table->foreign('institution_id')->references('institution_id')->on('institutions')->onDelete('cascade');
            $table->index(['institution_id'], 'institution_ip_institution_id');

            $table->string('ip_string', 40);
            $table->bigInteger('ip_start');
            $table->bigInteger('ip_end')->nullable();

            $table->index(['ip_start'], 'institution_ip_start');
            $table->index(['ip_end'], 'institution_ip_end');
        });

        if (Schema::hasTable('institutional_subscriptions') && Schema::hasColumn('institutional_subscriptions', 'institution_id')) {
            Schema::table('institutional_subscriptions', function (Blueprint $table) {
                $table->comment('Links institutional subscriptions with institutions.');
                $table->foreign('institution_id')->references('institution_id')->on('institutions');
                $table->index(['institution_id'], 'institutional_subscriptions_institution_ip');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('institutional_subscriptions', function (Blueprint $table) {
            $table->dropForeign('institution_id');
        });
        Schema::drop('institution_settings');
        Schema::drop('institution_ip');
        Schema::drop('institutions');
    }
}
