<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I857_ContributorRolesTypes.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I857_ContributorRolesTypes
 *
 * @brief create the contributor roles table
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
use PKP\facades\Repo;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class I857_ContributorRolesTypes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Contributor roles
        Schema::create('contributor_roles', function (Blueprint $table) {
            $table->comment('The list of the contributor roles');
            $table->bigInteger('contributor_role_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->enum('contributor_role_identifier', ContributorRoleIdentifier::getRoles());
            $table->unique(['context_id', 'contributor_role_identifier'], 'context_id_contributor_role_identifier_unique');
        });

        // Contributor role settings
        Schema::create('contributor_role_settings', function (Blueprint $table) {
            $table->comment('Contributor role settings');
            $table->bigInteger('contributor_role_setting_id')->autoIncrement();
            $table->bigInteger('contributor_role_id');
            $table->string('setting_name', 255);
            $table->string('setting_value', 255);
            $table->string('locale', 28);
            $table->unique(['contributor_role_id', 'setting_name', 'locale'], 'contributor_role_id_setting_name_locale_unique');
            $table->foreign('contributor_role_id', 'contributor_role_id_settings_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
        });

        // Add contributor type column
        Schema::table('credit_contributor_roles', function (Blueprint $table) {
            $table->foreign('contributor_role_id', 'contributor_role_id_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
        });

        $translations = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
            ->get()
            ->map(fn (UserGroup $ug, int $ugId) => [
                'userGroupId' => $ugId,
                'key' => $ug->nameLocaleKey,
                'name' => $ug->name,
                'contextId' => $ug->context_id,
            ]);
        $roles = collect([
            'default.groups.name.author' => ContributorRoleIdentifier::AUTHOR->getName(),
            'default.groups.name.translator' => ContributorRoleIdentifier::TRANSLATOR->getName(),
        ]);
        $contextIds = $translations
            ->pluck('contextId')
            ->unique();
        $person = ContributorType::PERSON->getName();

        // Create Author and Translator contributor roles
        $contextIds
            ->each(fn (int $contextId) =>
                $translations
                    ->filter(fn (array $t) => $t['contextId'] === $contextId)
                    ->each(fn (array $t) =>
                        Repo::contributorRole()->add($t['name'], $roles[$t['key']], $contextId)
                    )
            );

        // Add contributor roles and types to existing contributors
        DB::table('authors as a')
            ->select(['a.author_id', 'a.user_group_id', 's.context_id'])
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->get()
            ->each(function ($row) use ($translations, $roles) {
                $role = $roles->get($translations->get($row->user_group_id)['key']);
                Repo::creditContributorRole()->addContributorRoles([$role], $row->author_id, $row->context_id);
            });
        
        // Update translator ids to author ids
        $contextIds
            ->each(fn (int $contextId) => DB::table('authors')
                ->where('user_group_id', '=', $translations
                    ->first(fn (array $t) => $t['contextId'] === $contextId && $t['key'] === 'default.groups.name.translator')['userGroupId']
                )
                ->update(['user_group_id' => $translations
                    ->first(fn (array $t) => $t['contextId'] === $contextId && $t['key'] === 'default.groups.name.author')['userGroupId']]
                )
            );

        // Delete translator in user groups
        DB::table('user_groups')
            ->whereIn('user_group_id', $translations
                ->filter(fn (array $t) => $t['key'] === 'default.groups.name.translator')
                ->keys()
            )
            ->delete();

        Schema::table('authors', function (Blueprint $table) {
            // Change email to nullable
            $table->string('email', 90)->nullable()->change();
            // Remove column 'user_group_id'
            $table->dropForeign('authors_user_group_id_foreign');
            $table->dropIndex('authors_user_group_id');
            $table->dropColumn('user_group_id');
            // Add the enum column for contributor types
            $table->enum('contributor_type', ContributorType::getTypes())->nullable();
        });

        DB::table('authors')
            ->update(['contributor_type' => $person]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
