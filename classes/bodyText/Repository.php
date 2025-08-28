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
 * @brief A repository to find and manage body tesxt files.
 */

namespace PKP\bodyText;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\submissionFile\SubmissionFile;

class Repository
{
    /**
     * Map the BodyTextFile to an array including body text content and dependent files
     */
    public function map(BodyTextFile $bodyTextFile): array
    {
        $fileProps = [];

        if ($bodyTextFile->submissionFile) {
            $submission = Repo::submission()->get($bodyTextFile->submissionId);
            $schemaMap = Repo::submissionFile()->getSchemaMap($submission, $bodyTextFile->genres);

            // Use map() to get full properties including dependentFiles
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
    public function getBodyTextFile(int $publicationId, ?int $submissionId = null, array $genres): ?BodyTextFile
    {
        $submissionFileQuery = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_BODY_TEXT])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId]);

        if ($submissionId) {
            $submissionFileQuery = $submissionFileQuery->filterBySubmissionIds([$submissionId]);
        }

        $submissionFile = $submissionFileQuery
            ->getMany()
            ->first();

        return new BodyTextFile(
            $publicationId,
            $submissionId,
            $submissionFile,
            $genres
        );
    }

    /**
     * Create or update the body text file for a publication
     */
    public function setBodyText(
        string $bodyText,
        int $publicationId,
        ?int $submissionId = null,
        int $type = SubmissionFile::SUBMISSION_FILE_BODY_TEXT,
        array $params = []
    ): BodyTextFile {
        $publication = Repo::publication()->get($publicationId, $submissionId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();

        /** @var GenreDAO */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genresIterator = $genreDao->getEnabledByContextId($context->getId());
        $genres = $genresIterator->toArray();

        // Check if body text file already exists
        $existingBodyTextFile = $this->getBodyTextFile($publicationId, $submission->getId(), $genres);

        if ($existingBodyTextFile->submissionFile) {
            // Update existing file
            return $this->updateBodyTextFile($existingBodyTextFile->submissionFile, $bodyText, $submission, $genres);
        }

        // Create new file
        return $this->createBodyTextFile($bodyText, $publication, $submission, $context, $user, $genres, $type, $params);
    }

    /**
     * Store body text content to file storage
     *
     * @return int The new file ID
     */
    protected function storeContent(string $bodyText, $submission): int
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
        $submission,
        array $genres
    ): BodyTextFile {
        $newFileId = $this->storeContent($bodyText, $submission);

        $oldFileId = $submissionFile->getData('fileId');
        Repo::submissionFile()->edit($submissionFile, ['fileId' => $newFileId]);
        app()->get('file')->delete($oldFileId);

        return $this->getBodyTextFile(
            $submissionFile->getData('assocId'),
            $submission->getId(),
            $genres
        );
    }

    /**
     * Create new body text file
     */
    protected function createBodyTextFile(
        string $bodyText,
        $publication,
        $submission,
        $context,
        $user,
        array $genres,
        int $type,
        array $params
    ): BodyTextFile {
        $fileId = $this->storeContent($bodyText, $submission);

        $params['fileId'] = $fileId;
        $params['submissionId'] = $submission->getId();
        $params['uploaderUserId'] = $user->getId();
        $params['fileStage'] = $type;

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');
        $params['name'] = [$primaryLocale => 'bodyText.json'];

        if (empty($params['genreId']) && count($genres) === 1) {
            $params['genreId'] = reset($genres)->getId();
        }

        $params['assocType'] = Application::ASSOC_TYPE_PUBLICATION;
        $params['assocId'] = $publication->getId();

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

        return $this->getBodyTextFile($publication->getId(), $submission->getId(), $genres);
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
