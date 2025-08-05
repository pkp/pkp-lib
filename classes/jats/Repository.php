<?php

/**
 * @file classes/jats/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage JATS files.
 */

namespace PKP\jats;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\jatsTemplate\classes\Article;
use Exception;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\jats\exceptions\UnableToCreateJATSContentException;
use PKP\submissionFile\SubmissionFile;
use Throwable;

class Repository
{
    /**
     * Summarize the JatsFile along with the jatsContent
     */
    public function summarize(JatsFile $jatsFile): array
    {
        $fileProps = [];
        if (!$jatsFile->isDefaultContent) {
            $fileProps = Repo::submissionFile()
                ->getSchemaMap()
                ->summarize($jatsFile->submissionFile, $jatsFile->genres);
        }

        if ($jatsFile->jatsContent) {
            $fileProps['jatsContent'] = $jatsFile->jatsContent;
        }

        $fileProps['isDefaultContent'] = $jatsFile->isDefaultContent;

        if ($jatsFile->loadingContentError) {
            $fileProps['loadingContentError'] = $jatsFile->loadingContentError;
        }

        return $fileProps;
    }

    /**
     * Returns the SubmissionFile, if any, that corresponds to the JATS contents of the given submission/publication
     */
    public function getJatsFile(int $publicationId, ?int $submissionId = null, array $genres): ?JatsFile
    {
        $submissionFileQuery = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_JATS])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId]);

        if ($submissionId) {
            $submissionFileQuery = $submissionFileQuery->filterBySubmissionIds([$submissionId]);
        }

        $submissionFile = $submissionFileQuery
            ->getMany()
            ->first();

        return new JatsFile(
            $publicationId,
            $submissionId,
            $submissionFile,
            $genres
        );
    }

    /**
     * Returns the name of the file that will contain the default JATS content
     */
    public function getDefaultJatsFileName(int $publicationId): string
    {
        return 'jats-' . $publicationId . '-' . date('Ymd-His') . '.xml';
    }

    /**
     * Creates the default JATS XML Content from the given submission/publication metadata
     *
     * @throws \PKP\jats\exceptions\UnableToCreateJATSContentException If the default JATS creation fails
     */
    public function createDefaultJatsContent(int $publicationId, ?int $submissionId = null): string
    {
        $publication = Repo::publication()->get($publicationId, $submissionId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $context = app()->get('context')->get($submission->getData('contextId'));
        $section = $submission->getSectionId() ? Repo::section()->get($submission->getSectionId()) : null;

        $issue = null;
        if ($publication->getData('issueId')) {
            $issue = Repo::issue()->get($publication->getData('issueId'));
        }

        try {
            $exportXml = $this->convertSubmissionToJatsXml($submission, $context, $section, $issue, $publication, Application::get()->getRequest());
        } catch (Throwable $e) {
            throw new UnableToCreateJATSContentException($e);
        }

        return $exportXml;
    }

    /**
     * Base function that will add a new JATS file
     */
    public function addJatsFile(
        string $fileTmpName,
        string $fileName,
        int $publicationId,
        ?int $submissionId = null,
        int $type = SubmissionFile::SUBMISSION_FILE_JATS,
        array $params = []
    ): JatsFile {
        $publication = Repo::publication()->get($publicationId, $submissionId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();

        // If no genre has been set and there is only one genre possible, set it automatically
        $genres = Repo::genre()->getEnabledByContextId($context->getId());


        $existingJatsFile = $this->getJatsFile($publicationId, $submissionId, $genres->all());
        if (!$existingJatsFile->isDefaultContent) {
            throw new Exception('A JATS file already exists');
        }

        $fileManager = new FileManager();
        $extension = $fileManager->parseFileExtension($fileName);

        $submissionDir = Repo::submissionFile()
            ->getSubmissionDir(
                $submission->getData('contextId'),
                $submission->getId()
            );

        $fileId = app()->get('file')->add(
            $fileTmpName,
            $submissionDir . '/' . uniqid() . '.' . $extension
        );

        $params['fileId'] = $fileId;
        $params['submissionId'] = $submission->getId();
        $params['uploaderUserId'] = $user->getId();
        $params['fileStage'] = $type;

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        $params['name'] = null;
        $params['name'][$primaryLocale] = $fileName;

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
            throw new Exception('' . implode(', ', $errors));
        }

        $submissionFile = Repo::submissionFile()
            ->newDataObject($params);

        $submissionFileId = Repo::submissionFile()
            ->add($submissionFile);

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), $submission->getId(), $genres->all());

        return $jatsFile;
    }

    /**
     * Given a submission and a publication this function returns the JATS XML contents provided by the
     * submission/publication metadata
     *
     * @throws \PKP\jats\exceptions\UnableToCreateJATSContentException If the default JATS creation fails
     */
    protected function convertSubmissionToJatsXml($submission, $journal, $section, $issue, $publication, $request): string
    {
        if (!class_exists(\APP\plugins\generic\jatsTemplate\classes\Article::class)) {
            throw new UnableToCreateJATSContentException();
        }

        $articleJats = new Article();

        $articleJats->preserveWhiteSpace = false;
        $articleJats->formatOutput = true;

        $articleJats->convertSubmission($submission, $journal, $section, $issue, $publication, $request);

        $formattedXml = $articleJats->saveXML();

        return $formattedXml;
    }

    /**
     * Get all valid file stages
     *
     * Valid file stages should be passed through
     * the hook SubmissionFile::fileStages.
     */
    public function getFileStages(): array
    {
        return [SubmissionFile::SUBMISSION_FILE_JATS];
    }
}
