<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8073_RemoveNotesWithoutQueriesAndRelatedObjects.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8073_RemoveNotesWithoutQueriesAndRelatedObjects
 *
 * @brief Removes Notes without Queries and related objects
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use PKP\config\Config;
use PKP\migration\Migration;

class I8073_RemoveNotesWithoutQueriesAndRelatedObjects extends Migration
{
    private const ASSOC_TYPE_NOTE = 0x0000208; // PKPApplication::ASSOC_TYPE_NOTE
    private const ASSOC_TYPE_QUERY = 0x010000a; // PKPApplication::ASSOC_TYPE_QUERY
    private const FILE_MODE_MASK = 0666; // FileManager::FILE_MODE_MASK
    private const DIRECTORY_MODE_MASK = 0777; // FileManager::DIRECTORY_MODE_MASK

    public function up(): void
    {
        // Does not have the foreign key reference
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->foreign('notification_id')->references('notification_id')->on('notifications')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        if (DB::getDoctrineSchemaManager()->introspectTable('submission_files')->hasForeignKey('submission_files_file_id_foreign')) {
            Schema::table('submission_files', fn (Blueprint $table) => $table->dropForeign('submission_files_file_id_foreign'));
        }
        Schema::table('submission_files', function (Blueprint $table) {
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        foreach (['submission_file_revisions_submission_file_id_foreign', 'submission_file_revisions_file_id_foreign'] as $foreignKeyName) {
            if (DB::getDoctrineSchemaManager()->introspectTable('submission_file_revisions')->hasForeignKey($foreignKeyName)) {
                Schema::table('submission_file_revisions', fn (Blueprint $table) => $table->dropForeign($foreignKeyName));
            }
        }
        Schema::table('submission_file_revisions', function (Blueprint $table) {
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
        });

        // Does not have the foreign key reference
        Schema::table('submission_file_settings', function (Blueprint $table) {
            $table->foreignId('submission_file_id')->change();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        if (DB::getDoctrineSchemaManager()->introspectTable('review_files')->hasForeignKey('review_files_submission_file_id_foreign')) {
            Schema::table('review_files', fn (Blueprint $table) => $table->dropForeign('review_files_submission_file_id_foreign'));
        }
        Schema::table('review_files', function (Blueprint $table) {
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
            $table->index(['submission_file_id'], 'review_files_submission_file_id');
        });

        // Does have the foreign key reference but not the CASCADE
        if (DB::getDoctrineSchemaManager()->introspectTable('review_round_files')->hasForeignKey('review_round_files_submission_file_id_foreign')) {
            Schema::table('review_round_files', fn (Blueprint $table) => $table->dropForeign('review_round_files_submission_file_id_foreign'));
        }
        Schema::table('review_round_files', function (Blueprint $table) {
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
        });

        // Does not have the foreign key reference
        Schema::table('query_participants', function (Blueprint $table) {
            $table->foreign('query_id')->references('query_id')->on('queries')->onDelete('cascade');
            $table->index(['query_id'], 'query_participants_query_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'query_participants_user_id');
        });

        $this->removeOrphanedNotes();
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropForeign('notification_settings_notification_id_foreign');
        });

        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropForeign('submission_files_file_id_foreign');
            $table->foreign('file_id')->references('file_id')->on('files');
        });

        Schema::table('submission_file_revisions', function (Blueprint $table) {
            $table->dropForeign('submission_file_revisions_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');

            $table->dropForeign('submission_file_revisions_file_id_foreign');
            $table->foreign('file_id')->references('file_id')->on('files');
        });

        Schema::table('submission_file_settings', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->nullable(false)->unsigned()->change();
            $table->dropForeign('submission_file_settings_submission_file_id_foreign');
        });

        Schema::table('review_files', function (Blueprint $table) {
            $table->dropForeign('review_files_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
        });

        Schema::table('review_round_files', function (Blueprint $table) {
            $table->dropForeign('review_round_files_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
        });

        Schema::table('query_participants', function (Blueprint $table) {
            $table->dropForeign('query_participants_query_id_foreign');
            $table->dropForeign('query_participants_user_id_foreign');
        });
    }

    public function removeOrphanedNotes(): void
    {
        // Create a Filesystem object with the appropriate adapter to access the actual files
        $umask = Config::getVar('files', 'umask', 0022);
        $adapter = new LocalFilesystemAdapter(
            Config::getVar('files', 'files_dir'),
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => self::FILE_MODE_MASK & ~$umask,
                    'private' => self::FILE_MODE_MASK & ~$umask,
                ],
                'dir' => [
                    'public' => self::DIRECTORY_MODE_MASK & ~$umask,
                    'private' => self::DIRECTORY_MODE_MASK & ~$umask,
                ]
            ]),
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS
        );
        $filesystem = new Filesystem($adapter);

