<?php

/**
 * @file classes/core/EntityDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityDAO
 *
 * @brief A base class for DAOs that read and write an entity to the database
 */

namespace PKP\core;

use Exception;
use Illuminate\Support\Facades\DB;
use PKP\core\traits\EntityUpdate;
use PKP\db\DAO;
use PKP\services\PKPSchemaService;

/**
 * @template T of DataObject
 */
abstract class EntityDAO
{
    use EntityUpdate;

    /** @var string One of the \PKP\services\PKPSchemaService::SCHEMA_... constants */
    public $schema;

    /** @var string The name of the primary table for this entity */
    public $table;

    /** @var string The name of the settings table for this entity */
    public $settingsTable;

    /** @var string The column name for the object id in primary and settings tables */
    public $primaryKeyColumn;

    /** @var array Maps schema properties for the primary table to their column names */
    public $primaryTableColumns = [];

    /**
     * @var array Map schema properties to the primary table
     *
     * An array mapping the property names of an entity to the
     * correct column in the database table.
     *
     * Example:
     *
     * ```
     * [
     *  'id' => 'announcement_id',
     *  'datePosted' => 'date_posted',
     * }
     * ```
     *
     * Only include properties stored in self::$table. Properties
     * stored in self::$settingsTable do not need to be mapped.
     */
    public $primaryKeyColumns;

    /**
     * @var DAO An instance of PKP\db\DAO
     *
     * This provides access to a few methods that are
     * still shared with the deprecated DAOs.
     *
     * @deprecated 3.4
     */
    public $deprecatedDao;

    /** @var PKPSchemaService<T> $schemaService */
    protected $schemaService;

    /**
     * Constructor
     */
    public function __construct(PKPSchemaService $schemaService)
    {
        $this->deprecatedDao = new DAO();
        $this->schemaService = $schemaService;
    }

    /**
     * Creates a new DataObject
     *
     * @return T
     */
    public function newDataObject()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Convert a row from the database query into a DataObject
     *
     * @return T
     */
    public function fromRow(object $row): DataObject
    {
        $schema = $this->schemaService->get($this->schema);

        $object = $this->newDataObject();

        foreach ($this->primaryTableColumns as $propName => $column) {
            if (property_exists($row, $column)) {
                $object->setData(
                    $propName,
                    $this->convertFromDB($row->{$column}, $schema->properties->{$propName}->type, true)
                );
            }
        }

        if ($this->settingsTable) {
            $rows = DB::table($this->settingsTable)
                ->where($this->primaryKeyColumn, '=', $row->{$this->primaryKeyColumn})
                ->get();

            $rows->each(function ($row) use ($object, $schema) {
                if (!empty($schema->properties->{$row->setting_name})) {
                    $object->setData(
                        $row->setting_name,
                        $this->convertFromDB(
                            $row->setting_value,
                            $schema->properties->{$row->setting_name}->type
                        ),
                        empty($row->locale) ? null : $row->locale
                    );
                }
            });
        }

        return $object;
    }

