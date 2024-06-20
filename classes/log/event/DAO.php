<?php

/**
 * @file classes/log/event/DAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write event information to the database.
 */

namespace PKP\log\event;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_EVENT_LOG;

    /** @copydoc EntityDAO::$table */
    public $table = 'event_log';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'event_log_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'log_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'log_id',
        'assocType' => 'assoc_type',
        'assocId' => 'assoc_id',
        'userId' => 'user_id',
        'dateLogged' => 'date_logged',
        'eventType' => 'event_type',
        'message' => 'message',
        'isTranslated' => 'is_translated',
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): EventLogEntry
    {
        return app(EventLogEntry::class);
    }

    /**
     * Check if a log entry exists
     */
    public function exists(int $id): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Get an event log entry
     */
    public function get(int $id): ?EventLogEntry
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of log entries matching the configured query
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
            ->select('e.' . $this->primaryKeyColumn)
            ->pluck('e.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of log entries matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->log_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): EventLogEntry
    {
        $logEntry = parent::fromRow($row);
        $schema = $this->schemaService->get($this->schema);

        DB::table($this->settingsTable)
            ->where($this->primaryKeyColumn, '=', $row->{$this->primaryKeyColumn})
            ->get()
            ->each(function ($row) use ($logEntry, $schema) {
                if (!empty($schema->properties->{$row->setting_name})) {
                    return;
                }

                // Retrieve custom properties
                if (!empty($row->setting_type)) {
                    $logEntry->setData(
                        $row->setting_name,
                        $this->convertFromDB(
                            $row->setting_value,
                            $row->setting_type
                        ),
                        empty($row->locale) ? null : $row->locale
                    );
                }
            });

        return $logEntry;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(EventLogEntry $eventLog): int
    {
        return parent::_insert($eventLog);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(EventLogEntry $eventLog)
    {
        parent::_delete($eventLog);
    }

    /**
     * Transfer all log entries to another user.
     */
    public function changeUser(int $oldUserId, int $newUserId)
    {
        DB::table($this->table)->where('user_id', $oldUserId)->update(['user_id' => $newUserId]);
    }
}
