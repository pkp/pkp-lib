<?php
/**
 * @file classes/submissionFile/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submissionFile
 *
 * @brief Map submissionFiles to the properties defined in the submission file schema
 */

namespace PKP\submissionFile\maps;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\maps\Schema as BaseSchema;
use PKP\services\PKPSchemaService;
use PKP\submission\Genre;
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
    protected function mapByProperties(array $props, SubmissionFile $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            if ($prop === '_href') {
                $output[$prop] = $this->getApiUrl(
                    'submissions/' . $item->getData('submissionId') . '/files/' . $item->getId(),
                    $this->context->getData('urlPath')
                );

                continue;
            }

            if ($prop === 'dependentFiles') {
                $collector = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$item->getId()])
                    ->filterBySubmissionIds([$item->getData('submissionId')])
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
                    ->includeDependentFiles();

                $dependentFiles = Repo::submissionFile()->getMany($collector);

                $output[$prop] = $this->summarizeMany($dependentFiles, $this->genres)->values();

                continue;
            }

            if ($prop === 'documentType') {
                $output[$prop] = Services::get('file')->getDocumentType($item->getData('mimetype'));

                continue;
            }

            if ($prop === 'genreIsPrimary') {
                $output[$prop] = false;
                foreach ($this->genres as $genre) {
                    if ($genre->getId() === $item->getData('genreId')) {
                        $output[$prop] = !$genre->getSupplementary() && !$genre->getDependent();
                        break;
                    }
                }
                continue;
            }

            if ($prop === 'genreName') {
                foreach ($this->genres as $genre) {
                    if ($genre->getId() === $item->getData('genreId')) {
                        $output[$prop] = $genre->getData('name');
                        break;
                    }
                }
                continue;
            }

            if ($prop === 'revisions') {
                $files = [];

                $revisions = Repo::submissionFile()->getRevisions($item->getId());

                foreach ($revisions as $revision) {
                    if ($revision->fileId === $item->getData('fileId')) {
                        continue;
                    }

                    $files[] = [
                        'documentType' => Services::get('file')->getDocumentType($revision->mimetype),
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
                                'submissionFileId' => $item->getId(),
                                'submissionId' => $item->getData('submissionId'),
                                'stageId' => Repo::submissionFile()->getWorkflowStageId($item),
                            ]
                        ),
                    ];
                }

                $output[$prop] = $files;

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
                        'submissionFileId' => $item->getId(),
                        'submissionId' => $item->getData('submissionId'),
                        'stageId' => Repo::submissionFile()->getWorkflowStageId($item),
                    ]
                );

                continue;
            }

            $output[$prop] = $item->getData($prop);
        }

        $output = $this->schemaService->addMissingMultilingualValues(
            $this->schema,
            $output,
            $this->context->getSupportedFormLocales()
        );

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
