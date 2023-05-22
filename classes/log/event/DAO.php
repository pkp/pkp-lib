<?php
/**
 * @file classes/log/event/DAO.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
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

    public function __construct()
    {
        $this->deprecatedDao = new \PKP\db\DAO();

        // Overriding schema service to allow adding custom data to the event log
        $this->schemaService = new class extends PKPSchemaService {
            /**
             * Schema for the custom data to be recorded in the event log
             * [
             *   'propName' => [
             *        'type' => ...
             *        'multilingual' => ... (optional)
             * ]
             */
            protected ?array $customProps = null;

            public function setCustomProps(array $props)
            {
                $cleanProps = $this->validateCustomProps($props);
                if (empty($cleanProps)) {
                    return;
                }
                $this->customProps = $cleanProps;
            }

            public function getCustomProps(): ?array
            {
                return $this->customProps;
            }

            /**
             * Ensures custom props don't override original schema props.
             * Only "type" and "multilingual" flags are allowed
             */
            protected function validateCustomProps(array $props): array
            {
                // Check if property with specified name already exists
                $schema = $this->get(PKPSchemaService::SCHEMA_EVENT_LOG);
                if (!empty($schema->properties)) {
                    foreach ($schema->properties as $propName => $propSchema) {
                        if (array_key_exists($propName, $props)) {
                            unset($props[$propName]);
                        }
                    }
                }

                // Ensure that unsupported props aren't get passed
                $cleanProps = [];
                foreach ($props as $name => $settings) {
                    $type = $settings['type'] ?? null;
                    if (!$type) {
                        continue;
                    }
                    $cleanProps[$name] = ['type' => $settings['type']];

                    $multilingual = $settings['multilingual'] ?? null;
                    if ($multilingual) {
                        $cleanProps[$name] = array_merge($cleanProps[$name], ['multilingual' => $settings['multilingual']]);
                    }
                }

                return $cleanProps;
            }

            /**
             * Add custom properties to schema dynamically
             */
            public function get($schemaName, $forceReload = false)
            {
                $schema = parent::get($schemaName, $forceReload);
                if (!$this->getCustomProps()) {
                    return $schema;
                }

                $customSchema = new \stdClass();
                $customSchema->properties = json_decode(json_encode($this->getCustomProps()));
                $schema = $this->merge($schema, $customSchema);
                $this->_schemas[$schemaName] = $schema;;
                return $schema;
            }
        };
    }

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
     *
     * Allows inserting event log with dynamically defined custom properties.
     * Schema supports only 'type' and 'multilingual' flags; example of the expected format:
     * [
     *   'name' => [
     *        'type' => 'string',
     *        'multilingual' => true
     *   ]
     * ]
     */
    public function insert(EventLogEntry $eventLog, array $customPropsSchema = []): int
    {
        $this->schemaService->setCustomProps($customPropsSchema);
        $id = parent::_insert($eventLog);

        $customProps = $this->schemaService->getCustomProps();
        if (!$customProps) {
            return $id;
        }

        foreach ($customProps as $propName => $propFlags) {
            $this->setSettingType($propName, $propFlags['type'], $id);
        }

        return $id;
    }

    /**
     * @copydoc EntityDAO::update()
     * See self::insert() for custom props usage
     */
    public function update(EventLogEntry $eventLog, array $customPropsSchema = [])
    {
        $this->schemaService->setCustomProps($customPropsSchema);
        parent::_update($eventLog);

        $customProps = $this->schemaService->getCustomProps();
        if (!$customProps) {
            return;
        }

        foreach ($customProps as $propName => $propFlags) {
            $this->setSettingType($propName, $propFlags['type'], $eventLog->getId());
        }
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

    /**
     * Record setting type for custom props
     */
    protected function setSettingType(string $propName, string $propType, int $objectId)
    {
        DB::table($this->settingsTable)
            ->where('log_id', $objectId)
            ->where('setting_name', $propName)
            ->update([
                'setting_type' => $this->deprecatedDao->getType($propType)
            ]);
    }
}