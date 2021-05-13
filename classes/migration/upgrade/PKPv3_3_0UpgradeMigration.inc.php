<?php

/**
 * @file classes/migration/upgrade/PKPv3_3_0UpgradeMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade;

use APP\core\Application;
use APP\core\Services;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\db\XMLDAO;

use PKP\file\FileManager;
use PKP\submission\SubmissionFile;

class PKPv3_3_0UpgradeMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasColumn('submissions', 'locale')) {
            Schema::table('submissions', function (Blueprint $table) {
                // pkp/pkp-lib#3572 Remove OJS 2.x upgrade tools (OPS doesn't have this)
                $table->dropColumn('locale');
            });
        }
        Schema::table('submissions', function (Blueprint $table) {
            // pkp/pkp-lib#6285 submissions.section_id in OMP appears only from 3.2.1
            if (Schema::hasColumn($table->getTable(), 'section_id')) {
                // pkp/pkp-lib#2493 Remove obsolete columns
                $table->dropColumn('section_id');
            };
        });
        Schema::table('publication_settings', function (Blueprint $table) {
            // pkp/pkp-lib#6096 DB field type TEXT is cutting off long content
            $table->mediumText('setting_value')->nullable()->change();
        });
        if (Schema::hasColumn('authors', 'submission_id')) {
            Schema::table('authors', function (Blueprint $table) {
                // pkp/pkp-lib#2493 Remove obsolete columns
                $table->dropColumn('submission_id');
            });
        }
        if (Schema::hasColumn('author_settings', 'setting_type')) {
            Schema::table('author_settings', function (Blueprint $table) {
                // pkp/pkp-lib#2493 Remove obsolete columns
                $table->dropColumn('setting_type');
            });
        }
        Schema::table('announcements', function (Blueprint $table) {
            // pkp/pkp-lib#5865 Change announcement expiry format in database
            $table->date('date_expire')->change();
        });
        Schema::table('announcement_settings', function (Blueprint $table) {
            // pkp/pkp-lib#6748 Change announcement setting type to permit nulls
            $table->string('setting_type', 6)->nullable()->change();
        });

        // Transitional: The stage_id column may have already been added by the ADODB schema toolset
        if (!Schema::hasColumn('email_templates_default', 'stage_id')) {
            Schema::table('email_templates_default', function (Blueprint $table) {
                // pkp/pkp-lib#4796 stage ID as a filter parameter to email templates
                $table->bigInteger('stage_id')->nullable();
            });
        }

        // pkp/pkp-lib#6301: Indexes may be missing that affect search performance.
        // (These are added for 3.2.1-2 so may or may not be present for this upgrade code.)
        $schemaManager = DB::getDoctrineSchemaManager();
        if (!in_array('submissions_publication_id', array_keys($schemaManager->listTableIndexes('submissions')))) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->index(['submission_id'], 'submissions_publication_id');
            });
        }
        if (!in_array('submission_search_object_submission', array_keys($schemaManager->listTableIndexes('submission_search_objects')))) {
            Schema::table('submission_search_objects', function (Blueprint $table) {
                $table->index(['submission_id'], 'submission_search_object_submission');
            });
        }

        // Use nulls instead of 0 for assoc_type/id in user_settings
        DB::table('user_settings')->where('assoc_type', 0)->update(['assoc_type' => null]);
        DB::table('user_settings')->where('assoc_id', 0)->update(['assoc_id' => null]);

        // pkp/pkp-lib#6093 Don't allow nulls (previously an upgrade workaround)
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->bigInteger('assoc_type')->nullable(false)->change();
        });
        Schema::table('email_templates', function (Blueprint $table) {
            $table->bigInteger('context_id')->default(0)->nullable(false)->change();
        });
        Schema::table('genres', function (Blueprint $table) {
            $table->bigInteger('seq')->nullable(false)->change();
            $table->smallInteger('supplementary')->default(0)->nullable(false)->change();
        });
        Schema::table('event_log', function (Blueprint $table) {
            $table->bigInteger('assoc_type')->nullable(false)->change();
            $table->bigInteger('assoc_id')->nullable(false)->change();
        });
        Schema::table('email_log', function (Blueprint $table) {
            $table->bigInteger('assoc_type')->nullable(false)->change();
            $table->bigInteger('assoc_id')->nullable(false)->change();
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->bigInteger('assoc_type')->nullable(false)->change();
            $table->bigInteger('assoc_id')->nullable(false)->change();
        });
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->bigInteger('review_round_id')->nullable(false)->change();
        });
        Schema::table('authors', function (Blueprint $table) {
            $table->bigInteger('publication_id')->nullable(false)->change();
        });
        DB::unprepared('UPDATE review_assignments SET review_form_id=NULL WHERE review_form_id=0');

        $this->_populateEmailTemplates();
        $this->_makeRemoteUrlLocalizable();

        // pkp/pkp-lib#6057: Migrate locale property from publications to submissions
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('locale', 14)->nullable();
        });
        $currentPublicationIds = DB::table('submissions')->pluck('current_publication_id');
        $submissionLocales = DB::table('publications')
            ->whereIn('publication_id', $currentPublicationIds)
            ->pluck('locale', 'submission_id');
        foreach ($submissionLocales as $submissionId => $locale) {
            DB::table('submissions as s')
                ->where('s.submission_id', '=', $submissionId)
                ->update(['locale' => $locale]);
        }
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        // pkp/pkp-lib#6057 Submission files refactor
        $this->_migrateSubmissionFiles();

        $this->_fixCapitalCustomBlockTitles();
        $this->_createCustomBlockTitles();

        // Remove item views related to submission files and notes,
        // and convert the assoc_id to an integer
        DB::table('item_views')
            ->where('assoc_type', '!=', ASSOC_TYPE_REVIEW_RESPONSE)
            ->delete();
        // PostgreSQL requires an explicit type cast
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('ALTER TABLE item_views ALTER COLUMN assoc_id TYPE BIGINT USING (assoc_id::INTEGER)');
        } else {
            Schema::table('item_views', function (Blueprint $table) {
                $table->bigInteger('assoc_id')->change();
            });
        }

        // pkp/pkp-lib#4017 and pkp/pkp-lib#4622
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved_at']);
        });

        // pkp/pkp-lib#6594 Clear context defaults installed for unused languages
        $settingsWithDefaults = [
            'authorInformation',
            'librarianInformation',
            'openAccessPolicy',
            'privacyStatement',
            'readerInformation',
            'submissionChecklist',
            'clockssLicense',
            'lockssLicense',
        ];
        $contextDao = Application::getContextDAO();
        $tableName = $contextDao->tableName;
        $settingsTableName = $contextDao->settingsTableName;
        $primaryKeyColumn = $contextDao->primaryKeyColumn;
        $rows = DB::table($settingsTableName . ' as js')
            ->join($tableName . ' as j', 'j.' . $primaryKeyColumn, '=', 'js.' . $primaryKeyColumn)
            ->where('js.setting_name', '=', 'supportedFormLocales')
            ->select(['js.' . $primaryKeyColumn . ' as id', 'js.setting_value as locales'])
            ->get();
        foreach ($rows as $row) {
            // account for some locale settings stored as assoc arrays
            $locales = $row->locales;
            if (empty($locales)) {
                $locales = [];
            } elseif (@unserialize($locales) !== false) {
                $locales = unserialize($locales);
            } else {
                $locales = json_decode($locales, true);
            }
            $locales = array_values($locales);
            DB::table($settingsTableName)
                ->where($primaryKeyColumn, '=', $row->id)
                ->whereIn('setting_name', $settingsWithDefaults)
                ->whereNotIn('locale', $locales)
                ->delete();
        }
    }

    /**
     * Reverse the downgrades
     */
    public function down()
    {
        throw new PKP\install\DowngradeNotSupportedException();
    }

    /**
     * @brief populate email templates with new records for workflow stage id
     */
    private function _populateEmailTemplates()
    {
        $xmlDao = new XMLDAO();
        $emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
        $data = $xmlDao->parseStruct($emailTemplateDao->getMainEmailTemplatesFilename(), ['email']);
        foreach ($data['email'] as $template) {
            $attr = $template['attributes'];
            if (array_key_exists('stage_id', $attr)) {
                DB::table('email_templates_default')->where('email_key', $attr['key'])->update(['stage_id' => $attr['stage_id']]);
            }
        }
    }

    /**
     * @brief make remoteUrl navigation item type multilingual and drop the url column
     */
    private function _makeRemoteUrlLocalizable()
    {
        $contextService = Services::get('context');
        $contextIds = $contextService->getIds();
        foreach ($contextIds as $contextId) {
            $context = $contextService->get($contextId);
            $locales = $context->getData('supportedLocales');

            $navigationItems = DB::table('navigation_menu_items')->where('context_id', $contextId)->pluck('url', 'navigation_menu_item_id')->filter()->all();
            foreach ($navigationItems as $navigation_menu_item_id => $url) {
                foreach ($locales as $locale) {
                    DB::table('navigation_menu_item_settings')->insert([
                        'navigation_menu_item_id' => $navigation_menu_item_id,
                        'locale' => $locale,
                        'setting_name' => 'remoteUrl',
                        'setting_value' => $url,
                        'setting_type' => 'string'
                    ]);
                }
            }
        }

        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
        $site = $siteDao->getSite();
        $supportedLocales = $site->getSupportedLocales();
        $navigationItems = DB::table('navigation_menu_items')->where('context_id', '0')->pluck('url', 'navigation_menu_item_id')->filter()->all();
        foreach ($navigationItems as $navigation_menu_item_id => $url) {
            foreach ($supportedLocales as $locale) {
                DB::table('navigation_menu_item_settings')->insert([
                    'navigation_menu_item_id' => $navigation_menu_item_id,
                    'locale' => $locale,
                    'setting_name' => 'remoteUrl',
                    'setting_value' => $url,
                    'setting_type' => 'string'
                ]);
            }
        }

        Schema::table('navigation_menu_items', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }

    /**
     * Migrate submission files after major refactor
     *
     *	- Add files table to manage underlying file storage
     *	- Replace the use of file_id/revision as a unique id with a single
     * 		auto-incrementing submission_file_id, and update all references.
     *	- Move revisions to a submission_file_revisons table.
     *	- Drop unused columns in submission_files table.
     *
     * @see pkp/pkp-lib#6057
     */
    private function _migrateSubmissionFiles()
    {
        // pkp/pkp-lib#6616 Delete submission_files entries that correspond to nonexistent submissions
        $orphanedIds = DB::table('submission_files AS sf')->leftJoin('submissions AS s', 'sf.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->pluck('sf.submission_id', 'sf.file_id');
        foreach ($orphanedIds as $fileId => $submissionId) {
            error_log("Removing orphaned submission_files entry ID ${fileId} with submission_id ${submissionId}");
            DB::table('submission_files')->where('file_id', '=', $fileId)->delete();
        }

        // Add partial index (DBMS-specific)
        switch (DB::getDriverName()) {
            case 'mysql': DB::unprepared('CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name(50), setting_value(150))'); break;
            case 'pgsql': DB::unprepared("CREATE INDEX event_log_settings_name_value ON event_log_settings (setting_name, setting_value) WHERE setting_name IN ('fileId', 'submissionId')"); break;
        }

        // Create a new table to track files in file storage
        Schema::create('files', function (Blueprint $table) {
            $table->bigIncrements('file_id');
            $table->string('path', 255);
            $table->string('mimetype', 255);
        });

        // Create a new table to track submission file revisions
        Schema::create('submission_file_revisions', function (Blueprint $table) {
            $table->bigIncrements('revision_id');
            $table->unsignedBigInteger('submission_file_id');
            $table->unsignedBigInteger('file_id');
        });

        // Add columns to submission_files table
        Schema::table('submission_files', function (Blueprint $table) {
            $table->unsignedBigInteger('new_file_id')->nullable(); // Renamed and made not nullable at the end of the migration
        });

        // Drop unique keys that will cause trouble while we're migrating
        Schema::table('review_round_files', function (Blueprint $table) {
            $table->dropIndex('review_round_files_pkey');
        });

        // Create entry in files and revisions tables for every submission_file
        $fileManager = new FileManager();
        $rows = DB::table('submission_files')
            ->join('submissions', 'submission_files.submission_id', '=', 'submissions.submission_id')
            ->orderBy('file_id')
            ->orderBy('revision')
            ->get([
                'context_id',
                'file_id',
                'revision',
                'submission_files.submission_id',
                'genre_id',
                'file_type',
                'file_stage',
                'date_uploaded',
                'original_file_name'
            ]);
        $fileService = Services::get('file');
        $submissionFileService = Services::get('submissionFile');
        foreach ($rows as $row) {
            // Reproduces the removed method SubmissionFile::_generateFileName()
            // genre is %s because it can be blank with review attachments
            $filename = sprintf(
                '%d-%s-%d-%d-%d-%s.%s',
                $row->submission_id,
                $row->genre_id,
                $row->file_id,
                $row->revision,
                $row->file_stage,
                date('Ymd', strtotime($row->date_uploaded)),
                strtolower_codesafe($fileManager->parseFileExtension($row->original_file_name))
            );
            $path = sprintf(
                '%s/%s/%s',
                $submissionFileService->getSubmissionDir($row->context_id, $row->submission_id),
                $this->_fileStageToPath($row->file_stage),
                $filename
            );
            if (!$fileService->fs->has($path)) {
                error_log("A submission file was expected but not found at ${path}.");
            }
            $newFileId = DB::table('files')->insertGetId([
                'path' => $path,
                'mimetype' => $row->file_type,
            ], 'file_id');
            DB::table('submission_files')
                ->where('file_id', $row->file_id)
                ->where('revision', $row->revision)
                ->update(['new_file_id' => $newFileId]);
            DB::table('submission_file_revisions')->insert([
                'submission_file_id' => $row->file_id,
                'file_id' => $newFileId,
            ]);

            // Update revision data in event logs
            $eventLogIds = DB::table('event_log_settings')
                ->where('setting_name', '=', 'fileId')
                ->where('setting_value', '=', $row->file_id)
                ->pluck('log_id');
            DB::table('event_log_settings')
                ->whereIn('log_id', $eventLogIds)
                ->where('setting_name', 'fileRevision')
                ->where('setting_value', '=', $row->revision)
                ->update(['setting_value' => $newFileId]);
        }

        // Collect rows that will be deleted because they are old revisions
        // They are identified by the new_file_id column, which is the only unique
        // column on the table at this point.
        $newFileIdsToDelete = [];

        // Get all the unique file_ids. For each one, determine the latest revision
        // in order to keep it in the table. The others will be flagged for removal
        $revisionRowFileIds = DB::table('submission_files')
            ->groupBy('file_id')
            ->pluck('file_id');
        foreach ($revisionRowFileIds as $revisionRowFileId) {
            $submissionFileRows = DB::table('submission_files')
                ->where('file_id', '=', $revisionRowFileId)
                ->orderBy('revision', 'desc')
                ->get([
                    'file_id',
                    'new_file_id',
                ]);
            $latestFileId = $submissionFileRows[0]->new_file_id;
            foreach ($submissionFileRows as $submissionFileRow) {
                if ($submissionFileRow->new_file_id !== $latestFileId) {
                    $newFileIdsToDelete[] = $submissionFileRow->new_file_id;
                }
            }
        }

        // Delete the rows for old revisions
        DB::table('submission_files')
            ->whereIn('new_file_id', $newFileIdsToDelete)
            ->delete();

        // Remove all review round files that point to file ids
        // that don't exist, so that the foreign key can be set
        // up successfully.
        // See: https://github.com/pkp/pkp-lib/issues/6337
        DB::table('review_round_files as rrf')
            ->leftJoin('submission_files as sf', 'sf.file_id', '=', 'rrf.file_id')
            ->whereNotNull('rrf.file_id')
            ->whereNull('sf.file_id')
            ->delete();

        // Update review round files
        $rows = DB::table('review_round_files')->get();
        foreach ($rows as $row) {
            // Delete this row if another revision exists for this
            // submission file. This ensures that when the revision
            // column is dropped the submission_file_id column will
            // be unique.
            $count = DB::table('review_round_files')
                ->where('file_id', '=', $row->file_id)
                ->count();
            if ($count > 1) {
                DB::table('review_round_files')
                    ->where('file_id', '=', $row->file_id)
                    ->where('revision', '=', $row->revision)
                    ->delete();
                continue;
            }

            // Set assoc_type and assoc_id for all review round files
            // Run this before migration to internal review file stages
            DB::table('submission_files')
                ->where('file_id', '=', $row->file_id)
                ->whereIn('file_stage', [SubmissionFile::SUBMISSION_FILE_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
                ->update([
                    'assoc_type' => ASSOC_TYPE_REVIEW_ROUND,
                    'assoc_id' => $row->review_round_id,
                ]);
        }

        // Update name of event log params to reflect new file structure
        DB::table('event_log_settings')
            ->where('setting_name', 'fileId')
            ->update(['setting_name' => 'submissionFileId']);
        DB::table('event_log_settings')
            ->where('setting_name', 'fileRevision')
            ->update(['setting_name' => 'fileId']);

        // Update file name of dependent files, see: pkp/pkp-lib#6801
        DB::table('submission_files')
            ->select('file_id', 'original_file_name')
            ->where('file_stage', '=', SubmissionFile::SUBMISSION_FILE_DEPENDENT)
            ->chunkById(1000, function ($dependentFiles) {
                foreach ($dependentFiles as $dependentFile) {
                    DB::table('submission_file_settings')
                        ->where('file_id', '=', $dependentFile->file_id)
                        ->where('setting_name', '=', 'name')
                        ->update(['setting_value' => $dependentFile->original_file_name]);
                }
            }, 'file_id');

        // Restructure submission_files and submission_file_settings tables
        Schema::table('submission_files', function (Blueprint $table) {
            $table->bigInteger('file_id')->nullable(false)->unsigned()->change();
        });
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropPrimary(); // Drop compound primary key constraint
        });
        Schema::table('submission_files', function (Blueprint $table) {
            $table->renameColumn('file_id', 'submission_file_id');
            $table->renameColumn('new_file_id', 'file_id');
            $table->renameColumn('source_file_id', 'source_submission_file_id');
            $table->renameColumn('date_uploaded', 'created_at');
            $table->renameColumn('date_modified', 'updated_at');
            $table->dropColumn('revision');
            $table->dropColumn('source_revision');
            $table->dropColumn('file_size');
            $table->dropColumn('file_type');
            $table->dropColumn('original_file_name');
            $table->primary('submission_file_id');
        });
        //  pkp/pkp-lib#5804
        $schemaManager = DB::getDoctrineSchemaManager();
        if (!in_array('submission_files_stage_assoc', array_keys($schemaManager->listTableIndexes('submission_files')))) {
            Schema::table('submission_files', function (Blueprint $table) {
                $table->index(['file_stage', 'assoc_type', 'assoc_id'], 'submission_files_stage_assoc');
            });
        }
        // Modify column types and attributes in separate migration
        // function to prevent error in postgres with unfound columns
        Schema::table('submission_files', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->autoIncrement()->unsigned()->change();
            $table->bigInteger('file_id')->nullable(false)->unsigned()->change();
            $table->foreign('file_id')->references('file_id')->on('files');
        });
        Schema::table('submission_file_settings', function (Blueprint $table) {
            $table->renameColumn('file_id', 'submission_file_id');
            $table->string('setting_type', 6)->default('string')->change();
        });

        // Update columns in related tables
        Schema::table('review_round_files', function (Blueprint $table) {
            $table->renameColumn('file_id', 'submission_file_id');
            $table->dropColumn('revision');
        });
        Schema::table('review_round_files', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->nullable(false)->unique()->unsigned()->change();
            $table->unique(['submission_id', 'review_round_id', 'submission_file_id'], 'review_round_files_pkey');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
        });
        Schema::table('review_files', function (Blueprint $table) {
            $table->renameColumn('file_id', 'submission_file_id');
        });

        // pkp/pkp-lib#6616 Delete review_files entries that correspond to nonexistent submission_files
        $orphanedIds = DB::table('review_files AS rf')
            ->select('rf.submission_file_id', 'rf.review_id')
            ->leftJoin('submission_files AS sf', 'rf.submission_file_id', '=', 'sf.submission_file_id')
            ->whereNull('sf.submission_file_id')
            ->whereNotNull('rf.submission_file_id')
            ->get();
        foreach ($orphanedIds as $orphanedId) {
            $reviewId = $orphanedId->{'review_id'};
            $submissionFileId = $orphanedId->{'submission_file_id'};
            error_log("Removing orphaned review_files entry with review_id ID ${reviewId} and submission_file_id ${submissionFileId}");
            DB::table('review_files')
                ->where('submission_file_id', '=', $submissionFileId)
                ->where('review_id', '=', $reviewId)
                ->delete();
        }

        Schema::table('review_files', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->nullable(false)->unsigned()->change();
            $table->dropIndex('review_files_pkey');
            $table->unique(['review_id', 'submission_file_id'], 'review_files_pkey');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
        });
        Schema::table('submission_file_revisions', function (Blueprint $table) {
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
            $table->foreign('file_id')->references('file_id')->on('files');
        });

        // Postgres leaves the old file_id autoincrement sequence around, so
        // we delete it and apply a new sequence.
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('DROP SEQUENCE submission_files_file_id_seq CASCADE');
            Schema::table('submission_files', function (Blueprint $table) {
                $table->bigIncrements('submission_file_id')->change();
            });
        }
    }

    /**
     * Get the directory of a file based on its file stage
     *
     * @param int $fileStage One of SUBMISSION_FILE_ constants
     *
     * @return string
     */
    private function _fileStageToPath($fileStage)
    {
        static $fileStagePathMap = [
            SubmissionFile::SUBMISSION_FILE_SUBMISSION => 'submission',
            SubmissionFile::SUBMISSION_FILE_NOTE => 'note',
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE => 'submission/review',
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT => 'submission/review/attachment',
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION => 'submission/review/revision',
            SubmissionFile::SUBMISSION_FILE_FINAL => 'submission/final',
            7 /* SUBMISSION_FILE_FAIR_COPY */ => 'submission/fairCopy',
            8 /* SUBMISSION_FILE_EDITOR */ => 'submission/editor',
            SubmissionFile::SUBMISSION_FILE_COPYEDIT => 'submission/copyedit',
            SubmissionFile::SUBMISSION_FILE_DEPENDENT => 'submission/proof',
            SubmissionFile::SUBMISSION_FILE_PROOF => 'submission/proof',
            SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY => 'submission/productionReady',
            SubmissionFile::SUBMISSION_FILE_ATTACHMENT => 'attachment',
            SubmissionFile::SUBMISSION_FILE_QUERY => 'submission/query',
        ];

        if (!isset($fileStagePathMap[$fileStage])) {
            error_log('A file assigned to the file stage ' . $fileStage . ' could not be migrated.');
        }

        return $fileStagePathMap[$fileStage];
    }

    /*
     * Update block names to be all lowercase
     *
     * In previous versions, a custom block name would be stored in the
     * array of blocks with capitals but the block name in the plugin_settings
     * table is all lowercase. This migration aligns the two places by changing
     * the block names to always use lowercase.
     *
     * @return void
     */
    private function _fixCapitalCustomBlockTitles()
    {
        $rows = DB::table('plugin_settings')
            ->where('plugin_name', 'customblockmanagerplugin')
            ->where('setting_name', 'blocks')
            ->get();
        foreach ($rows as $row) {
            $updateBlocks = false;
            $blocks = unserialize($row->setting_value);
            foreach ($blocks as $key => $block) {
                $newBlock = strtolower_codesafe($block);
                if ($block !== $newBlock) {
                    $blocks[$key] = $newBlock;
                    $updateBlocks = true;
                }
            }
            if ($updateBlocks) {
                DB::table('plugin_settings')
                    ->where('plugin_name', 'customblockmanagerplugin')
                    ->where('setting_name', 'blocks')
                    ->where('context_id', $row->context_id)
                    ->update(['setting_value' => serialize($blocks)]);
            }
        }
    }

    /*
     * Create titles for custom block plugins
     *
     * This method copies the block names, which are a unique id,
     * into a block setting, `blockTitle`, in the context's
     * primary locale
     *
     * @see https://github.com/pkp/pkp-lib/issues/5619
     */
    private function _createCustomBlockTitles()
    {
        $contextDao = \Application::get()->getContextDAO();

        $rows = DB::table('plugin_settings')
            ->where('plugin_name', 'customblockmanagerplugin')
            ->where('setting_name', 'blocks')
            ->get();

        $newRows = [];
        foreach ($rows as $row) {
            $locale = DB::table($contextDao->tableName)
                ->where($contextDao->primaryKeyColumn, $row->context_id)
                ->first()
                ->primary_locale;
            $blocks = unserialize($row->setting_value);
            foreach ($blocks as $block) {
                $newRows[] = [
                    'plugin_name' => $block,
                    'context_id' => $row->context_id,
                    'setting_name' => 'blockTitle',
                    'setting_value' => serialize([$locale => $block]),
                    'setting_type' => 'object',
                ];
            }
        }
        if (!DB::table('plugin_settings')->insert($newRows)) {
            error_log('Failed to create title for custom blocks. This can be fixed manually by editing each custom block and adding a title.');
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\migration\upgrade\PKPv3_3_0UpgradeMigration', '\PKPv3_3_0UpgradeMigration');
}
