<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13392_RemoveFilesOfDeletedNotes.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13392_RemoveFilesOfDeletedNotes
 *
 * @brief Remove the submission files that were attached to notes (discussions) that have been deleted.
 *   Deleting a discussion did not delete the files attached to its notes, see https://github.com/pkp/pkp-lib/issues/13392
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use PKP\config\Config;
use PKP\migration\Migration;

class I13392_RemoveFilesOfDeletedNotes extends Migration
{
    private const ASSOC_TYPE_SUBMISSION_FILE = 0x0000203; // PKPApplication::ASSOC_TYPE_SUBMISSION_FILE
    private const ASSOC_TYPE_NOTE = 0x0000208; // PKPApplication::ASSOC_TYPE_NOTE
    private const FILE_MODE_MASK = 0666; // FileManager::FILE_MODE_MASK
    private const DIRECTORY_MODE_MASK = 0777; // FileManager::DIRECTORY_MODE_MASK
    private const CHUNK_SIZE = 500;

    public function up(): void
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

        // The submission files are removed in each round, so the next round finds the next ones
        while (($submissionFileIds = $this->getStrandedSubmissionFileIds())->isNotEmpty()) {
            $submissionFileIds = $this->addDependentSubmissionFileIds($submissionFileIds);

            // The revisions are removed together with the submission files (foreign key cascade),
            // so get the files they use before that
            $fileIds = DB::table('submission_files')
                ->whereIn('submission_file_id', $submissionFileIds)
                ->pluck('file_id')
                ->merge(
                    DB::table('submission_file_revisions')
                        ->whereIn('submission_file_id', $submissionFileIds)
                        ->pluck('file_id')
                )
                ->unique();

            DB::table('submission_files')
                ->whereIn('submission_file_id', $submissionFileIds)
                ->delete();
            $this->_installer->log('Removed the submission files ' . $submissionFileIds->implode(', ') . ' that were attached to deleted notes.');

            $this->deleteUnusedFiles($filesystem, $fileIds);
        }
    }

    /**
     * Get the IDs of a chunk of the submission files that are attached to notes that do not exist
     *
     * @return Collection<int>
     */
    private function getStrandedSubmissionFileIds(): Collection
    {
        return DB::table('submission_files as sf')
            ->leftJoin('notes as n', 'n.note_id', '=', 'sf.assoc_id')
            ->where('sf.assoc_type', '=', self::ASSOC_TYPE_NOTE)
            ->whereNull('n.note_id')
            ->limit(self::CHUNK_SIZE)
            ->pluck('sf.submission_file_id');
    }

    /**
     * Add the IDs of the submission files that depend on the given submission files
     *
     * @param Collection<int> $submissionFileIds
     *
     * @return Collection<int>
     */
    private function addDependentSubmissionFileIds(Collection $submissionFileIds): Collection
    {
        $parentIds = $submissionFileIds;
        while ($parentIds->isNotEmpty()) {
            $parentIds = DB::table('submission_files')
                ->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE)
                ->whereIn('assoc_id', $parentIds)
                ->whereNotIn('submission_file_id', $submissionFileIds)
                ->pluck('submission_file_id');
            $submissionFileIds = $submissionFileIds->merge($parentIds);
        }

        return $submissionFileIds;
    }

    /**
     * Delete the given files, if no submission file or revision uses them anymore
     *
     * @param Collection<int> $fileIds
     */
    private function deleteUnusedFiles(Filesystem $filesystem, Collection $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $isUsed = DB::table('submission_files')->where('file_id', '=', $fileId)->exists()
                || DB::table('submission_file_revisions')->where('file_id', '=', $fileId)->exists();
            if ($isUsed) {
                continue;
            }

            $path = DB::table('files')->where('file_id', '=', $fileId)->value('path');
            if ($path !== null && $filesystem->has($path)) {
                try {
                    $filesystem->delete($path);
                    $this->_installer->log("The file {$path} of a submission file that was attached to a deleted note was successfully deleted.");
                } catch (FilesystemException | UnableToDeleteFile $exception) {
                    $exceptionMessage = $exception->getMessage();
                    $this->_installer->log("The file {$path} of a submission file that was attached to a deleted note could not be deleted because of: {$exceptionMessage}.");
                }
            }

            DB::table('files')->where('file_id', '=', $fileId)->delete();
        }
    }

    public function down(): void
    {
        // The removed files cannot be restored
    }
}
