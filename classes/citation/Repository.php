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

use APP\core\Request;
use Illuminate\Support\Facades\App;
use PKP\citation\filter\CitationListTokenizerFilter;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

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
        $errors = [];

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
        $newRow = clone $citation;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Citation::edit', [$newRow, $citation, $params]);
        $this->dao->update($newRow);
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
     * Get all citations for a given publication.
     *
     * @return array<Citation>
     */
    public function getByPublicationId(int $publicationId): array
    {
        return $this->getCollector()
            ->filterByPublicationId($publicationId)
            ->getMany()
            ->all();
    }

    /**
     * Delete a publication's citations.
     */
    public function deleteByPublicationId(int $publicationId): void
    {
        $this->dao->deleteByPublicationId($publicationId);
    }

    public function getRawCitationsByPublicationId(int $publicationId): string
    {
        $existingRawCitations = [];
        foreach ($this->getByPublicationId($publicationId) as $id => $citation) {
            $existingRawCitations[] = $citation->getData('rawCitation');
        }

        return implode("\n", $existingRawCitations);
    }

    /**
     * Compare existing and new raw citations.
     */
    public function isRawCitationsChanged(array $existingCitations, string $rawCitations): bool
    {
        $existingRawCitations = [];
        foreach ($existingCitations as $id => $citation) {
            $existingRawCitations[] = $citation->getData('rawCitation');
        }

        $citationTokenizer = new CitationListTokenizerFilter();
        $newRawCitations = $citationTokenizer->execute($rawCitations);

        return $existingRawCitations !== $newRawCitations;
    }

    /**
     * Import citations from a raw citation list of the particular publication.
     *
     * @hook Citation::importCitations::before [[$publicationId, $existingCitations, $rawCitations]]
     * @hook Citation::importCitations::after [[$publicationId, $existingCitations, $importedCitations]]
     */
    public function importCitations(int $publicationId, ?string $rawCitations): void
    {
        $existingCitations = $this->getByPublicationId($publicationId);

        Hook::call('Citation::importCitations::before', [$publicationId, $existingCitations, $rawCitations]);

        if (!$rawCitations) {
            $this->deleteByPublicationId($publicationId);
            return;
        }

        // Tokenize raw citations
        $citationTokenizer = new CitationListTokenizerFilter();
        $rawCitationStrings = $citationTokenizer->execute($rawCitations);

        if ($this->isRawCitationsChanged($existingCitations, $rawCitations)) {
            $importedCitations = [];
            $this->deleteByPublicationId($publicationId);
            if (is_array($rawCitationStrings) && !empty($rawCitationStrings)) {
                foreach ($rawCitationStrings as $seq => $rawCitationString) {
                    if (!empty(trim($rawCitationString))) {
                        $citation = new Citation();
                        $citation->setRawCitation($rawCitationString);
                        $citation->setData('isStructured', false);
                        $citation->setData('publicationId', $publicationId);
                        $citation->setData('lastModified', date('Y-m-d H:i:s'));

                        $citation->setSequence($seq + 1);
                        $this->dao->insert($citation);
                        $importedCitations[] = $citation;
                    }
                }
            }

            Hook::call('Citation::importCitations::after', [$publicationId, $existingCitations, $importedCitations]);
        }
    }
}
