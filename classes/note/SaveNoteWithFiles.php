<?php

/**
 * @file classes/core/traits/ModelWithSettings.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ModelWithSettings
 *
 * @ingroup core_traits
 *
 * @brief A trait for Eloquent Model classes that can be saved together with associated files, i.e. task or note
 *
 */

namespace PKP\note;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\editorialTask\EditorialTask;
use PKP\facades\Locale;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileDAO;
use PKP\submissionFile\SubmissionFile;

trait SaveNoteWithFiles
{
    // Allow filling temporary and submission file IDs to upload them and associate with the task
    public const ATTRIBUTE_TEMPORARY_FILE_IDS = 'temporaryFileIds';
    public const ATTRIBUTE_SUBMISSION_FILE_IDS = 'submissionFileIds';

    /**
     * @var ?array<TemporaryFile> Temporary files ID to be uploaded and associated with the task
     */
    protected ?array $temporaryFiles = null;

    /**
     * @var ?array<SubmissionFile> Submission file IDs to be associated with the task
     */
    protected ?array $submissionFiles = null;

    /**
     * @throws Exception
     */
    protected function manageFiles(?Note $headnote = null): array
    {
        $existingFiles = Repo::submissionFile()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_QUERY, [$this->id])
            ->getMany()
            ->toArray();

        $newFileIds = $this->saveTemporaryFiles($headnote ?? $this);
        $attachedFileIds = $this->attachSubmissionFiles($existingFiles, $headnote);

        // Remove previously associated submission files, including situation when any of temporary or submission files attribute was passed as empty array
        foreach ($existingFiles as $existingFile) {
            if (in_array($existingFile->getId(), $attachedFileIds)) {
                continue;
            }
            // Using Repo method to ensure related files are also removed
            Repo::submissionFile()->delete($existingFile);
        }

        return array_merge($newFileIds, $attachedFileIds);
    }

    /**
     * Fill temporary files by their IDs when corresponding attribute is passed to the model
     */
    protected function fillTemporaryFiles(array $attributes): array
    {
        $temporaryFileIds = $attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS];
        $dao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $dao */
        $this->temporaryFiles = $dao->getTemporaryFiles($temporaryFileIds)->toArray();
        unset($attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS]);

        return $attributes;
    }

    /**
     * Fill submission files by their IDs when corresponding attribute is passed to the model
     */
    protected function fillSubmissionFiles(array $attributes): array
    {
        $submissionFileIds = $attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS];
        $this->submissionFiles = Repo::submissionFile()->getCollector()
            ->filterByAssoc($this->assocType, $this->assocId)
            ->filterBySubmissionFileIds($submissionFileIds)
            ->getMany()
            ->toArray();
        unset($attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS]);

        return $attributes;
    }

    /**
     * Handle temporary files associated with the task.
     *
     * @return array<int> IDs of created submission files
     */
    protected function saveTemporaryFiles(Note $note = null): array
    {
        if (empty($this->temporaryFiles)) {
            return [];
        }

        if (!in_array($this->assocType, [PKPApplication::ASSOC_TYPE_SUBMISSION, PKPApplication::ASSOC_TYPE_QUERY])) {
            return [];
        }

        $noteId = $note?->id ?? $this->id;

        if ($this->assocType == PKPApplication::ASSOC_TYPE_SUBMISSION) {
            $submission = Repo::submission()->get((int) $this->assocId);
        } else {
            if ($this->assoc && is_a($this->assoc, EditorialTask::class)) {
                $submission = Repo::submission()->get((int) $this->assoc->assocId);
            } else {
                // Don't support file uploads with other entities associated with notes except for editorial tasks.
                throw new Exception('Cannot find submission for the note with assocType ' . $this->assocType);
            }
        }

        $createdFileIds = [];
        foreach ($this->temporaryFiles as $temporaryFile) {

            // Save to files
            $extension = pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION);
            $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
            $fileId = app()->get('file')->add(
                $temporaryFile->getFilePath(),
                $submissionDir . '/' . uniqid() . '.' . $extension
            );
            if (!$fileId) {
                throw new Exception('Failed to save file from temporary file ID ' . $temporaryFile->getId());
            }

            // Save to submission files
            $submissionFile = Repo::submissionFile()->newDataObject([
                'fileId' => $fileId,
                'name' => [
                    Locale::getLocale() => $temporaryFile->getData('originalFileName') ?? $temporaryFile->getData('fileName'),
                ],
                'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
                'submissionId' => $submission->getId(),
                'uploaderUserId' => $temporaryFile->getUserId(),
                'assocType' => Application::ASSOC_TYPE_NOTE,
                'assocId' => $noteId,
            ]);

            $submissionFileId = Repo::submissionFile()->add($submissionFile);
            if (!$submissionFileId) {
                throw new Exception('Failed to save submission file from temporary file ID ' . $temporaryFile->getId());
            }

            $createdFileIds[] = $submissionFileId;
            $removed = unlink($temporaryFile->getFilePath());

            // Delete temporary file
            if ($removed) {
                DB::table('temporary_files')
                    ->where('file_id', '=', $temporaryFile->getId())
                    ->delete();
                continue;
            }

            trigger_error('Failed to remove temporary file with ID ' . $temporaryFile->getId() . ' from a server', E_USER_WARNING);
        }

        return $createdFileIds;
    }

    /**
     * Attach existing submission files to the task.
     *
     * @param array<SubmissionFile> $existingFiles files already attached to the task
     * @param ?Note $note optional Note to attach files to; by default it's a current Model instance
     *
     * @return array<int> IDs of attached submission files
     */
    protected function attachSubmissionFiles(array $existingFiles, ?Note $note = null): array
    {
        if (empty($this->submissionFiles)) {
            return [];
        }

        $noteId = $note?->id ?? $this->id;

        $attachedFileIds = [];

        foreach ($this->submissionFiles as $submissionFile) { /** @var SubmissionFile $submissionFile */
            // If the submission file is already attached, skip it
            if (Arr::first($existingFiles, fn (SubmissionFile $file, int $key) => $file->getId() == $submissionFile->getId())) {
                $attachedFileIds[] = $submissionFile->getId();
                continue;
            }

            $newSubmissionFile = Repo::submissionFile()->newDataObject(array_merge($submissionFile->getAllData(), [
                'assocType' => PKPApplication::ASSOC_TYPE_NOTE,
                'assocId' => $noteId,
                'sourceSubmissionFileId' => $submissionFile->getId(),
            ]));

            Repo::submissionFile()->add($newSubmissionFile);
        }

        return $attachedFileIds;
    }
}
