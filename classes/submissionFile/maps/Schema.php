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
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\maps\Schema as BaseSchema;
use PKP\services\PKPSchemaService;
use PKP\submission\genre\Genre;
use PKP\submissionFile\SubmissionFile;

class Schema extends BaseSchema
{
    /**  */
    public Enumerable $collection;

    /**  */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION_FILE;

    /** @var Genre[] File genres in this context */
    public array $genres;

    public function __construct(Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
    }

    /**
     * Map a submission file
     *
     * Includes all properties in the submission file schema.
     *
     * @param Genre[] $genres
     */
    public function map(SubmissionFile $item, array $genres): array
    {
        $this->genres = $genres;
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a submission file
     *
     * Includes properties with the apiSummary flag in the submission file schema.
     *
     * @param Genre[] $genres
     */
    public function summarize(SubmissionFile $item, array $genres): array
    {
        $this->genres = $genres;
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of submission files
     *
     * @see self::map
     *
     * @param Genre[] $genres
     */
    public function mapMany(Enumerable $collection, array $genres): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($genres) {
            return $this->map($item, $genres);
        });
    }

    /**
     * Summarize a collection of submission files
     *
     * @see self::summarize
     *
     * @param Genre[] $genres
     */
    public function summarizeMany(Enumerable $collection, array $genres): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($genres) {
            return $this->summarize($item, $genres);
        });
    }

    /**
     * Map schema properties of a submission file to an assoc array
     */
    protected function mapByProperties(array $props, SubmissionFile $submissionFile): array
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

                $output[$prop] = $this->summarizeMany($dependentFiles, $this->genres)->values();

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
                $user = !is_null($userId) ? Repo::user()->get($userId) : null; // userId can be null, see pkp/pkp-lib#8493
                $output[$prop] = $user?->getUsername() ?? '';

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

        $locales = Repo::submission()->get($submissionFile->getData('submissionId'))->getPublicationLanguages($this->context->getSupportedSubmissionMetadataLocales(), $submissionFile->getLanguages());

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
        foreach ($this->genres as $genre) {
            if ($genre->getKey() === $submissionFile->getData('genreId')) {
                return $genre;
            }
        }
        return null;
    }
}
