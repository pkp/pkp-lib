<?php

/**
 * @file classes/ror/DAO.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\ror\DAO
 *
 * @ingroup ror
 *
 * @see Ror
 *
 * @brief Read and write ror cache to the database.
 */

namespace PKP\ror;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;

/**
 * @template T of Ror
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_ROR;

    /** @copydoc EntityDAO::$table */
    public $table = 'rors';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'ror_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'ror_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'ror_id',
        'ror' => 'ror',
        'displayLocale' => 'display_locale',
        'isActive' => 'is_active',
        'searchPhrase' => 'search_phrase'
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Ror
    {
        return App::make(Ror::class);
    }

    /**
     * Check if a ror exists.
     */
    public function exists(int $id): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Get an Ror
     */
    public function get(int $id): ?Ror
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of RORs matching the configured query
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
            ->select('r.' . $this->primaryKeyColumn)
            ->pluck('r.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of rors matching the configured query
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
                yield $row->ror_id => $this->fromRow($row);
            }
        });
    }

    /** @copydoc EntityDAO::fromRow() */
    public function fromRow(object $row): Ror
    {
        return parent::fromRow($row);
    }

    /** @copydoc EntityDAO::insert() */
    public function insert(Ror $ror): int
    {
        return parent::_insert($ror);
    }

    /** @copydoc EntityDAO::update() */
    public function update(Ror $ror): void
    {
        parent::_update($ror);
    }

    /** @copydoc EntityDAO::delete() */
    public function delete(Ror $ror): void
    {
        parent::_delete($ror);
    }

    /**
     * Get ror_id for given ror.
     */
    public function getIdByRor(string $ror): int
    {
        $row = DB::table($this->table)
            ->where('ror', '=', $ror)
            ->first($this->primaryKeyColumn);

        if (!empty($row->{$this->primaryKeyColumn})) {
            return $row->{$this->primaryKeyColumn};
        }

        return 0;
    }

    /**
     * Get ror object for given ror.
     */
    public function getByRor(string $ror): ?Ror
    {
        $row = DB::table($this->table)
            ->where('ror', '=', $ror)
            ->first();

        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Check if ror exists with given ror
     */
    public function existsByRor(string $ror): bool
    {
        return DB::table($this->table)
            ->where('ror', '=', $ror)
            ->exists();
    }

    /**
     * Insert on duplicate update.
     */
    public function updateOrInsert(Ror $ror): int
    {
        $existingRor = $this->getByRor($ror->getRor());
        if ($existingRor && $existingRor->getId()) {
            $ror->setId($existingRor->getId());
            $this->update($ror);
            return $existingRor->getId();
        } else {
            return $this->insert($ror);
        }
    }
}
