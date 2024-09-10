<?php

/**
 * @defgroup db DB
 * Implements basic database concerns such as connection abstraction.
 */

/**
 * @file classes/db/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup db
 *
 * @see DAORegistry
 *
 * @brief Operations for retrieving and modifying objects from a database.
 */

namespace PKP\db;

use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\DataObject;
use PKP\core\JSONMessage;
use PKP\plugins\Hook;

class DAO
{
    public const SORT_DIRECTION_ASC = 1;
    public const SORT_DIRECTION_DESC = 2;

    /**
     * Constructor.
     * Initialize the database connection.
     */
    public function __construct($callHooks = true)
    {
        if ($callHooks === true) {
            // Call hooks based on the object name. Results
            // in hook calls named e.g. "DAO_CLASS::_Constructor"
            $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
            if (Hook::run(strtolower(end($classNameParts)) . '::_Constructor', [$this])) {
                return;
            }
        }
    }

    /**
     * Execute a SELECT SQL statement.
     *
     * @param string $sql the SQL statement
     * @param array $params parameters for the SQL statement
     *
     * @deprecated 3.4
     *
     * @return Generator<int,object>
     */
    public function retrieve(string $sql, array $params = [], bool $callHooks = true): Generator
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "DAO_CLASS::_get..."
            // (always lower case).
            $value = null;
            if (Hook::run(strtolower($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$value])) {
                return $value;
            }
        }

        return DB::cursor(DB::raw($sql)->getValue(DB::connection()->getQueryGrammar()), $params);
    }

    /**
     * Execute a SELECT SQL statement, returning rows in the range supplied.
     *
     * @param $sql the SQL statement
     * @param $params parameters for the SQL statement, params is used only when $sql is a string
     * @param $dbResultRange object describing the desired range
     *
     * @deprecated 3.4
     */
    public function retrieveRange(string|Builder $sql, array $params = [], ?DBResultRange $dbResultRange = null, bool $callHooks = true): Iterable
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "DAO_CLASS::_get..."
            $value = null;
            if (Hook::run(strtolower($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$dbResultRange, &$value])) {
                return $value;
            }
        }

        if ($dbResultRange && $dbResultRange->isValid()) {
            $limit = (int) $dbResultRange->getCount();
            $offset = (int) $dbResultRange->getOffset();
            $offset += max(0, $dbResultRange->getPage() - 1) * (int) $dbResultRange->getCount();
            if ($sql instanceof Builder) {
                $sql->limit($limit)->offset($offset);
            } else {
                $sql .= " LIMIT {$limit} OFFSET {$offset}";
            }
        }

        return $sql instanceof Builder ? $sql->get() : DB::cursor(DB::raw($sql)->getValue(DB::connection()->getQueryGrammar()), $params);
    }

    /**
     * Count the number of records in the supplied SQL statement (with optional bind parameters parameters)
     *
     * @param $sql SQL query to be counted
     * @param $params Optional SQL query bind parameters, only used when the $sql argument is a string
     *
     * @deprecated 3.4
     */
    public function countRecords(string|Builder $sql, array $params = []): int
    {
        // In case a Laravel Builder has been received, drop its SELECT and ORDER BY clauses for optimization purposes
        if ($sql instanceof Builder) {
            return $sql->getCountForPagination();
        }
        $result = $this->retrieve('SELECT COUNT(*) AS row_count FROM (' . $sql . ') AS count_subquery', $params);
        return $result->current()->row_count;
    }

    /**
     * Concatenate SQL expressions into a single string.
     *
     * @param array ...$args SQL expressions (e.g. column names) to concatenate.
     *
     * @deprecated 3.4
     */
    public function concat(...$args): string
    {
        return 'CONCAT(' . join(',', $args) . ')';
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE SQL statement.
     *
     * @param $sql the SQL statement the execute
     * @param $params an array of parameters for the SQL statement
     * @param $callHooks Whether or not to call hooks
     * @param $dieOnError Whether or not to die if an error occurs
     *
     * @deprecated 3.4
     *
     * @return Affected row count
     */
    public function update(string $sql, array $params = [], bool $callHooks = true, bool $dieOnError = true): int
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "DAO_CLASS::_updateobject"
            // (all lowercase)
            $value = null;
            if (Hook::run(strtolower($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$value])) {
                return $value;
            }
        }

        return DB::affectingStatement($sql, $params);
    }

    /**
     * Insert a row in a table, replacing an existing row if necessary.
     *
     * @param $arrFields Associative array of colName => value
     * @param $keyCols Array of column names that are keys
     *
     * @deprecated 3.4
     */
    public function replace(string $table, array $arrFields, array $keyCols): void
    {
        $matchValues = array_filter($arrFields, fn ($key) => in_array($key, $keyCols), ARRAY_FILTER_USE_KEY);
        $additionalValues = array_filter($arrFields, fn ($key) => !in_array($key, $keyCols), ARRAY_FILTER_USE_KEY);
        DB::table($table)->updateOrInsert($matchValues, $additionalValues);
    }

    /**
     * Return the last ID inserted in an autonumbered field.
     */
    protected function getInsertId(): int
    {
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Return the last ID inserted in an autonumbered field.
     *
     * @deprecated 3.4
     */
    public function _getInsertId(): int
    {
        return $this->getInsertId();
    }

    /**
     * Return datetime formatted for DB insertion.
     *
     * @param $dt *nix timestamp or ISO datetime string
     *
     * @deprecated 3.4
     */
    public function datetimeToDB(null|int|string $dt): string
    {
        if ($dt === null) {
            return 'NULL';
        }
        if (!ctype_digit((string) $dt)) {
            $dt = strtotime($dt);
        }
        return '\'' . date('Y-m-d H:i:s', $dt) . '\'';
    }

    /**
     * Return date formatted for DB insertion.
     *
     * @param $d *nix timestamp or ISO date string
     *
     * @deprecated 3.4
     */
    public function dateToDB(null|int|string $d): string
    {
        if ($d === null) {
            return 'NULL';
        }
        if (!ctype_digit($d)) {
            $d = strtotime($d);
        }
        return '\'' . date('Y-m-d', $d) . '\'';
    }

    /**
     * Return datetime from DB as ISO datetime string.
     *
     * @deprecated 3.4
     */
    public function datetimeFromDB(?string $dt): ?string
    {
        return $dt === null ? null : date('Y-m-d H:i:s', strtotime($dt));
    }

    /**
     * Return date from DB as ISO date string.
     *
     * @deprecated 3.4
     */
    public function dateFromDB(?string $d): ?string
    {
        return $d === null ? null : date('Y-m-d', strtotime($d));
    }

    /**
     * Convert a value from the database to a specific type
     *
     * @param $value Value from the database
     * @param $type Type from the database, eg `string`
     * @param $nullable True iff the value is allowed to be null
     */
    public function convertFromDB(mixed $value, ?string $type, bool $nullable = false): mixed
    {
        if ($nullable && $value === null) {
            return null;
        }
        switch ($type) {
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'number':
                return (float) $value;
            case 'object':
            case 'array':
                return json_decode($value, true);
            case 'date':
                return strtotime($value);
            case 'string':
            default:
                // Nothing required.
                break;
        }
        return $value;
    }

    /**
     * Get the type of a value to be stored in the database
     *
     * @deprecated 3.4
     */
    public function getType(mixed $value): string
    {
        return match(gettype($value)) {
            'boolean' => 'bool',
            'bool' => 'bool',
            'integer' => 'int',
            'int' => 'int',
            'double' => 'float',
            'float' => 'float',
            'array' => 'object',
            'object' => 'object',
            'string' => 'string',
            default => 'string'
        };
    }

    /**
     * Convert a PHP variable into a string to be stored in the DB
     *
     * @param bool $nullable True iff the value is allowed to be null.
     *
     * @return string
     */
    public function convertToDB(mixed $value, ?string &$type = null, bool $nullable = false)
    {
        if ($nullable && $value === null) {
            return null;
        }

        if ($type === null) {
            $type = $this->getType($value);
        }

        switch ($type) {
            case 'object':
            case 'array':
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            case 'bool':
            case 'boolean':
                // Cast to boolean, ensuring that string
                // "false" evaluates to boolean false
                $value = ($value && $value !== 'false') ? 1 : 0;
                break;
            case 'int':
            case 'integer':
                $value = (int) $value;
                break;
            case 'float':
            case 'number':
                $value = (float) $value;
                break;
            case 'date':
                if ($value !== null) {
                    if (!is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    $value = date('Y-m-d H:i:s', $value);
                }
                break;
            case 'string':
            default:
                // do nothing.
        }

        return $value;
    }

    /**
     * Cast the given parameter to an int, or leave it null.
     *
     * @deprecated 3.4
     */
    public function nullOrInt(mixed $value): ?int
    {
        return (empty($value) ? null : (int) $value);
    }

    /**
     * Get a list of additional field names to store in this DAO.
     * This can be used to extend the table with virtual "columns",
     * typically using the ..._settings table.
     *
     * @deprecated 3.4
     *
     * @return array List of strings representing field names.
     */
    public function getAdditionalFieldNames(): array
    {
        $returner = [];
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "DAO_CLASS::getAdditionalFieldNames"
        // (class names lowercase)
        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        Hook::run(strtolower(end($classNameParts)) . '::getAdditionalFieldNames', [$this, &$returner]);

        return $returner;
    }

    /**
     * Get locale field names. Like getAdditionalFieldNames, but for
     * localized (multilingual) fields.
     *
     * @see getAdditionalFieldNames
     * @deprecated 3.4
     *
     * @return array Array of string field names.
     */
    public function getLocaleFieldNames(): array
    {
        $returner = [];
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "DAO_CLASS::getLocaleFieldNames"
        // (class names lowercase)
        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        Hook::run(strtolower(end($classNameParts)) . '::getLocaleFieldNames', [$this, &$returner]);

        return $returner;
    }

    /**
     * Update the settings table of a data object.
     *
     * @deprecated 3.4
     */
    public function updateDataObjectSettings(string $tableName, DataObject $dataObject, array $idArray)
    {
        // Initialize variables
        $idFields = array_keys($idArray);
        $idFields[] = 'locale';
        $idFields[] = 'setting_name';

        // Build a data structure that we can process efficiently.
        $translated = $metadata = 1;
        $settings = !$metadata;
        $settingFields = [
            // Translated data
            $translated => [
                $settings => $this->getLocaleFieldNames(),
                $metadata => $dataObject->getLocaleMetadataFieldNames()
            ],
            // Shared data
            !$translated => [
                $settings => $this->getAdditionalFieldNames(),
                $metadata => $dataObject->getAdditionalMetadataFieldNames()
            ]
        ];

        // Loop over all fields and update them in the settings table
        $updateArray = $idArray;
        $noLocale = 0;
        $staleSettings = [];

        foreach ($settingFields as $isTranslated => $fieldTypes) {
            foreach ($fieldTypes as $isMetadata => $fieldNames) {
                foreach ($fieldNames as $fieldName) {
                    // Now we have the following control data:
                    // - $isTranslated: true for translated data, false data shared between locales
                    // - $isMetadata: true for metadata fields, false for normal settings
                    // - $fieldName: the field in the data object to be updated
                    if ($dataObject->hasData($fieldName)) {
                        if ($isTranslated) {
                            // Translated data comes in as an array
                            // with the locale as the key.
                            $values = $dataObject->getData($fieldName) ?? [];
                            if (!is_array($values)) {
                                // Inconsistent data: should have been an array
                                assert(false);
                                continue;
                            }
                        } else {
                            // Transform shared data into an array so that
                            // we can handle them the same way as translated data.
                            $values = [
                                $noLocale => $dataObject->getData($fieldName)
                            ];
                        }

                        // Loop over the values and update them in the database
                        foreach ($values as $locale => $value) {
                            $updateArray['locale'] = ($locale === $noLocale ? '' : $locale);
                            $updateArray['setting_name'] = $fieldName;
                            $updateArray['setting_type'] = null;
                            // Convert the data value and implicitly set the setting type.
                            $updateArray['setting_value'] = $this->convertToDB($value, $updateArray['setting_type']);
                            $this->replace($tableName, $updateArray, $idFields);
                        }
                    } else {
                        // Data is maintained "sparsely". Only set fields will be
                        // recorded in the settings table. Fields that are not explicity set
                        // in the data object will be deleted.
                        $staleSettings[] = $fieldName;
                    }
                }
            }
        }

        // Remove stale data
        if (count($staleSettings)) {
            $removeWhere = '';
            $removeParams = [];
            foreach ($idArray as $idField => $idValue) {
                if (!empty($removeWhere)) {
                    $removeWhere .= ' AND ';
                }
                $removeWhere .= $idField . ' = ?';
                $removeParams[] = $idValue;
            }
            $removeWhere .= rtrim(' AND setting_name IN ( ' . str_repeat('? ,', count($staleSettings)), ',') . ')';
            $removeParams = array_merge($removeParams, $staleSettings);
            $removeSql = 'DELETE FROM ' . $tableName . ' WHERE ' . $removeWhere;
            $this->update($removeSql, $removeParams);
        }
    }

    /**
     * Get contents of the _settings table, storing entries in the specified
     * data object.
     *
     * @param $tableName Settings table name
     * @param $idFieldName Name of ID column
     * @param $dataObject Object in which to store retrieved values
     *
     * @deprecated 3.4
     */
    public function getDataObjectSettings(string $tableName, string $idFieldName, int $idFieldValue, DataObject $dataObject)
    {
        if ($idFieldName !== null) {
            $sql = "SELECT * FROM {$tableName} WHERE {$idFieldName} = ?";
            $params = [$idFieldValue];
        } else {
            $sql = "SELECT * FROM {$tableName}";
            $params = [];
        }
        $result = $this->retrieve($sql, $params);
        foreach ($result as $row) {
            $dataObject->setData(
                $row->setting_name,
                $this->convertFromDB(
                    $row->setting_value,
                    $row->setting_type
                ),
                empty($row->locale) ? null : $row->locale
            );
        }
    }

    /**
     * Get the direction specifier for sorting from a SORT_DIRECTION_... constant.
     *
     * @deprecated 3.4
     */
    public function getDirectionMapping(int $direction): string
    {
        return match($direction) {
            self::SORT_DIRECTION_ASC => 'ASC',
            self::SORT_DIRECTION_DESC => 'DESC',
            default => 'ASC'
        };
    }

    /**
     * Generate a JSON message with an event that can be sent
     * to the client to refresh itself according to changes
     * in the DB.
     *
     * @param $elementId (Optional) To refresh a single element
     *  give the element ID here. Otherwise all elements will
     *  be refreshed.
     * @param $parentElementId (Optional) To refresh a single
     *  element that is associated with another one give the parent
     *  element ID here.
     * @param $content (Optional) Additional content to pass back
     *  to the handler of the JSON message.
     *
     * @deprecated 3.4
     */
    public static function getDataChangedEvent(?string $elementId = null, ?string $parentElementId = null, string $content = ''): JSONMessage
    {
        // Create the event data.
        $eventData = null;
        if ($elementId) {
            $eventData = [$elementId];
            if (strlen($parentElementId ?? '') > 0) {
                $eventData['parentElementId'] = $parentElementId;
            }
        }

        // Create and render the JSON message with the
        // event to be triggered on the client side.
        $json = new JSONMessage(true, $content);
        $json->setEvent('dataChanged', $eventData);
        return $json;
    }

    /**
     * Format a passed date (in English textual datetime)
     * to Y-m-d H:i:s format, used in database.
     *
     * @param string $date Any English textual datetime.
     * @param int $defaultNumWeeks If passed and date is null,
     * used to calculate a data in future from today.
     * @param bool $acceptPastDate Will not accept past dates,
     * returning today if false and the passed date
     * is in the past.
     *
     * @deprecated 3.4
     */
    protected function formatDateToDB(string $date, ?int $defaultNumWeeks = null, bool $acceptPastDate = true): ?string
    {
        $today = getDate();
        $todayTimestamp = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
        if ($date != null) {
            $dateParts = explode('-', $date);

            // If we don't accept past dates...
            if (!$acceptPastDate && $todayTimestamp > strtotime($date)) {
                // ... return today.
                return date('Y-m-d H:i:s', $todayTimestamp);
            } else {
                // Return the passed date.
                return date('Y-m-d H:i:s', mktime(0, 0, 0, $dateParts[1], $dateParts[2], $dateParts[0]));
            }
        } elseif (isset($defaultNumWeeks)) {
            // Add the equivalent of $numWeeks weeks, measured in seconds, to $todaysTimestamp.
            $numWeeks = max((int) $defaultNumWeeks, 2);
            $newDueDateTimestamp = $todayTimestamp + ($numWeeks * 7 * 24 * 60 * 60);
            return date('Y-m-d H:i:s', $newDueDateTimestamp);
        } else {
            // Either the date or the defaultNumWeeks must be set
            assert(false);
            return null;
        }
    }
}
