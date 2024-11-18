<?php

/**
 * @file classes/affiliation/DAO.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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
use PKP\ror\Ror;
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
     * If the submission locale is not found in rors / ror_settings table,
     * use the display locale name.
     */
    public function getMany(Collector $query, ?string $submissionLocale = null): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows, $submissionLocale) {
            $rorIds = [];
            foreach ($rows as $row) {
                if ($row->ror) $rorIds[] = $row->ror;
            }
            $rors = iterator_to_array(Repo::ror()->getCollector()->filterByRors($rorIds)->getManyRorAsCollectionId());

            foreach ($rows as $row) {
                $fromRow = $this->fromRow($row);
                if ($fromRow->_data['ror']) {
                    $fromRow->_data['name'] = $rors[$fromRow->_data['ror']]->_data['name'];
                    unset($fromRow->_data['name'][Ror::NO_LANG_CODE]);
                    if(empty($fromRow->_data['name'][$submissionLocale])) {
                        $displayLocale = $rors[$fromRow->_data['ror']]->_data['displayLocale'];
                        $fromRow->_data['name'][$submissionLocale] =
                            $rors[$fromRow->_data['ror']]->_data['name'][$displayLocale];
                    }
                }
                yield $row->author_affiliation_id => $fromRow;
            }
        });
    }

    /** @copydoc EntityDAO::fromRow() */
    public function fromRow(object $row): Affiliation
    {
        return parent::fromRow($row);
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
