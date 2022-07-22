<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8073_RemoveNotesWithoutQueriesAndRelatedObjects.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7706_AssociateTemplatesWithMailables
 * @brief Refactors relationship between Mailables and Email Templates
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use Illuminate\Support\Facades\DB;
use PKP\submissionFile\SubmissionFile;
use APP\core\Services;
use PKP\install\DowngradeNotSupportedException;
use PKP\config\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use PKP\file\FileManager;
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
        $orphanedIds = DB::table('notes AS n')
            ->leftJoin('queries AS q', 'n.assoc_id', '=', 'q.query_id')
            ->where('n.assoc_type', '=', self::ASSOC_TYPE_QUERY)
            ->whereNull('q.query_id')
            ->pluck('n.note_id', 'n.assoc_id');

        foreach ($orphanedIds as $neQueryId => $noteId) {
            error_log("Removing submission files that relates to the note entry ID ${noteId} which will be deleted as orphan object");
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

            foreach ($notesFileRows as $submissionFileRow) {
                $submissionFileId = $submissionFileRow->submissionFileId;
                $submissionFileFileId = $submissionFileRow->fileId;
                $submissionFilePath = $submissionFileRow->filePath;

                error_log("Removing submission file revisions that relate to the submission file entry ID ${submissionFileId} which will be deleted.");
                DB::table('submission_file_revisions')
                    ->where('submission_file_id', '=', $submissionFileId)
                    ->delete();

                error_log("Removing review round files that relate to the submission file entry ID ${submissionFileId} which will be deleted.");
                DB::table('review_round_files')
                    ->where('submission_file_id', '=', $submissionFileId)
                    ->delete();

                error_log("Removing review files that relate to the submission file entry ID ${submissionFileId} which will be deleted.");
                DB::table('review_files')
                    ->where('submission_file_id', '=', $submissionFileId)
                    ->delete();

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
                    error_log("A submission file was found at ${submissionFilePath}. Trying to delete it...");
                    try {
                        $filesystem->delete($submissionFilePath);
                        error_log("A submission file at ${submissionFilePath} was successfully deleted.");
                    } catch (FilesystemException | UnableToDeleteFile $exception) {
                        $exceptionMessage = $exception->getMessage();
                        error_log("A submission file was found at ${submissionFilePath} but could not be deleted because of: ${exceptionMessage}.");
                    }
                } else {
                    error_log("A submission file was expected but not found at ${submissionFilePath}.");
                }
                

                DB::table('submission_files')
                    ->where('submission_file_id', '=', $submissionFileId)
                    ->delete();

                DB::table('files')
                    ->where('file_id', '=', $submissionFileFileId)
                    ->delete();
            }

            error_log("Removing orphaned note entry ID ${noteId} with not existing query ${neQueryId}");
            DB::table('notes')
                ->where('note_id', '=', $noteId)
                ->delete();
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to removed data');
    }
}
