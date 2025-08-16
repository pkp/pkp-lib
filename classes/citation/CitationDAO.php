<?php

/**
 * @file classes/citation/CitationDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationDAO
 *
 * @ingroup citation
 *
 * @see Citation
 *
 * @brief Operations for retrieving and modifying Citation objects
 */

namespace PKP\citation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\plugins\Hook;

class CitationDAO extends \PKP\db\DAO
{
    /**
     * Insert a new citation.
     *
     * @param Citation $citation
     *
     * @return int the new citation id
     */
    public function insertObject($citation)
    {
        $seq = $citation->getSequence();
        if (!(is_numeric($seq) && $seq > 0)) {
            // Find the latest sequence number
            $result = $this->retrieve(
                'SELECT MAX(seq) AS lastseq FROM citations
				WHERE publication_id = ?',
                [(int)$citation->getData('publicationId')]
            );
            $row = $result->current();
            $citation->setSequence($row ? $row->lastseq + 1 : 1);
        }

        $this->update(
            sprintf('INSERT INTO citations
				(publication_id, raw_citation, seq)
				VALUES
				(?, ?, ?)'),
            [
                (int) $citation->getData('publicationId'),
                $citation->getRawCitation(),
                (int) $seq
            ]
        );
        $citation->setId($this->getInsertId());
        $this->_updateObjectMetadata($citation);
        return $citation->getId();
    }

    /**
     * Retrieve a citation by id.
     *
     * @param int $citationId
     *
     * @return ?Citation
     */
    public function getById($citationId)
    {
        $result = $this->retrieve(
            'SELECT * FROM citations WHERE citation_id = ?',
            [$citationId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Import citations from a raw citation list of the particular publication.
     *
     * @hook Citation::importCitations::after [$publicationId, $existingCitations, $importedCitations]
     */
    public function importCitations(int $publicationId, string $rawCitationList)
    {
        $existingCitations = $this->getByPublicationId($publicationId)->all();

        // Remove existing citations.
        $this->deleteByPublicationId($publicationId);

        // Tokenize raw citations
        $citationTokenizer = new CitationListTokenizerFilter();
        $citationStrings = $rawCitationList ? $citationTokenizer->execute($rawCitationList) : [];

        // Instantiate and persist citations
        $importedCitations = [];
        if (is_array($citationStrings)) {
            foreach ($citationStrings as $seq => $citationString) {
                if (!empty(trim($citationString))) {
                    $citation = new Citation($citationString);
                    // Set the publication
                    $citation->setData('publicationId', $publicationId);
                    // Set the counter
                    $citation->setSequence($seq + 1);

                    $this->insertObject($citation);

                    $importedCitations[] = $citation;
                }
            }
        }

        Hook::run('Citation::importCitations::after', [$publicationId, $existingCitations, $importedCitations]);
    }

    /**
     * Retrieve an array of citations matching a particular publication id.
     */
    public function getByPublicationId(int $publicationId): LazyCollection
    {
        return LazyCollection::make(function () use ($publicationId) {
            $rows = DB::table('citations')
                ->select('*')
                ->where('publication_id', $publicationId)
                ->orderBy('seq')->orderBy('citation_id')
                ->get();
            foreach ($rows as $row) {
                yield $row->citation_id => $this->_fromRow($row);
            }
        });
    }

    /**
     * Retrieve raw citations for the given publication.
     */
    public function getRawCitationsByPublicationId(int $publicationId): LazyCollection
    {
        return LazyCollection::make(function () use ($publicationId) {
            $rawCitations = DB::table('citations')
                ->select(['raw_citation'])
                ->where('publication_id', '=', $publicationId)
                ->orderBy('seq')
                ->pluck('raw_citation');

            foreach ($rawCitations as $rawCitation) {
                yield $rawCitation;
            }
        });
    }

    /**
     * Update an existing citation.
     *
     * @param Citation $citation
     */
    public function updateObject($citation)
    {
        $returner = $this->update(
            'UPDATE	citations
			SET	publication_id = ?,
				raw_citation = ?,
				seq = ?
			WHERE	citation_id = ?',
            [
                (int) $citation->getData('publicationId'),
                $citation->getRawCitation(),
                (int) $citation->getSequence(),
                (int) $citation->getId()
            ]
        );
        $this->_updateObjectMetadata($citation);
    }

    /**
     * Delete a citation.
     *
     * @param Citation $citation
     *
     * @return bool
     */
    public function deleteObject($citation)
    {
        return $this->deleteById($citation->getId());
    }

    /**
     * Delete a citation by id.
     */
    public function deleteById(int $citationId): int
    {
        return DB::table('citations')
            ->where('citation_id', '=', $citationId)
            ->delete();
    }

    /**
     * Delete all citations matching a particular publication id.
     *
     * @param int $publicationId
     *
     * @return bool
     */
    public function deleteByPublicationId($publicationId)
    {
        $citations = $this->getByPublicationId($publicationId);
        foreach ($citations as $citation) {
            $this->deleteById($citation->getId());
        }
        return true;
    }

    //
    // Private helper methods
    //
    /**
     * Construct a new citation object.
     *
     * @return Citation
     */
    public function _newDataObject()
    {
        return new Citation();
    }

    /**
     * Internal function to return a citation object from a
     * row.
     */
    public function _fromRow(\stdClass $row): Citation
    {
        $citation = $this->_newDataObject();
        $citation->setId((int)$row->citation_id);
        $citation->setData('publicationId', (int) $row->publication_id);
        $citation->setRawCitation($row->raw_citation);
        $citation->setSequence((int) $row->seq);

        $this->getDataObjectSettings('citation_settings', 'citation_id', $row->citation_id, $citation);

        return $citation;
    }

    /**
     * Update the citation meta-data
     *
     * @param Citation $citation
     */
    public function _updateObjectMetadata($citation)
    {
        $this->updateDataObjectSettings('citation_settings', $citation, ['citation_id' => $citation->getId()]);
    }
}
