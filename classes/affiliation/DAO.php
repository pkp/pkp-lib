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
use PKP\core\EntityDAO;
use PKP\core\interfaces\CollectorInterface;
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
     * Get an affiliation.
     *
     * Optionally, pass the author ID to only get an affiliation
     * if it exists and is assigned to that author.
     */
    public function get(int $id, ?int $authorId = null): ?Author
    {
        // This is overridden due to the need to include submission_locale
        // to the fromRow function
        $row = DB::table('author_affiliations as a')
            ->join('authors as au', 'a.author_id', '=', 'au.author_id')
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->where('a.affiliation_id', '=', $id)
            ->when($authorId !== null, fn (Builder $query) => $query->where('a.author_id', '=', $authorId))
            ->select(['a.*', 's.locale AS submission_locale'])
            ->first();
        return $row ? $this->fromRow($row, [$id], (object) []) : null;
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

    /** @copydoc EntityDAO::fromRow() */
    public function fromRow(object $row, array $ids, object $cache, ?CollectorInterface $query = null): Affiliation
    {
        $affiliation = parent::fromRow($row, $ids, $cache, $query);

        // Set the primary locale from the submission
        $affiliation->setData('submissionLocale', $row->submission_locale);

        $cache->rorObjects ??= Repo::ror()->getCollector()->filterByAuthorAffiliationIds($ids)
            ->getMany()
            ->collect()
            ->groupBy(fn ($rorObject) => $rorObject->getRor());
        $affiliation->setData('rorObject', $cache->rorObjects->get($row->ror)?->first());

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
