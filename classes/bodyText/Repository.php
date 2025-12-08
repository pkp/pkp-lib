<?php

/**
 * @file classes/bodyText/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage body text files.
 */

namespace PKP\bodyText;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Exception;
use PKP\context\Context;
use PKP\file\TemporaryFileManager;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class Repository
{
    /**
     * Map the BodyTextFile to an array including body text content and dependent files
     */
    public function map(BodyTextFile $bodyTextFile): array
    {
        $fileProps = [];

        if ($bodyTextFile->submissionFile) {
            $submission = Repo::submission()->get($bodyTextFile->submissionFile->getData('submissionId'));
            $schemaMap = Repo::submissionFile()->getSchemaMap($submission, []);

            $fileProps = $schemaMap->map($bodyTextFile->submissionFile);
        }

        if ($bodyTextFile->bodyTextContent) {
            $fileProps['bodyTextContent'] = $bodyTextFile->bodyTextContent;
        }

        if ($bodyTextFile->loadingContentError) {
            $fileProps['loadingContentError'] = $bodyTextFile->loadingContentError;
        }

        return $fileProps;
    }

    /**
     * Returns the SubmissionFile, if any, that corresponds to the body text contents of the given submission/publication
     */
    public function getBodyTextFile(int $publicationId): BodyTextFile
    {
        $submissionFile = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_BODY_TEXT])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId])
            ->getMany()
            ->first();

        return new BodyTextFile(
            $publicationId,
            $submissionFile,
        );
    }

    /**
     * Create or update the body text file for a publication
     */
    public function setBodyText(
        string $bodyText,
        int $publicationId,
    ): BodyTextFile {
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $existingBodyTextFile = $this->getBodyTextFile($publicationId);

        if ($existingBodyTextFile->submissionFile) {
            return $this->updateBodyTextFile($existingBodyTextFile->submissionFile, $bodyText, $submission);
        }

        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();

        return $this->createBodyTextFile($bodyText, $publication, $submission, $context, $user);
    }

    /**
     * Store body text content to file storage
     */
    protected function storeContent(string $bodyText, Submission $submission): int
    {
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'bodyText');

        if (!file_put_contents($temporaryFilename, $bodyText)) {
            throw new Exception('Unable to save body text!');
        }

        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $submission->getData('contextId'),
            $submission->getId()
        );

        return app()->get('file')->add(
            $temporaryFilename,
            $submissionDir . '/' . uniqid() . '.json'
        );
    }

    /**
     * Update existing body text file content
     */
    protected function updateBodyTextFile(
        SubmissionFile $submissionFile,
        string $bodyText,
        Submission $submission,
    ): BodyTextFile {
        $newFileId = $this->storeContent($bodyText, $submission);

        $oldFileId = $submissionFile->getData('fileId');
        Repo::submissionFile()->edit($submissionFile, ['fileId' => $newFileId]);
        app()->get('file')->delete($oldFileId);

        return $this->getBodyTextFile($submissionFile->getData('assocId'));
    }

    /**
     * Create new body text file
     */
    protected function createBodyTextFile(
        string $bodyText,
        Publication $publication,
        Submission $submission,
        Context $context,
        User $user,
    ): BodyTextFile {
        $fileId = $this->storeContent($bodyText, $submission);

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        $params = [
            'fileId' => $fileId,
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $user->getId(),
            'fileStage' => SubmissionFile::SUBMISSION_FILE_BODY_TEXT,
            'name' => [$primaryLocale => 'bodyText.json'],
            'assocType' => Application::ASSOC_TYPE_PUBLICATION,
            'assocId' => $publication->getId(),
        ];

        $errors = Repo::submissionFile()->validate(
            null,
            $params,
            $allowedLocales,
            $primaryLocale
        );

        if (!empty($errors)) {
            app()->get('file')->delete($fileId);
            throw new Exception(print_r($errors, true));
        }

        $submissionFile = Repo::submissionFile()->newDataObject($params);
        Repo::submissionFile()->add($submissionFile);

        return $this->getBodyTextFile($publication->getId());
    }

    /**
     * Get all valid file stages
     *
     * Valid file stages should be passed through
     * the hook SubmissionFile::fileStages.
     */
    public function getFileStages(): array
    {
        return [SubmissionFile::SUBMISSION_FILE_BODY_TEXT];
    }
}
