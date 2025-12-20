<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10620_EditorialBoardMemberRole.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10620_EditorialBoardMemberRole
 *
 * @brief Add new Editorial Board Member user group.
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I10620_EditorialBoardMemberRole extends Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $roleId = 4097; // Role::ROLE_ID_ASSISTANT
        $nameKey = 'default.groups.name.editorialBoardMember';
        $abbrevKey = 'default.groups.abbrev.editorialBoardMember';

        $installedLocales = json_decode(DB::table('site')->select('installed_locales')->first()->installed_locales, true);

        $contexts = DB::table($this->getContextTable())
            ->select([$this->getContextIdColumn(), 'primary_locale'])
            ->get();
        foreach ($contexts as $context) {
            $contextId = $context->{$this->getContextIdColumn()};
            DB::table('user_groups')->insert([
                'context_id' => $contextId,
                'role_id' => $roleId,
                'is_default' => true,
                'show_title' => true,
                'permit_self_registration' => false,
                'permit_metadata_edit' => false,
                'permit_settings' => false,
                'masthead' => true,
            ]);
            $userGroupId = (int) DB::getPdo()->lastInsertId();

            DB::table('user_group_settings')->insert([
                ['user_group_id' => $userGroupId, 'locale' => '', 'setting_name' => 'nameLocaleKey', 'setting_value' => $nameKey],
                ['user_group_id' => $userGroupId, 'locale' => '', 'setting_name' => 'abbrevLocaleKey', 'setting_value' => $abbrevKey],
            ]);

            foreach ($installedLocales as $locale) {
                $translatedNameKey = __($nameKey, [], $locale);
                if ((!str_starts_with($translatedNameKey, '##') && !str_ends_with($translatedNameKey, '##')) ||
                    ($locale == $context->primary_locale)) {

                    DB::table('user_group_settings')->insert([
                        ['user_group_id' => $userGroupId, 'locale' => $locale, 'setting_name' => 'name', 'setting_value' => $translatedNameKey],
                    ]);
                }
                $translatedAbbrevKey = __($abbrevKey, [], $locale);
                if ((!str_starts_with($translatedAbbrevKey, '##') && !str_ends_with($translatedAbbrevKey, '##')) ||
                    ($locale == $context->primary_locale)) {

                    DB::table('user_group_settings')->insert([
                        ['user_group_id' => $userGroupId, 'locale' => $locale, 'setting_name' => 'abbrev', 'setting_value' => $translatedAbbrevKey],
                    ]);
                }
            }

            Repo::userGroup()::forgetEditorialCache($contextId);
            Repo::userGroup()::forgetEditorialHistoryCache($contextId);
        }
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
