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
use APP\submission\Submission;
use APP\facades\Repo;
use APP\plugins\generic\jatsTemplate\classes\Article;
use Exception;
use Illuminate\Support\Facades\Cache;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\jats\exceptions\UnableToCreateJATSContentException;
use PKP\submission\GenreDAO;
use PKP\submissionFile\SubmissionFile;
use Throwable;

class Repository
{
    public const JATS_FILE_CACHE_LIFETIME = 24 * 60 * 60; // 24 hours
    
    /**
     * Summarize the JatsFile along with the jatsContent
     */
    public function summarize(JatsFile $jatsFile, Submission $submission): array
    {
        $fileProps = [];
        if (!$jatsFile->isDefaultContent) {
            $fileProps = Repo::submissionFile()
                ->getSchemaMap($submission, $jatsFile->genres)
                ->summarize($jatsFile->submissionFile);
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
     * Add or update a JATS file for a publication.
     *
     * If a JATS file already exists, this creates a new revision by updating
     * the existing record (which automatically creates a revision record) .
     * If no JATS file exists, creates a new one.
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

        /** @var GenreDAO */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        // Check if a JATS file already exists
        $existingJatsFile = $this->getJatsFile($publicationId, $submissionId, $genres->toArray());

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

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        // REVISION: If JATS file already exists, UPDATE it which also creates revision automatically
        if (!$existingJatsFile->isDefaultContent && $existingJatsFile->submissionFile) {
            Repo::submissionFile()->edit(
                $existingJatsFile->submissionFile,
                [
                    'fileId' => $fileId,
                    'uploaderUserId' => $user->getId(),
                    'name' => [$primaryLocale => $fileName],
                ]
            );
        } else {
            // FIRST UPLOAD: Create new submission file record
            $params['fileId'] = $fileId;
            $params['submissionId'] = $submission->getId();
            $params['uploaderUserId'] = $user->getId();
            $params['fileStage'] = $type;
            $params['name'] = [$primaryLocale => $fileName];

            if (empty($params['genreId'])) {
                [$firstGenre, $secondGenre] = [$genres->next(), $genres->next()];
                if ($firstGenre && !$secondGenre) {
                    $params['genreId'] = $firstGenre->getId();
                }
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
        }

        // cache should be cleared when file changes
        $this->clearPublicJatsCache($publicationId);

        // Return fresh JatsFile
        return $this->getJatsFile($publication->getId(), $submission->getId(), $genres->toArray());
    }

    /**
     * Delete the JATS file for a publication.
     *
     * @param int $publicationId The publication ID
     * @param int|null $submissionId Optional submission ID filter
     */
    public function deleteJatsFile(int $publicationId, ?int $submissionId = null): void
    {
        $query = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_JATS])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId]);

        if ($submissionId) {
            $query = $query->filterBySubmissionIds([$submissionId]);
        }

        $jatsFile = $query->getMany()->first();

        if ($jatsFile) {
            // Delete the single record (revisions cascade via FK)
            Repo::submissionFile()->delete($jatsFile);
        }

        // cache should be cleared when file deleted
        $this->clearPublicJatsCache($publicationId);
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

    /**
     * Get JATS content for public download with given cache life time as defined
     *
     * This method caches the JATS XML content to prevent server overload
     * from repeated public requests. Cache is invalidated when:
     * - JATS file is uploaded (addJatsFile)
     * - JATS file is deleted (deleteJatsFile)
     * - Visibility setting changes (via controller)
     *
     * @param int $publicationId The publication ID
     * @param int $submissionId Optional submission ID filter
     * @return string|null The JATS XML content or null if unavailable
     */
    public function getPublicJatsContent(int $publicationId, int $submissionId): ?string
    {
        $cacheKey = $this->getPublicJatsCacheKey($publicationId);

        return Cache::remember($cacheKey, static::JATS_FILE_CACHE_LIFETIME, function () use ($publicationId, $submissionId) {
            $submission = Repo::submission()->get($submissionId);
            
            /** @var \PKP\context\Context $context */
            $context = app()->get('context')->get($submission->getData('contextId'));
            
            $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
            $genres = $genreDao->getEnabledByContextId($context->getId());

            $jatsFile = $this->getJatsFile($publicationId, $submissionId, $genres->toArray());

            return $jatsFile?->jatsContent;
        });
    }

    /**
     * Clear the public JATS cache for a publication.
     *
     * Should be called when:
     * - JATS file content changes (upload/delete)
     * - Visibility setting changes
     */
    public function clearPublicJatsCache(int $publicationId): void
    {
        Cache::forget($this->getPublicJatsCacheKey($publicationId));
    }

    /**
     * Get the cache key for public JATS content.
     */
    protected function getPublicJatsCacheKey(int $publicationId): string
    {
        return "jats-public-content-{$publicationId}";
    }
}
