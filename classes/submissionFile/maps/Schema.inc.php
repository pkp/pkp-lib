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
use APP\core\Services;
use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\core\maps\Schema as BaseSchema;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\SubmissionFile;

class Schema extends BaseSchema
{
    /** @var Enumerable */
    public Enumerable $collection;

    /** @var string */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION_FILE;

    /**
     * Map a submission file
     *
     * Includes all properties in the submission file schema.
     */
    public function map(SubmissionFile $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a submission file
     *
     * Includes properties with the apiSummary flag in the submission file schema.
     */
    public function summarize(SubmissionFile $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of submission files
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Summarize a collection of submission files
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->summarize($item);
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

                $output[$prop] = $this->summarizeMany($dependentFiles)->values();

                continue;
            }

            if ($prop === 'documentType') {
                $output[$prop] = Services::get('file')->getDocumentType($item->getData('mimetype'));

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