    /**
     * Insert an object into the database
     *
     * @param T $object
     *
     * @return int New entry's database identifier
     */
    protected function _insert(DataObject $object): int
    {
        $schemaService = $this->schemaService;
        $schema = $schemaService->get($this->schema);
        $sanitizedProps = $schemaService->sanitize($this->schema, $object->_data);

        $primaryDbProps = $this->getPrimaryDbProps($object);

        if (empty($primaryDbProps)) {
            throw new Exception('Tried to insert ' . get_class($object) . ' without any properties for the ' . $this->table . ' table.');
        }

        DB::table($this->table)->insert($primaryDbProps);
        $object->setId((int) DB::getPdo()->lastInsertId());

        // Add additional properties to settings table if they exist
        if ($this->settingsTable && count($sanitizedProps) !== count($primaryDbProps)) {
            foreach ($schema->properties as $propName => $propSchema) {
                if (!isset($sanitizedProps[$propName]) || array_key_exists($propName, $this->primaryTableColumns)) {
                    continue;
                }
                if (!empty($propSchema->multilingual)) {
                    foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
                        DB::table($this->settingsTable)->insert([
                            $this->primaryKeyColumn => $object->getId(),
                            'locale' => $localeKey,
                            'setting_name' => $propName,
                            'setting_value' => $this->convertToDB($localeValue, $schema->properties->{$propName}->type),
                        ]);
                    }
                } else {
                    DB::table($this->settingsTable)->insert([
                        $this->primaryKeyColumn => $object->getId(),
                        'setting_name' => $propName,
                        'setting_value' => $this->convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type),
                    ]);
                }
            }
        }

        return $object->getId();
    }

    /**
     * Update an object in the database
     *
     * @param T $object
     */
    protected function _update(DataObject $object): void
    {
        $schemaService = $this->schemaService;
        $sanitizedProps = $schemaService->sanitize($this->schema, $object->_data);

        $primaryDbProps = $this->getPrimaryDbProps($object);

        DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $object->getId())
            ->update($primaryDbProps);

        if (!$this->settingsTable) {
            return;
        }

        $this->updateSettings($sanitizedProps, $object->getId());
    }

    /**
     * Delete an object from the database
     *
     * @param T $object
     *
     * @return int The number of affected rows
     */
    protected function _delete(DataObject $object): int
    {
        return $this->deleteById($object->getId());
    }

    /**
     * Delete an object from the database by its id
     *
     * @return int The number of affected rows
     */
    public function deleteById(int $id): int
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->delete();
    }

    /**
     * Prepare data to be inserted into the primary table
     *
     * Compiles the properties of a DataObject into a key/value
     * array that maps them to the primary table columns.
     *
     * @see $this->primaryTableColumns
     *
     * @param T $object
     */
    protected function getPrimaryDbProps(DataObject $object): array
    {
        $schema = $this->schemaService->get($this->schema);
        $sanitizedProps = $this->schemaService->sanitize($this->schema, $object->_data);

        $primaryDbProps = [];
        foreach ($this->primaryTableColumns as $propName => $columnName) {
            if ($propName !== 'id' && array_key_exists($propName, $sanitizedProps)) {
                $primaryDbProps[$columnName] = $this->convertToDB($sanitizedProps[$propName] ?? null, $schema->properties->{$propName}->type, true);
                // Convert empty string values for DATETIME columns into null values
                // because an empty string can not be saved to a DATETIME column
                if ($primaryDbProps[$columnName] === ''
                        && isset($schema->properties->{$propName}->validation)
                        && (
                            in_array('date_format:Y-m-d H:i:s', $schema->properties->{$propName}->validation)
                            || in_array('date_format:Y-m-d', $schema->properties->{$propName}->validation)
                        )
                ) {
                    $primaryDbProps[$columnName] = null;
                }
            }
        }

        return $primaryDbProps;
    }

    /**
     * @copydoc DAO::convertFromDB()
     */
    protected function convertFromDB($value, string $type, bool $nullable = false)
    {
        return $this->deprecatedDao->convertFromDB($value, $type, $nullable);
    }

    /**
     * @copydoc DAO::convertToDB()
     */
    protected function convertToDB($value, string $type, bool $nullable = false)
    {
        return $this->deprecatedDao->convertToDB($value, $type, $nullable);
    }

    public function getSettingsTable(): ?string
    {
        return $this->settingsTable;
    }

    public function getPrimaryKeyName(): string
    {
        return $this->primaryKeyColumn;
    }

    /**
     * @return bool whether the property is a setting
     */
    public function isSetting(string $settingName): bool
    {
        return !array_key_exists($settingName, $this->primaryTableColumns);
    }

    public function getSchemaName(): ?string
    {
        return $this->schema;
    }
}
