<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Check for common problems early in the upgrade process.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

use PKP\db\DAORegistry;
use PKP\migration\Migration;

class PreflightCheckMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pkp/pkp-lib#6903 Prepare to add foreign key relationships
        // Clean orphaned assoc_type/assoc_id data in announcement_types
        $contextDao = \APP\core\Application::getContextDAO();
        $orphanedIds = DB::table('announcement_types AS at')->leftJoin($contextDao->tableName . ' AS c', 'at.assoc_id', '=', 'c.' . $contextDao->primaryKeyColumn)->whereNull('c.' . $contextDao->primaryKeyColumn)->orWhere('at.assoc_type', '<>', \Application::get()->getContextAssocType())->distinct()->pluck('at.type_id');
        foreach ($orphanedIds as $typeId) {
            error_log("Removing orphaned announcement type ID ${typeId} with no matching context ID.");
            DB::table('announcement_types')->where('type_id', '=', $typeId)->delete();
        }

        // Clean orphaned announcement_type_setting entries
        $orphanedIds = DB::table('announcement_type_settings AS ats')->leftJoin('announcement_types AS at', 'ats.type_id', '=', 'at.type_id')->whereNull('at.type_id')->distinct()->pluck('ats.type_id');
        foreach ($orphanedIds as $typeId) {
            error_log("Removing orphaned settings for missing announcement type ID ${typeId}");
            DB::table('announcement_type_settings')->where('type_id', '=', $typeId)->delete();
        }

        if ($count = DB::table('announcements AS a')->leftJoin('announcement_types AS at', 'a.type_id', '=', 'at.type_id')->whereNull('at.type_id')->whereNotNull('a.type_id')->update(['a.type_id' => null])) {
            error_log("Reset ${count} announcements with orphaned (non-null) announcement types to no announcement type.");
        }

        // Clean orphaned announcement_setting entries
        $orphanedIds = DB::table('announcement_settings AS a_s')->leftJoin('announcements AS a', 'a_s.announcement_id', '=', 'a.announcement_id')->whereNull('a.announcement_id')->distinct()->pluck('a_s.announcement_id');
        foreach ($orphanedIds as $announcementId) {
            error_log("Removing orphaned settings for missing announcement ID ${announcementId}");
            DB::table('announcement_settings')->where('announcement_id', '=', $announcementId)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): ?bool
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            $this->_installer->log("Upgrade failed! Fallback set to ${fallbackVersion} by pre-flight check.");
            return true;
        }
    }
}