        // Select notes without an associated query
        $orphanedNotesQuery = DB::table('notes AS n')
            ->leftJoin('queries AS q', 'n.assoc_id', '=', 'q.query_id')
            ->where('n.assoc_type', '=', static::ASSOC_TYPE_QUERY)
            ->whereNull('q.query_id');

        // Select files associated to the orphaned notes
        $orphanedSubmissionFilesFromNotes = (clone $orphanedNotesQuery)
            ->join('submission_files AS sf', 'sf.assoc_id', '=', 'n.note_id')
            ->where('sf.assoc_type', '=', static::ASSOC_TYPE_NOTE)
            ->join('files as f', 'sf.file_id', '=', 'f.file_id')
            ->select(
                'n.note_id',
                'sf.submission_file_id',
                'sf.file_id',
                'f.path',
                // Check wether the file is shared with another submission_file entry
                DB::raw(
                    'CASE WHEN EXISTS (
                        SELECT 0
                        FROM submission_files sf2
                        WHERE sf2.file_id = sf.file_id
                        AND sf2.submission_file_id <> sf.submission_file_id
                    ) THEN 1 END AS is_shared'
                )
            )
            ->lazyById(1000, 'sf.submission_file_id', 'submission_file_id');

        $submissionFileIds = [];
        $processSubmissionFileId = function ($id = null, $minimum = 1) use (&$submissionFileIds): void
        {
            if ($id) {
                $submissionFileIds[] = $id;
            }

            $count = count($submissionFileIds);
            if ($count && !($count % $minimum)) {
                DB::table('submission_files')->whereIn('submission_file_id', $submissionFileIds)->delete();
                $submissionFileIds = [];
            }
        };

        $fileIds = [];
        $processFileId = function ($id = null, $minimum = 1) use (&$fileIds): void
        {
            if ($id) {
                $fileIds[] = $id;
            }
            $count = count($fileIds);
            if ($count && !($count % $minimum)) {
                DB::table('files')->whereIn('file_id', $fileIds)->delete();
                $fileIds = [];
            }
        };
        foreach ($orphanedSubmissionFilesFromNotes as $submissionFile) {
            [
                'note_id' => $noteId,
                'submission_file_id' => $submissionFileId,
                'file_id' => $fileId,
                'path' => $path,
                'is_shared' => $isShared
            ] = (array) $submissionFile;

            $processSubmissionFileId($submissionFileId, 1000);

            if ($isShared) {
                continue;
            }

            $processFileId($fileId, 1000);
            if ($filesystem->has($path)) {
                try {
                    $filesystem->delete($path);
                    $this->_installer->log("A submission file that was attached to an orphaned note with ID {$noteId} at {$path} was successfully deleted.");
                } catch (FilesystemException | UnableToDeleteFile $exception) {
                    $exceptionMessage = $exception->getMessage();
                    $this->_installer->log("A submission file that was attached to an orphaned note with ID {$noteId} was found at {$path} but could not be deleted because of: {$exceptionMessage}.");
                }
            }
        }
        $processSubmissionFileId();
        $processFileId();

        $this->_installer->log("Attempting to remove orphaned note entries");
        $deletedCount = $orphanedNotesQuery->delete();
        $this->_installer->log($deletedCount ? "{$deletedCount} orphaned note entries were removed" : "No orphaned note entries were found");
    }
}
