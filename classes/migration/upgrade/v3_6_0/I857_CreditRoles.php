<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I857_CreditRoles.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I857_CreditRoles
 *
 * @brief create the credit roles tables
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\author\creditRole\CreditRoleDegree;
use PKP\core\Core;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I857_CreditRoles extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Credit roles listing table
        Schema::create('credit_roles', function (Blueprint $table) {
            $table->comment('The list of the CRediT Roles');
            $table->bigInteger('credit_role_id')->autoIncrement();
            $table->string('credit_role_identifier', 255);
            $table->unique(['credit_role_identifier'], 'credit_role_identifier_unique');
        });

        // Load en json, and fill table with values
        $creditRoles = json_decode(file_get_contents(Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/creditRoles/translations/en.json') ?: "", true);
        if (!$creditRoles) {
            throw new \Exception(PKP_LIB_PATH . '/lib/creditRoles/translations/en.json not found');
        }
        $creditRolesData = Arr::map(array_keys($creditRoles['translations'] ?? []), fn (string $role): array => ['credit_role_identifier' => $role]);
        DB::table('credit_roles')
            ->insert($creditRolesData);

        // Credit and contributor roles
        Schema::create('credit_contributor_roles', function (Blueprint $table) {
            $table->comment('The CRediT Roles and the degrees of contributors, and contributor roles');
            $table->bigInteger('credit_contributor_role_id')->autoIncrement();
            $table->bigInteger('contributor_id');
            $table->bigInteger('credit_role_id')->nullable();
            $table->enum('credit_degree', CreditRoleDegree::getDegrees())->nullable();
            $table->bigInteger('contributor_role_id')->nullable();
            $table->foreign('contributor_id', 'contributor_id_author_id_foreign')->references('author_id')->on('authors')->onDelete('cascade');
            $table->foreign('credit_role_id', 'credit_role_id_foreign')->references('credit_role_id')->on('credit_roles')->onDelete('cascade');
            $table->unique(['contributor_id', 'credit_role_id'], 'contributor_id_credit_role_id_unique');
            $table->unique(['contributor_id', 'contributor_role_id'], 'contributor_id_contributor_role_id_unique');
        });

        // Add contstraint to only allow either credit or contributor role per row
        DB::statement('
            ALTER TABLE credit_contributor_roles
            ADD CONSTRAINT check_xor_credit_contributor_role
            CHECK ((credit_role_id IS NOT NULL AND contributor_role_id IS NULL) OR (contributor_role_id IS NOT NULL AND credit_role_id IS NULL))
        ');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
