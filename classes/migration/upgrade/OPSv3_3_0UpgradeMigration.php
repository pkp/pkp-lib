<?php

/**
 * @file classes/migration/upgrade/OPSv3_3_0UpgradeMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

namespace APP\migration\upgrade;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OPSv3_3_0UpgradeMigration extends \PKP\migration\upgrade\PKPv3_3_0UpgradeMigration
{
    protected function getSubmissionPath(): string
    {
        return 'articles';
    }

    protected function getContextPath(): string
    {
        return 'journals';
    }

    protected function getContextTable(): string
    {
        return 'journals';
    }

    protected function getContextKeyField(): string
    {
        return 'journal_id';
    }

    protected function getContextSettingsTable(): string
    {
        return 'journal_settings';
    }

    protected function getSectionTable(): string
    {
        return 'sections';
    }

    protected function getSerializedSettings(): array
    {
        return [
            'site_settings' => [
                'enableBulkEmails',
                'installedLocales',
                'pageHeaderTitleImage',
                'sidebar',
                'styleSheet',
                'supportedLocales',
            ],
            'journal_settings' => [
                'disableBulkEmailUserGroups',
                'favicon',
                'homepageImage',
                'pageHeaderLogoImage',
                'sidebar',
                'styleSheet',
                'submissionChecklist',
                'supportedFormLocales',
                'supportedLocales',
                'supportedSubmissionLocales',
                'enablePublisherId',
                'serverThumbnail',
            ],
            'publication_settings' => [
                'categoryIds',
                'coverImage',
                'disciplines',
                'keywords',
                'languages',
                'subjects',
                'supportingAgencies',
            ]
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        parent::up();

        // Delete the old MODS34 filters
        DB::statement("DELETE FROM filters WHERE class_name='plugins.metadata.mods34.filter.Mods34SchemaArticleAdapter'");
        DB::statement("DELETE FROM filter_groups WHERE symbolic IN ('article=>mods34', 'mods34=>article')");
    }

    /**
     * Complete specific submission file migrations
     *
     * The main submission file migration is done in
     * PKPv3_3_0UpgradeMigration and that migration must
     * be run before this one.
     */
    protected function migrateSubmissionFiles()
    {
        parent::migrateSubmissionFiles();

        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->renameColumn('file_id', 'submission_file_id');
        });
        DB::statement('UPDATE publication_galleys SET submission_file_id = NULL WHERE submission_file_id = 0');

        // pkp/pkp-lib#6616 Delete publication_galleys entries that correspond to nonexistent submission_files
        $orphanedIds = DB::table('publication_galleys AS pg')
            ->leftJoin('submission_files AS sf', 'pg.submission_file_id', '=', 'sf.submission_file_id')
            ->whereNull('sf.submission_file_id')
            ->whereNotNull('pg.submission_file_id')
            ->pluck('pg.submission_file_id', 'pg.galley_id');
        foreach ($orphanedIds as $galleyId => $submissionFileId) {
            error_log("Removing orphaned publication_galleys entry ID ${galleyId} with submission_file_id ${submissionFileId}");
            DB::table('publication_galleys')->where('galley_id', '=', $galleyId)->delete();
        }

        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->nullable()->unsigned()->change();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
        });
    }
}
