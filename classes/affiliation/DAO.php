<?php

/**
 * @file classes/affiliation/DAO.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\affiliation\DAO
 *
 * @ingroup affiliation
 *
 * @see Affiliation
 *
 * @brief Read and write affiliation cache to the database.
 */

namespace PKP\affiliation;

use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of Affiliation
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_AFFILIATION;

    /** @copydoc EntityDAO::$table */
    public $table = 'author_affiliations';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'author_affiliation_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'author_affiliation_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'author_affiliation_id',
        'authorId' => 'author_id',
        'ror' => 'ror'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'author_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Affiliation
    {
        return App::make(Affiliation::class);
    }

    /**
     * Get the number of Affiliation's matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('a.' . $this->primaryKeyColumn)
            ->pluck('a.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of affiliations matching the configured query.
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
                yield $row->author_affiliation_id => $this->fromRow($row);
            }
        });
    }

    /** @copydoc EntityDAO::fromRow() */
    public function fromRow(object $row): Affiliation
    {
        $affiliation = parent::fromRow($row);
        if (!empty($affiliation->getRor())) {
            $affiliation->setData('rorObject', Repo::ror()->getCollector()->filterByRor($affiliation->getRor())->getMany()->first());
        }
        return $affiliation;
    }

    /** @copydoc EntityDAO::insert() */
    public function insert(Affiliation $affiliation): int
    {
        return parent::_insert($affiliation);
    }

    /** @copydoc EntityDAO::update() */
    public function update(Affiliation $affiliation): void
    {
        parent::_update($affiliation);
    }

    /** @copydoc EntityDAO::delete() */
    public function delete(Affiliation $affiliation): void
    {
        parent::_delete($affiliation);
    }

    /**
     * Delete author's affiliations.
     */
    public function deleteByAuthorId(int $authorId): void
    {
        DB::table($this->table)
            ->where($this->getParentColumn(), '=', $authorId)
            ->delete();
    }

    /**
     * * Insert on duplicate update.
     */
    public function updateOrInsert(Affiliation $affiliation): void
    {
        if (empty($affiliation->getId())) {
            $this->insert($affiliation);
        } else {
            $this->update($affiliation);
        }
    }
}
