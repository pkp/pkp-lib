<?php

/**
 * @file classes/jobs/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup jobs
 *
 * @see Job
 *
 * @brief Operations for retrieving and modifying jobs
 */

namespace PKP\jobs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_JOBS;

    /** @copydoc EntityDAO::$table */
    public $table = 'jobs';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'id',
        'queue' => 'queue',
        'payload' => 'payload',
        'attempts' => 'attempts',
        'reserved_at' => 'reserved_at',
        'available_at' => 'available_at',
        'created_at' => 'created_at',
    ];

    /**
     * Instantiate a new Job
     */
    public function newDataObject(): Job
    {
        return App::make(Job::class);
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Job
    {
        $query = new Collector($this);
        $row = $query
            ->getQueryBuilder()
            ->where($this->primaryKeyColumn, '=', $id)
            ->first();

        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of jobs matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('sf.' . $this->primaryKeyColumn)
            ->pluck('sf.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of jobs matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Job $job): int
    {
        parent::_insert($job);

        return $job->getId();
    }

    /**
     * Update a Job
     */
    public function update(Job $job): void
    {
        throw new InvalidArgumentException('Is not possible to update a queued job. Delete it and add a new one');
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Job $job)
    {
        $this->deleteById($job->getData('id'));
    }

    /**
     * @copydoc EntityDao::deleteById()
     */
    public function deleteById(int $id)
    {
        DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->delete();
    }
}
