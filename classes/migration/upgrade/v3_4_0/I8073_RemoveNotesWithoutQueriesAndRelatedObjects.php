<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8073_RemoveNotesWithoutQueriesAndRelatedObjects.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8073_RemoveNotesWithoutQueriesAndRelatedObjects
 * @brief Removes Notes without Queries and related objects
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToDeleteFile;

class I8073_RemoveNotesWithoutQueriesAndRelatedObjects extends Migration
{
    private const ASSOC_TYPE_NOTE = 0x0000208; // PKPApplication::ASSOC_TYPE_NOTE
    private const ASSOC_TYPE_QUERY = 0x010000a; // PKPApplication::ASSOC_TYPE_QUERY
    private const SUBMISSION_FILE_QUERY = 18; // SubmissionFile::SUBMISSION_FILE_QUERY
    private const FILE_MODE_MASK = 0666; // FileManager::FILE_MODE_MASK
    private const DIRECTORY_MODE_MASK = 0777; // FileManager::DIRECTORY_MODE_MASK

    public function up(): void
    {
        // Does not have the foreign key reference
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->foreign('notification_id')->references('notification_id')->on('notifications')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropForeign('submission_files_file_id_foreign');
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        Schema::table('submission_file_revisions', function (Blueprint $table) {
            $table->dropForeign('submission_file_revisions_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');

            $table->dropForeign('submission_file_revisions_file_id_foreign');
            $table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade');
        });

        // Does not have the foreign key reference
        Schema::table('submission_file_settings', function (Blueprint $table) {
            $table->bigInteger('submission_file_id')->nullable(false)->unsigned()->change();
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        Schema::table('review_files', function (Blueprint $table) {
            $table->dropForeign('review_files_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
        });

        // Does have the foreign key reference but not the CASCADE
        Schema::table('review_round_files', function (Blueprint $table) {
            $table->dropForeign('review_round_files_submission_file_id_foreign');
            $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files')->onDelete('cascade');
        });

        // Does not have the foreign key reference
        Schema::table('query_participants', function (Blueprint $table) {
            $table->foreign('query_id')->references('query_id')->on('queries')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });

        $orphanedIds = DB::table('notes AS n')
            ->leftJoin('queries AS q', 'n.assoc_id', '=', 'q.query_id')
            ->where('n.assoc_type', '=', self::ASSOC_TYPE_QUERY)
            ->whereNull('q.query_id')
            ->pluck('n.note_id', 'n.assoc_id');

        foreach ($orphanedIds as $neQueryId => $noteId) {
            $notesFileRows = DB::table('submission_files as sf')
                ->join('files as f', 'sf.file_id', '=', 'f.file_id')
                ->where('sf.assoc_type', '=', self::ASSOC_TYPE_NOTE)
                ->where('sf.assoc_id', '=', $noteId)
                ->where('sf.file_stage', '=', self::SUBMISSION_FILE_QUERY)
                ->get([
                        'sf.submission_file_id as submissionFileId',
                        'sf.file_id as fileId',
                        'f.path as filePath'
                    ]);

            $filesToCheckForDeletion = array();
            foreach ($notesFileRows as $submissionFileRow) {
                $submissionFileId = $submissionFileRow->submissionFileId;
                $submissionFileFileId = $submissionFileRow->fileId;
                $submissionFilePath = $submissionFileRow->filePath;

                DB::table('submission_files')
                    ->where('submission_file_id', '=', $submissionFileId)
                    ->delete();
                
                if (!array_key_exists($submissionFileFileId, $filesToCheckForDeletion)) {
                    $filesToCheckForDeletion[$submissionFileFileId] = $submissionFilePath;
                }
            }

            foreach ($filesToCheckForDeletion as $submissionFileFileId => $submissionFilePath) {
                $remainingSubmissionFilesCount = DB::table('submission_files')
                    ->where('file_id', '=', $submissionFileFileId)
                    ->count();

                // If the file is not used by another SubmissionFile, it can be deleted.
                if ($remainingSubmissionFilesCount == 0) {
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

                    if ($filesystem->has($submissionFilePath)) {
                        try {
                            $filesystem->delete($submissionFilePath);
                            error_log("A submission file that was attached to an orphaned note with ID ${noteId} at ${submissionFilePath} was successfully deleted.");
                        } catch (FilesystemException | UnableToDeleteFile $exception) {
                            $exceptionMessage = $exception->getMessage();
                            error_log("A submission file that was attached to an orphaned note with ID ${noteId} was found at ${submissionFilePath} but could not be deleted because of: ${exceptionMessage}.");
                        }
                    }

                    DB::table('files')
                        ->where('file_id', '=', $submissionFileFileId)
                        ->delete();
                }
            }

            error_log("Removing orphaned note entry ID ${noteId} with not existing query ${neQueryId}");
            DB::table('notes')
                ->where('note_id', '=', $noteId)
                ->delete();
        }
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
            $table->bigInteger('submission_file_id')->nullable(false)->change();
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
}
