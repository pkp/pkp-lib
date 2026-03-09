<?php

/**
 * @file classes/citation/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @ingroup citation
 *
 * @brief A repository to find and manage citations.
 */

namespace PKP\citation;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\filter\CitationListTokenizerFilter;
use PKP\jobs\citation\CrossrefJob;
use PKP\jobs\citation\ExtractPidsJob;
use PKP\jobs\citation\IsProcessedJob;
use PKP\jobs\citation\OpenAlexJob;
use PKP\jobs\citation\OrcidJob;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;
use Throwable;

class Repository
{
    public DAO $dao;

    /** The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<Citation> */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Citation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $publicationId = null): bool
    {
        return $this->dao->exists($id, $publicationId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $publicationId = null): ?Citation
    {
        return $this->dao->get($id, $publicationId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping citations to their schema.
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a citation
     *
     * Perform validation checks on data used to add or edit a citation.
     *
     * @param Citation|null $citation Citation being edited. Pass `null` if creating a new citation
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Citation::validate [[&$errors, $citation, $props]]
     */
    public function validate(?Citation $citation, array $props): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, [])
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $citation,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            [],
            ''
        );

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Citation::validate', [&$errors, $citation, $props]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Citation $citation): int
    {
        $id = $this->dao->insert($citation);
        Hook::call('Citation::add', [$citation]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Citation $citation, array $params): void
    {
        $newCitation = clone $citation;
        $newCitation->setAllData(array_merge($newCitation->_data, $params));
        Hook::call('Citation::edit', [$newCitation, $citation, $params]);
        $this->dao->update($newCitation);
    }

    /** @copydoc DAO::delete() */
    public function delete(Citation $citation): void
    {
        Hook::call('Citation::delete::before', [$citation]);
        $this->dao->delete($citation);
        Hook::call('Citation::delete', [$citation]);
    }

    /**
     * Delete a collection of citations
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $citation) {
            $this->delete($citation);
        }
    }

    /**
     * Insert on duplicate update.
     */
    public function updateOrInsert(Citation $citation): int
    {
        return $this->dao->updateOrInsert($citation);
    }

    /**
     * Get all citations for a given publication.
     *
     * @return array<Citation>
     */
    public function getByPublicationId(int $publicationId): array
    {
        return $this->getCollector()
            ->filterByPublicationId($publicationId)
            ->getMany()
            ->values()
            ->all();
    }

    /**
     * Get all raw citations for a given publication.
     */
    public function getRawCitationsByPublicationId(int $publicationId): Collection
    {
        return $this->dao->getRawCitationsByPublicationId($publicationId);
    }

    /**
     * Delete a publication's citations.
     */
    public function deleteByPublicationId(int $publicationId): void
    {
        $this->dao->deleteByPublicationId($publicationId);
    }

    /**
     * Import and replace citations from a raw citation list of the particular publication.
     *
     * @hook Citation::importCitations::before [[$publicationId, $existingCitations, $rawCitations]]
     * @hook Citation::importCitations::after [[$publicationId, $existingCitations, $importedCitations]]
     */
    public function importCitations(Publication $publication, ?string $rawCitationList, bool $reprocess = true): void
    {
        $context = $this->request->getContext();
        if (!$context) {
            $submission = Repo::submission()->get($publication->getData('submissionId'));
            $context = Application::getContextDAO()->getById($submission->getData('contextId'));
        }
        $citationsMetadataLookup = $context->getData('citationsMetadataLookup');
        $publicationId = $publication->getId();

        $existingCitations = $this->getByPublicationId($publicationId);
        Hook::call('Citation::importCitations::before', [$publicationId, $existingCitations, $rawCitationList]);

        $citationTokenizer = new CitationListTokenizerFilter();
        $citationStrings = $rawCitationList ? $citationTokenizer->execute($rawCitationList) : [];

        $existingRawCitations = array_map(fn (Citation $citation) => $citation->getRawCitation(), $existingCitations);

        if ($existingRawCitations !== $citationStrings) {
            $importedCitations = [];
            $this->deleteByPublicationId($publicationId);
            if (is_array($citationStrings) && !empty($citationStrings)) {
                foreach ($citationStrings as $seq => $rawCitationString) {
                    if (!empty($rawCitationString)) {
                        $citation = new Citation();
                        $citation->setRawCitation($rawCitationString);
                        $citation->setData('publicationId', $publicationId);
                        $citation->setSequence($seq + 1);
                        $citation->setProcessingStatus(CitationProcessingStatus::NOT_PROCESSED->value);
                        $newCitationId = $this->dao->insert($citation);
                        $citation->setId($newCitationId);
                        if ($citationsMetadataLookup && $reprocess) {
                            $this->reprocessCitation($citation);
                        }
                        $importedCitations[] = $citation;
                    }
                }
            }

            Hook::call('Citation::importCitations::after', [$publicationId, $existingCitations, $importedCitations]);
        }
    }

    /**
     * Insert/cpopy citations as they are for a publication. Used at publication versioning.
     */
    public function copyCitations(array $citations, int $publicationId): void
    {
        foreach ($citations as $citation) {
            /** @var Citation $citation */
            $citation->setData('publicationId', $publicationId);
            $this->dao->insert($citation);
        }

    }

    /**
     * Import citations from a raw citation list of the particular publication to existing citations.
     */
    public function importAdditionalCitations(int $publicationId, ?string $rawCitationList): string
    {
        $citationTokenizer = new CitationListTokenizerFilter();
        $citationStrings = $rawCitationList ? $citationTokenizer->execute($rawCitationList) : [];

        $lastSeq = $this->getLastSeq($publicationId);

        $rejectedCitations = [];
        if (is_array($citationStrings) && !empty($citationStrings)) {
            foreach ($citationStrings as $rawCitationString) {
                if (!empty($rawCitationString)) {
                    if (!$this->existsRawCitation($publicationId, $rawCitationString)) {
                        $lastSeq++;
                        $citation = new Citation();
                        $citation->setRawCitation($rawCitationString);
                        $citation->setData('publicationId', $publicationId);
                        $citation->setSequence($lastSeq);
                        $citation->setProcessingStatus(CitationProcessingStatus::NOT_PROCESSED->value);
                        $newCitationId = $this->dao->insert($citation);
                        $citation->setId($newCitationId);
                        if ($this->request->getContext()->getData('citationsMetadataLookup')) {
                            $this->reprocessCitation($citation);
                        }
                    } else {
                        $rejectedCitations[] = $rawCitationString;
                    }
                }
            }
        }

        return implode(PHP_EOL, $rejectedCitations);
    }

    /** @copydoc DAO::existsRawCitation() */
    public function existsRawCitation(int $publicationId, string $rawCitation): bool
    {
        return $this->dao->existsRawCitation($publicationId, $rawCitation);
    }

    /**
     * Get the last (max) sequence.
     */
    public function getLastSeq(int $publicationId): int
    {
        return $this->dao->getLastSeq($publicationId);
    }

    /**
     * Add a new job chain for a citation.
     */
    public function reprocessCitation(Citation $citation): void
    {
        $publication = Repo::publication()->get($citation->getData('publicationId'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = Application::getContextDAO()->getById($submission->getData('contextId'));

        $contactEmail = $context->getContactEmail();

        $jobs = [
            new ExtractPidsJob($citation->getId(), $context->getId()),
            new CrossrefJob($citation->getId(), $contactEmail, $context->getId()),
            new OpenAlexJob($citation->getId(), $contactEmail, $context->getId()),
            new OrcidJob($citation->getId(), $contactEmail, $context->getId()),
            new IsProcessedJob($citation->getId(), $context->getId()),
        ];

        Bus::chain($jobs)
            ->catch(function (Throwable $e) {
                error_log($e->getMessage());
            })
            ->dispatch();
    }
}
