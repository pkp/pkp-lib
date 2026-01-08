<?php

/**
 * @file classes/submissionFile/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map submissionFiles to the properties defined in the submission file schema
 */

namespace PKP\submissionFile\maps;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\maps\Schema as BaseSchema;
use PKP\services\PKPSchemaService;
use PKP\submission\genre\Genre;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class Schema extends BaseSchema
{
    /**  */
    public Enumerable $collection;

    public Submission $submission;

    /**  */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION_FILE;

    /** @var Genre[] Associative array of file genres in this context by ID */
    public array $genres;

    public function __construct(Submission $submission, array $genres, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        $this->submission = $submission;
        $this->genres = $genres;
        parent::__construct($request, $context, $schemaService);
    }

    /**
     * Map a submission file
     *
     * Includes all properties in the submission file schema.
     */
    public function map(SubmissionFile $item): array
    {
        return $this->mapByProperties(
            $this->getProps(),
            $item,
            $this->getUploaderUsernames(collect([$item]))
        );
    }

    /**
     * Summarize a submission file
     *
     * Includes properties with the apiSummary flag in the submission file schema.
     */
    public function summarize(SubmissionFile $item): array
    {
        return $this->mapByProperties(
            $this->getSummaryProps(),
            $item,
            $this->getUploaderUsernames(collect([$item]))
        );
    }

    /**
     * Map a collection of submission files
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $uploaderUsernames = $this->getUploaderUsernames($collection);
        $this->collection = $collection;
        return $collection->map(function ($item) use ($uploaderUsernames) {
            return $this->mapByProperties(
                $this->getProps(),
                $item,
                $uploaderUsernames
            );
        });
    }

    /**
     * Summarize a collection of submission files
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $uploaderUsernames = $this->getUploaderUsernames($collection);
        $this->collection = $collection;
        return $collection->map(function ($item) use ($uploaderUsernames) {
            return $this->mapByProperties(
                $this->getSummaryProps(),
                $item,
                $uploaderUsernames
            );
        });
    }

    /**
     * Map schema properties of a submission file to an assoc array
     */
    protected function mapByProperties(array $props, SubmissionFile $submissionFile, array $uploaderUsernames): array
    {
        $output = [];
        foreach ($props as $prop) {
            if ($prop === '_href') {
                $output[$prop] = $this->getApiUrl(
                    'submissions/' . $submissionFile->getData('submissionId') . '/files/' . $submissionFile->getId(),
                    $this->context->getData('urlPath')
                );

                continue;
            }

            if ($prop === 'dependentFiles') {
                $dependentFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$submissionFile->getId()])
                    ->filterBySubmissionIds([$submissionFile->getData('submissionId')])
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
                    ->includeDependentFiles()
                    ->getMany();

                $output[$prop] = $this->summarizeMany($dependentFiles, $this->genres, $this->submission)->values();

                continue;
            }

            if ($prop === 'documentType') {
                $output[$prop] = app()->get('file')->getDocumentType($submissionFile->getData('mimetype'));

                continue;
            }

            if ($prop === 'genreId') {
                $genre = $this->getGenre($submissionFile);
                $output[$prop] = $genre ? $genre->getKey() : null;
                continue;
            }

            if ($prop === 'genreName') {
                $genre = $this->getGenre($submissionFile);
                $output[$prop] = $genre
                    ? $genre->getAttribute('name')
                    : null;
                continue;
            }

            if ($prop === 'genreIsDependent') {
                $genre = $this->getGenre($submissionFile);
                $output[$prop] = $genre ? (bool) $genre->dependent : null;
                continue;
            }

            if ($prop === 'genreIsSupplementary') {
                $genre = $this->getGenre($submissionFile);
                $output[$prop] = $genre ? (bool) $genre->supplementary : null;
                continue;
            }

            if ($prop === 'revisions') {
                $files = [];

                $revisions = Repo::submissionFile()->getRevisions($submissionFile->getId());

                foreach ($revisions as $revision) {
                    if ($revision->fileId === $submissionFile->getData('fileId')) {
                        continue;
                    }

                    $files[] = [
                        'documentType' => app()->get('file')->getDocumentType($revision->mimetype),
                        'fileId' => $revision->fileId,
                        'mimetype' => $revision->mimetype,
                        'path' => $revision->path,
                        'url' => $this->request->getDispatcher()->url(
                            $this->request,
                            Application::ROUTE_COMPONENT,
                            $this->context->getData('urlPath'),
                            'api.file.FileApiHandler',
                            'downloadFile',
                            null,
                            [
                                'fileId' => $revision->fileId,
                                'submissionFileId' => $submissionFile->getId(),
                                'submissionId' => $submissionFile->getData('submissionId'),
                                'stageId' => Repo::submissionFile()->getWorkflowStageId($submissionFile),
                            ]
                        ),
                    ];
                }

                $output[$prop] = $files;

                continue;
            }

            if ($prop === 'uploaderUserName') {
                $userId = $submissionFile->getData('uploaderUserId');
                $output[$prop] = $userId ? $uploaderUsernames[$userId] : ''; // userId can be null, see pkp/pkp-lib#8493

                continue;
            }

            if ($prop === 'url') {
                $output[$prop] = $this->request->getDispatcher()->url(
                    $this->request,
                    Application::ROUTE_COMPONENT,
                    $this->context->getData('urlPath'),
                    'api.file.FileApiHandler',
                    'downloadFile',
                    null,
                    [
                        'submissionFileId' => $submissionFile->getId(),
                        'submissionId' => $submissionFile->getData('submissionId'),
                        'stageId' => Repo::submissionFile()->getWorkflowStageId($submissionFile),
                    ]
                );

                continue;
            }

            $output[$prop] = $submissionFile->getData($prop);
        }

        $locales = $this->submission->getPublicationLanguages($this->context->getSupportedSubmissionMetadataLocales(), $submissionFile->getLanguages());

        $output = $this->schemaService->addMissingMultilingualValues(
            $this->schema,
            $output,
            $locales
        );

        ksort($output);

        return $this->withExtensions($output, $submissionFile);
    }

    protected function getGenre(SubmissionFile $submissionFile): ?Genre
    {
        return $this->genres[$submissionFile->getData('genreId')] ?? null;
    }

    /**
     * Given a collection of SubmissionFile objects, get an associative array of uploader [user ID => username]
     */
    protected function getUploaderUsernames(Enumerable $collection): array
    {
        $userIds = $collection->map(fn (SubmissionFile $submissionFile) => $submissionFile->getUploaderUserId())
            ->unique()->filter()->toArray();
        return $userIds ? Repo::user()->getCollector()->filterByUserIds($userIds)->getUsernames()->all() : [];
    }
}

