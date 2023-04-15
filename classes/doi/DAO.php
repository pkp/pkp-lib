<?php

/**
 * @file classes/doi/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup doi
 *
 * @see Doi
 *
 * @brief Operations for retrieving and modifying Doi objects.
 */

namespace PKP\doi;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

abstract class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_DOI;

    /** @copydoc EntityDAO::$table */
    public $table = 'dois';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'doi_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'doi_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'doi_id',
        'contextId' => 'context_id',
        'doi' => 'doi',
        'status' => 'status'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Doi
    {
        return App::make(Doi::class);
    }

    /**
     * Get the number of DOIs matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select('d.' . $this->primaryKeyColumn)
            ->get()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('d.' . $this->primaryKeyColumn)
            ->pluck('d.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of DOIs matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->doi_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Doi
    {
        /** @var Doi */
        $doi = parent::fromRow($row);
        if (empty($doi->getData('doi'))) {
            $doi->setData('resolvingUrl', '');
        } else {
            $doi->setData('resolvingUrl', $doi->getResolvingUrl());
        }

        return $doi;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Doi $doi): int
    {
        return parent::_insert($doi);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Doi $doi)
    {
        parent::_update($doi);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Doi $doi)
    {
        parent::_delete($doi);
    }

    /**
     * Set DOIs as stale if they have been submitted or registered
     */
    public function markStale(array $doiIds)
    {
        $q = DB::table($this->table, 'd');

        $updatableStatuses = [
            Doi::STATUS_SUBMITTED,
            Doi::STATUS_REGISTERED
        ];

        $q->whereIn('d.doi_id', $doiIds)
            ->whereIn('d.status', $updatableStatuses)
            ->update(['status' => Doi::STATUS_STALE]);
    }

    /**
     * Set DOIs as submitted once they have been added to the queue for processing
     *
     */
    public function markSubmitted(array $doiIds)
    {
        $q = DB::table($this->table, 'd');
        $q->whereIn('d.doi_id', $doiIds)
            ->update(['status' => Doi::STATUS_SUBMITTED]);
    }

    /**
     * Gets all depositable submission IDs along with all associated DOI IDs for use in DOI bulk deposit jobs.
     * This method is used to collect all valid submissions/IDs in a single query specifically for use with
     * queued jobs for depositing DOIs with a registration agency.
     *
     */
    abstract public function getAllDepositableSubmissionIds(Context $context): Collection;
}
