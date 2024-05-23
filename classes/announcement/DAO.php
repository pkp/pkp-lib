<?php
/**
 * @file classes/announcement/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write announcements to the database.
 */

namespace PKP\announcement;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;

/**
 * @template T of Announcement
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_ANNOUNCEMENT;

    /** @copydoc EntityDAO::$table */
    public $table = 'announcements';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'announcement_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'announcement_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'announcement_id',
        'assocId' => 'assoc_id',
        'assocType' => 'assoc_type',
        'typeId' => 'type_id',
        'dateExpire' => 'date_expire',
        'datePosted' => 'date_posted',
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Announcement
    {
        return app(Announcement::class);
    }

    /**
     * Check if an announcement exists
     */
    public function exists(int $id): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Get an announcement
     */
    public function get(int $id): ?Announcement
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of announcements matching the configured query
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
            ->pluck('a.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of announcements matching the configured query
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
                yield $row->announcement_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Announcement $announcement): int
    {
        return parent::_insert($announcement);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Announcement $announcement)
    {
        parent::_update($announcement);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Announcement $announcement)
    {
        parent::_delete($announcement);
    }
}
