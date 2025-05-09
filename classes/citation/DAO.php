<?php

/**
 * @file classes/citation/DAO.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup citation
 *
 * @brief Operations for retrieving and modifying Citation objects.
 */

namespace PKP\citation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of Citation
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_CITATION;

    /** @copydoc EntityDAO::$table */
    public $table = 'citations';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'citation_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'citation_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'citation_id',
        'publicationId' => 'publication_id',
        'rawCitation' => 'raw_citation',
        'seq' => 'seq'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'publication_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Citation
    {
        return App::make(Citation::class);
    }

    /**
     * Get the number of Citation's matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Get a collection of citations matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->citation_id => $this->fromRow($row);
            }
        });
    }

    /**  @copydoc EntityDAO::insert() */
    public function insert(Citation $citation): int
    {
        return parent::_insert($citation);
    }

    /** @copydoc EntityDAO::update() */
    public function update(Citation $citation): void
    {
        parent::_update($citation);
    }

    /** @copydoc EntityDAO::delete() */
    public function delete(Citation $citation): void
    {
        parent::_delete($citation);
    }

    /**
     * Retrieve raw citations for the given publication.
     */
    public function getRawCitationsByPublicationId(int $publicationId): Collection
    {
        return DB::table('citations')
            ->select(['raw_citation'])
            ->where('publication_id', '=', $publicationId)
            ->orderBy('seq')
            ->pluck('raw_citation');
    }

    /**
     * Delete publication's citations.
     */
    public function deleteByPublicationId(int $publicationId): void
    {
        DB::table($this->table)
            ->where($this->getParentColumn(), '=', $publicationId)
            ->delete();
    }
}
