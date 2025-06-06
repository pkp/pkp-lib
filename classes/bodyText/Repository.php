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
     * Summarize the BodyTextFile along with the body text content
     */
    public function summarize(BodyTextFile $bodyTextFile): array
    {
        $fileProps = [];
        if ($bodyTextFile->submissionFile) {
            $fileProps = Repo::submissionFile()
                ->getSchemaMap()
                ->summarize($bodyTextFile->submissionFile, $bodyTextFile->genres);
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
     * Base function that will add a new body text file
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

        // If no genre has been set and there is only one genre possible, set it automatically
        /** @var GenreDAO */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'bodyText');
        if (!file_put_contents($temporaryFilename, $bodyText)) {
            throw new \Exception('Unable to save body text!');
        }

        $submissionDir = Repo::submissionFile()
            ->getSubmissionDir(
                $submission->getData('contextId'),
                $submission->getId()
            );

        $fileId = app()->get('file')->add(
            $temporaryFilename,
            $submissionDir . '/' . uniqid() . '.txt'
        );

        $params['fileId'] = $fileId;
        $params['submissionId'] = $submission->getId();
        $params['uploaderUserId'] = $user->getId();
        $params['fileStage'] = $type;

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');
        $params['name'] = [$primaryLocale => 'bodyText'];

        if (empty($params['genreId'])) {

            [$firstGenre, $secondGenre] = [$genres->next(), $genres->next()];
            if ($firstGenre && !$secondGenre) {
                $params['genreId'] = $firstGenre->getId();
            }
        }

        $params['assocType'] = Application::ASSOC_TYPE_PUBLICATION;
        $params['assocId'] = $publication->getId();

        $errors = Repo::submissionFile()
            ->validate(
                null,
                $params,
                $allowedLocales,
                $primaryLocale
            );

        if (!empty($errors)) {
            app()->get('file')->delete($fileId);
            throw new Exception(print_r($errors, true));
        }

        $submissionFile = Repo::submissionFile()
            ->newDataObject($params);

        $submissionFileId = Repo::submissionFile()
            ->add($submissionFile);

        $bodyTextFile = Repo::bodyText()
            ->getBodyTextFile($publication->getId(), $submission->getId(), $genres->toArray());

        return $bodyTextFile;
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
