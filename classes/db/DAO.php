<?php

/**
 * @defgroup db DB
 * Implements basic database concerns such as connection abstraction.
 */

/**
 * @file classes/db/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup db
 *
 * @see DAORegistry
 *
 * @brief Operations for retrieving and modifying objects from a database.
 */

namespace PKP\db;

use Illuminate\Support\Facades\DB;

use PKP\cache\CacheManager;
use PKP\core\JSONMessage;
use PKP\plugins\HookRegistry;

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
            // in hook calls named e.g. "sessiondao::_Constructor"
            $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
            if (HookRegistry::call(strtolower_codesafe(end($classNameParts)) . '::_Constructor', [$this])) {
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
     * @return Generator
     */
    public function retrieve($sql, $params = [], $callHooks = true)
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "sessiondao::_getsession"
            // (always lower case).
            $value = null;
            if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$value])) {
                return $value;
            }
        }

        return DB::cursor(DB::raw($sql)->getValue(), $params);
    }

    /**
     * Execute a SELECT SQL statment, returning rows in the range supplied.
     *
     * @param string $sql the SQL statement
     * @param array $params parameters for the SQL statement
     * @param DBResultRange $dbResultRange object describing the desired range
     *
     * @deprecated 3.4
     *
     * @return Generator
     */
    public function retrieveRange($sql, $params = [], $dbResultRange = null, $callHooks = true)
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "sessiondao::_getsession"
            $value = null;
            if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$dbResultRange, &$value])) {
                return $value;
            }
        }

        if ($dbResultRange && $dbResultRange->isValid()) {
            $sql .= ' LIMIT ' . (int) $dbResultRange->getCount();
            $offset = (int) $dbResultRange->getOffset();
            $offset += max(0, $dbResultRange->getPage() - 1) * (int) $dbResultRange->getCount();
            $sql .= ' OFFSET ' . $offset;
        }

        return DB::cursor(DB::raw($sql), $params);
    }

    /**
     * Count the number of records in the supplied SQL statement (with optional bind parameters parameters)
     *
     * @param string $sql SQL query to be counted
     * @param array $params Optional SQL query bind parameters
     *
     * @deprecated 3.4
     *
     * @return int
     */
    public function countRecords($sql, $params = [])
    {
        $result = $this->retrieve('SELECT COUNT(*) AS row_count FROM (' . $sql . ') AS count_subquery', $params);
        return $result->current()->row_count;
    }

    /**
     * Concatenate SQL expressions into a single string.
     *
     * @param array ...$args SQL expressions (e.g. column names) to concatenate.
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function concat(...$args)
    {
        return 'CONCAT(' . join(',', $args) . ')';
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE SQL statement.
     *
     * @param string $sql the SQL statement the execute
     * @param array $params an array of parameters for the SQL statement
     * @param bool $callHooks Whether or not to call hooks
     * @param bool $dieOnError Whether or not to die if an error occurs
     *
     * @deprecated 3.4
     *
     * @return int Affected row count
     */
    public function update($sql, $params = [], $callHooks = true, $dieOnError = true)
    {
        if ($callHooks === true) {
            $trace = debug_backtrace();
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "sessiondao::_updateobject"
            // (all lowercase)
            $value = null;
            if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$value])) {
                return $value;
            }
        }

        return DB::affectingStatement($sql, $params);
    }

    /**
     * Insert a row in a table, replacing an existing row if necessary.
     *
     * @param string $table
     * @param array $arrFields Associative array of colName => value
     * @param array $keyCols Array of column names that are keys
     *
     * @deprecated 3.4
     *
     * @return int @see ADODB::Replace
     */
    public function replace($table, $arrFields, $keyCols)
    {
        $matchValues = array_filter($arrFields, fn ($key) => in_array($key, $keyCols), ARRAY_FILTER_USE_KEY);
        $additionalValues = array_filter($arrFields, fn ($key) => !in_array($key, $keyCols), ARRAY_FILTER_USE_KEY);
        DB::table($table)->updateOrInsert($matchValues, $additionalValues);
    }

    /**
     * Return the last ID inserted in an autonumbered field.
     *
     * @deprecated 3.4
     *
     * @return int
     */
    protected function _getInsertId()
    {
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Configure the caching directory for database results
     * NOTE: This is implemented as a GLOBAL setting and cannot
     * be set on a per-connection basis.
     *
     * @deprecated 3.4
     */
    protected function setCacheDir()
    {
        static $cacheDir;
        if (!isset($cacheDir)) {
            global $ADODB_CACHE_DIR;

            $cacheDir = CacheManager::getFileCachePath() . '/_db';

            $ADODB_CACHE_DIR = $cacheDir;
        }
    }

    /**
     * Flush the system cache.
     *
     * @deprecated 3.4
     */
    public function flushCache()
    {
        $this->setCacheDir();
    }

    /**
     * Return datetime formatted for DB insertion.
     *
     * @param int|string $dt *nix timestamp or ISO datetime string
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function datetimeToDB($dt)
    {
        if ($dt === null) {
            return 'NULL';
        }
        if (!ctype_digit($dt)) {
            $dt = strtotime($dt);
        }
        return '\'' . date('Y-m-d H:i:s', $dt) . '\'';
    }

    /**
     * Return date formatted for DB insertion.
     *
     * @param int|string $d *nix timestamp or ISO date string
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function dateToDB($d)
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
     * @param string $dt datetime from DB
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function datetimeFromDB($dt)
    {
        if ($dt === null) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($dt));
    }
    /**
     * Return date from DB as ISO date string.
     *
     * @param string $d date from DB
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function dateFromDB($d)
    {
        if ($d === null) {
            return null;
        }
        return date('Y-m-d', strtotime($d));
    }

    /**
     * Convert a value from the database to a specific type
     *
     * @param mixed $value Value from the database
     * @param string $type Type from the database, eg `string`
     * @param bool $nullable True iff the value is allowed to be null
     */
    public function convertFromDB($value, $type, $nullable = false)
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
                $decodedValue = json_decode($value, true);
                // FIXME: pkp/pkp-lib#6250 Remove after 3.3.x upgrade code is removed (see also pkp/pkp-lib#5772)
                if (!is_null($decodedValue)) {
                    return $decodedValue;
                } else {
                    return unserialize($value);
                }
                // no break
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
     * @param string $value
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function getType($value)
    {
        switch (gettype($value)) {
            case 'boolean':
            case 'bool':
                return 'bool';
            case 'integer':
            case 'int':
                return 'int';
            case 'double':
            case 'float':
                return 'float';
            case 'array':
            case 'object':
                return 'object';
            case 'string':
            default:
                return 'string';
        }
    }

    /**
     * Convert a PHP variable into a string to be stored in the DB
     *
     * @param string $type
     * @param bool $nullable True iff the value is allowed to be null.
     *
     * @return string
     */
    public function convertToDB($value, &$type, $nullable = false)
    {
        if ($nullable && $value === null) {
            return null;
        }

        if ($type == null) {
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
     *
     * @deprecated 3.4
     *
     * @return string|null
     */
    public function nullOrInt($value)
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
    public function getAdditionalFieldNames()
    {
        $returner = [];
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "sessiondao::getAdditionalFieldNames"
        // (class names lowercase)
        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        HookRegistry::call(strtolower_codesafe(end($classNameParts)) . '::getAdditionalFieldNames', [$this, &$returner]);

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
    public function getLocaleFieldNames()
    {
        $returner = [];
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "sessiondao::getLocaleFieldNames"
        // (class names lowercase)
        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        HookRegistry::call(strtolower_codesafe(end($classNameParts)) . '::getLocaleFieldNames', [$this, &$returner]);

        return $returner;
    }

    /**
     * Update the settings table of a data object.
     *
     * @param string $tableName
     * @param DataObject $dataObject
     * @param array $idArray
     *
     * @deprecated 3.4
     */
    public function updateDataObjectSettings($tableName, $dataObject, $idArray)
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
     * @param string $tableName Settings table name
     * @param string $idFieldName Name of ID column
     * @param \PKP\core\DataObject $dataObject Object in which to store retrieved values
     *
     * @deprecated 3.4
     */
    public function getDataObjectSettings($tableName, $idFieldName, $idFieldValue, $dataObject)
    {
        if ($idFieldName !== null) {
            $sql = "SELECT * FROM ${tableName} WHERE ${idFieldName} = ?";
            $params = [$idFieldValue];
        } else {
            $sql = "SELECT * FROM ${tableName}";
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
     * Get the driver for this connection.
     *
     * @param int $direction
     *
     * @deprecated 3.4
     *
     * @return string
     */
    public function getDirectionMapping($direction)
    {
        switch ($direction) {
            case self::SORT_DIRECTION_ASC:
                return 'ASC';
            case self::SORT_DIRECTION_DESC:
                return 'DESC';
            default:
                return 'ASC';
        }
    }

    /**
     * Generate a JSON message with an event that can be sent
     * to the client to refresh itself according to changes
     * in the DB.
     *
     * @param string $elementId (Optional) To refresh a single element
     *  give the element ID here. Otherwise all elements will
     *  be refreshed.
     * @param string $parentElementId (Optional) To refresh a single
     *  element that is associated with another one give the parent
     *  element ID here.
     * @param mixed $content (Optional) Additional content to pass back
     *  to the handler of the JSON message.
     *
     * @deprecated 3.4
     *
     * @return JSONMessage
     */
    public static function getDataChangedEvent($elementId = null, $parentElementId = null, $content = '')
    {
        // Create the event data.
        $eventData = null;
        if ($elementId) {
            $eventData = [$elementId];
            if ($parentElementId) {
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
     *
     * @return string|null
     */
    protected function formatDateToDB($date, $defaultNumWeeks = null, $acceptPastDate = true)
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\db\DAO', '\DAO');
    define('SORT_DIRECTION_ASC', \DAO::SORT_DIRECTION_ASC);
    define('SORT_DIRECTION_DESC', \DAO::SORT_DIRECTION_DESC);
}
