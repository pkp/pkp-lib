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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
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
        });

        // Contributor role settings
        Schema::create('contributor_role_settings', function (Blueprint $table) {
            $table->comment('Contributor role settings');
            $table->bigInteger('contributor_role_setting_id')->autoIncrement();
            $table->bigInteger('contributor_role_id');
            $table->string('setting_name', 255);
            $table->string('setting_value', 255)->nullable();
            $table->string('locale', 28);
            $table->unique(['contributor_role_id', 'setting_name', 'locale'], 'contributor_role_id_setting_name_locale_unique');
            $table->foreign('contributor_role_id', 'contributor_role_id_settings_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
        });

        // Add foreign key
        Schema::table('credit_contributor_roles', function (Blueprint $table) {
            $table->foreign('contributor_role_id', 'contributor_role_id_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
        });

        /**
         * Add contributor roles and types to current contributors
         */

        $translations = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
            ->get()
            ->map(fn (UserGroup $ug, int $ugId): array => [
                'userGroupId' => $ugId,
                'key' => $ug->nameLocaleKey ?? 'default.groups.name.author',
                'name' => $ug->name,
                'contextId' => $ug->context_id,
            ]);
        $roles = collect([
            'default.groups.name.author' => ContributorRoleIdentifier::AUTHOR->getName(),
            'default.groups.name.translator' => ContributorRoleIdentifier::TRANSLATOR->getName(),
            'default.groups.name.chapterAuthor' => ContributorRoleIdentifier::AUTHOR->getName(),
            'default.groups.name.volumeEditor' => ContributorRoleIdentifier::EDITOR->getName(),
        ]);
        $contextIds = $translations
            ->pluck('contextId')
            ->unique();
        $person = ContributorType::PERSON->getName();

        // Create Author and Translator contributor roles. Get added role ids per context: [ contextId => [ identifier => roleId, ... ], ... ]
        $roleIds = $contextIds
            ->mapWithKeys(fn (int $contextId): array => [$contextId =>
                $translations
                    ->filter(fn (array $t) => $t['contextId'] === $contextId)
                    ->mapWithKeys(function (array $t, int $ugId) use ($roles, $contextId): array {
                        $identifier = $roles[$t['key']];
                        // Insert role. Get id for later use
                        $roleId = DB::table('contributor_roles')
                            ->insertGetId(['contributor_role_identifier' => $identifier, 'context_id' => $contextId], 'contributor_role_id');
                        return [$ugId => $roleId];
                    })
            ]);

        // Insert role settings
        DB::table('contributor_role_settings')
            ->insert(
                $roleIds
                    ->map(
                        fn ($ugIdRoleIds) => $ugIdRoleIds
                            ->map(function (int $roleId, int $ugId) use ($translations) {
                                return collect($translations->get($ugId)['name'])
                                    ->map(fn (string $value, string $locale): array => ['contributor_role_id' => $roleId, 'setting_name' => 'name', 'setting_value' => $value, 'locale' => $locale])
                                    ->values();
                            })
                    )
                    ->flatten(2)
                    ->values()
                    ->toArray()
            );

        // Add contributor roles to existing contributors
        DB::table('authors as a')
            ->select(['a.author_id', 'a.user_group_id', 's.context_id'])
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->get()
            ->chunk(1000)
            ->each(
                fn ($chunk) => DB::table('credit_contributor_roles')
                    ->insert(
                        $chunk
                            ->map(
                                fn (\StdClass $row): array =>
                                ['contributor_id' => $row->author_id, 'contributor_role_id' => $roleIds->get($row->context_id)->get($row->user_group_id)]
                            )
                            ->toArray()
                    )
            );

        /**
         * Contributor Type
         */

        Schema::table('authors', function (Blueprint $table) use ($person) {
            // Change email to nullable
            $table->string('email', 90)->nullable()->change();
            // Remove column 'user_group_id'
            $table->dropForeign('authors_user_group_id_foreign');
            $table->dropIndex('authors_user_group_id');
            $table->dropColumn('user_group_id');
            // Add the enum column for contributor types
            $table->enum('contributor_type', ContributorType::getTypes())->default($person);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
