<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6091_AddFilterNamespaces.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6091_AddFilterNamespaces
 * @brief Describe upgrade/downgrade operations for introducing namespaces to the built-in set of filters.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I6091_AddFilterNamespaces extends \PKP\migration\Migration
{
    public const FILTER_RENAME_MAP = [
        // Application filters
        'plugins.generic.crossref.filter.PreprintCrossrefXmlFilter' => 'APP\plugins\generic\crossref\filter\PreprintCrossrefXmlFilter',
        'plugins.metadata.dc11.filter.Dc11SchemaPreprintAdapter' => 'APP\plugins\metadata\dc11\filter\Dc11SchemaPreprintAdapter',
        'plugins.importexport.native.filter.PreprintNativeXmlFilter' => 'APP\plugins\importexport\native\filter\PreprintNativeXmlFilter',
        'plugins.importexport.native.filter.NativeXmlAuthorFilter' => 'APP\plugins\importexport\native\filter\NativeXmlPreprintFilter',
        'plugins.importexport.native.filter.NativeXmlAuthorFilter' => 'APP\plugins\importexport\native\filter\NativeXmlAuthorFilter',
        'plugins.importexport.native.filter.AuthorNativeXmlFilter' => 'APP\plugins\importexport\native\filter\AuthorNativeXmlFilter',
        'plugins.importexport.native.filter.NativeXmlPreprintFileFilter' => 'APP\plugins\importexport\native\filter\NativeXmlPreprintFileFilter',
        'plugins.importexport.native.filter.PreprintGalleyNativeXmlFilter' => 'APP\plugins\importexport\native\filter\PreprintGalleyNativeXmlFilter',
        'plugins.importexport.native.filter.NativeXmlPreprintGalleyFilter' => 'APP\plugins\importexport\native\filter\NativeXmlPreprintGalleyFilter',
        'plugins.importexport.native.filter.PublicationNativeXmlFilter' => 'APP\plugins\importexport\native\filter\PublicationNativeXmlFilter',
        'plugins.importexport.native.filter.NativeXmlPublicationFilter' => 'APP\plugins\importexport\native\filter\NativeXmlPublicationFilter',

        // pkp-lib filters
        'lib.pkp.plugins.importexport.users.filter.PKPUserUserXmlFilter' => 'PKP\plugins\importexport\users\filter\PKPUserUserXmlFilter',
        'lib.pkp.plugins.importexport.users.filter.UserXmlPKPUserFilter' => 'PKP\plugins\importexport\users\filter\UserXmlPKPUserFilter',
        'lib.pkp.plugins.importexport.users.filter.UserGroupNativeXmlFilter' => 'PKP\plugins\importexport\users\filter\UserGroupNativeXmlFilter',
        'lib.pkp.plugins.importexport.users.filter.NativeXmlUserGroupFilter' => 'PKP\plugins\importexport\users\filter\NativeXmlUserGroupFilter',
        'lib.pkp.plugins.importexport.native.filter.SubmissionFileNativeXmlFilter' => 'PKP\plugins\importexport\native\filter\SubmissionFileNativeXmlFilter',
    ];

    public const TASK_RENAME_MAP = [
        'lib.pkp.classes.task.StatisticsReport' => 'PKP\task\StatisticsReport',
        'lib.pkp.classes.task.RemoveUnvalidatedExpiredUsers' => 'PKP\task\RemoveUnvalidatedExpiredUsers',
        'lib.pkp.classes.task.UpdateIPGeoDB' => 'PKP\task\UpdateIPGeoDB',
        'classes.tasks.UsageStatsLoader' => 'APP\tasks\UsageStatsLoader',
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        foreach (self::FILTER_RENAME_MAP as $oldName => $newName) {
            DB::statement('UPDATE filters SET class_name = ? WHERE class_name = ?', [$newName, $oldName]);
        }
        foreach (self::TASK_RENAME_MAP as $oldName => $newName) {
            DB::statement('UPDATE scheduled_tasks SET class_name = ? WHERE class_name = ?', [$newName, $oldName]);
        }
        DB::statement('UPDATE filter_groups SET output_type=? WHERE output_type = ?', ['metadata::APP\plugins\metadata\dc11\schema\Dc11Schema(PREPRINT)', 'metadata::plugins.metadata.dc11.schema.Dc11Schema(PREPRINT)']);
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        foreach (self::FILTER_RENAME_MAP as $oldName => $newName) {
            DB::statement('UPDATE filters SET class_name = ? WHERE class_name = ?', [$oldName, $newName]);
        }
        foreach (self::TASK_RENAME_MAP as $oldName => $newName) {
            DB::statement('UPDATE scheduled_tasks SET class_name = ? WHERE class_name = ?', [$oldName, $newName]);
        }
        DB::statement('UPDATE filter_groups SET output_type=? WHERE output_type = ?', ['metadata::plugins.metadata.dc11.schema.Dc11Schema(PREPRINT)', 'metadata::APP\plugins\metadata\dc11\schema\Dc11Schema(PREPRINT)']);
    }
}
