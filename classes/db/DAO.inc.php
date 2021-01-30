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
 * @see DAORegistry
 *
 * @brief Operations for retrieving and modifying objects from a database.
 */

import('lib.pkp.classes.db.DAOResultFactory');
import('lib.pkp.classes.db.DBResultRange');
import('lib.pkp.classes.core.DataObject');

define('SORT_DIRECTION_ASC', 0x00001);
define('SORT_DIRECTION_DESC', 0x00002);

use Illuminate\Database\Capsule\Manager as Capsule;

class DAO {
	/**
	 * Constructor.
	 * Initialize the database connection.
	 */
	function __construct($callHooks = true) {
		if ($callHooks === true) {
			// Call hooks based on the object name. Results
			// in hook calls named e.g. "sessiondao::_Constructor"
			if (HookRegistry::call(strtolower_codesafe(get_class($this)) . '::_Constructor', array($this))) {
				return;
			}
		}
	}

	/**
	 * Execute a SELECT SQL statement.
	 * @param $sql string the SQL statement
	 * @param $params array parameters for the SQL statement
	 * @return Illuminate\Support\LazyCollection
	 */
	function retrieve($sql, $params = [], $callHooks = true) {
		if ($callHooks === true) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "sessiondao::_getsession"
			// (always lower case).
			$value = null;
			if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), array(&$sql, &$params, &$value))) {
				return $value;
			}
		}

		return Capsule::cursor(Capsule::raw($sql), $params);
	}

	/**
	 * Execute a SELECT SQL statment, returning rows in the range supplied.
	 * @param $sql string the SQL statement
	 * @param $params array parameters for the SQL statement
	 * @param $dbResultRange DBResultRange object describing the desired range
	 * @return Illuminate\Support\LazyCollection
	 */
	function retrieveRange($sql, $params = [], $dbResultRange = null, $callHooks = true) {
		if ($callHooks === true) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "sessiondao::_getsession"
			$value = null;
			if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), array(&$sql, &$params, &$dbResultRange, &$value))) {
				return $value;
			}
		}

		if ($dbResultRange && $dbResultRange->isValid()) {
			$sql .= ' LIMIT ' . (int) $dbResultRange->getCount();
			$offset = (int) $dbResultRange->getOffset();
			$offset += max(0, $dbResultRange->getPage()-1) * (int) $dbResultRange->getCount();
			$sql .= ' OFFSET ' . $offset;
		}

		return Capsule::cursor(Capsule::raw($sql), $params);
	}

	/**
	 * Count the number of records in the supplied SQL statement (with optional bind parameters parameters)
	 * @param $sql string SQL query to be counted
	 * @param $params array Optional SQL query bind parameters
	 * @return int
	 */
	public function countRecords($sql, $params = []) {
		$result = $this->retrieve('SELECT COUNT(*) AS row_count FROM (' . $sql . ') AS count_subquery', $params);
		return $result->current()->row_count;
	}

	/**
	 * Concatenate SQL expressions into a single string.
	 * @param ...$args SQL expressions (e.g. column names) to concatenate.
	 * @return string
	 */
	public function concat(...$args) {
		return 'CONCAT(' .  join(',', $args) . ')';
	}

	/**
	 * Execute an INSERT, UPDATE, or DELETE SQL statement.
	 * @param $sql string the SQL statement the execute
	 * @param $params array an array of parameters for the SQL statement
	 * @param $callHooks boolean Whether or not to call hooks
	 * @param $dieOnError boolean Whether or not to die if an error occurs
	 * @return int Affected row count
	 */
	function update($sql, $params = [], $callHooks = true, $dieOnError = true) {
		if ($callHooks === true) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "sessiondao::_updateobject"
			// (all lowercase)
			$value = null;
			if (HookRegistry::call(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), array(&$sql, &$params, &$value))) {
				return $value;
			}
		}

		return Capsule::affectingStatement($sql, $params);
	}

	/**
	 * Insert a row in a table, replacing an existing row if necessary.
	 * @param $table string
	 * @param $arrFields array Associative array of colName => value
	 * @param $keyCols array Array of column names that are keys
	 * @return int @see ADODB::Replace
	 */
	function replace($table, $arrFields, $keyCols) {
		$queryBuilder = new \Staudenmeir\LaravelUpsert\Query\Builder(Capsule::connection());
		return $queryBuilder->from($table)->upsert($arrFields, $keyCols);
	}

	/**
	 * Return the last ID inserted in an autonumbered field.
	 * @return int
	 */
	protected function _getInsertId() {
		return Capsule::getPdo()->lastInsertId();
	}

	/**
	 * Configure the caching directory for database results
	 * NOTE: This is implemented as a GLOBAL setting and cannot
	 * be set on a per-connection basis.
	 */
	function setCacheDir() {
		static $cacheDir;
		if (!isset($cacheDir)) {
			global $ADODB_CACHE_DIR;

			$cacheDir = CacheManager::getFileCachePath() . '/_db';

			$ADODB_CACHE_DIR = $cacheDir;
		}
	}

	/**
	 * Flush the system cache.
	 */
	function flushCache() {
		$this->setCacheDir();
	}

	/**
	 * Return datetime formatted for DB insertion.
	 * @param $dt int/string *nix timestamp or ISO datetime string
	 * @return string
	 */
	function datetimeToDB($dt) {
		if ($dt === null) return 'NULL';
		if (!ctype_digit($dt)) $dt = strtotime($dt);
		return '\'' . date('Y-m-d H:i:s', $dt) . '\'';
	}

	/**
	 * Return date formatted for DB insertion.
	 * @param $d int/string *nix timestamp or ISO date string
	 * @return string
	 */
	function dateToDB($d) {
		if ($d === null) return 'NULL';
		if (!ctype_digit($d)) $d = strtotime($d);
		return '\'' . date('Y-m-d', $d) . '\'';
	}

	/**
	 * Return datetime from DB as ISO datetime string.
	 * @param $dt string datetime from DB
	 * @return string
	 */
	function datetimeFromDB($dt) {
		if ($dt === null) return null;
		return date('Y-m-d H:i:s', strtotime($dt));
	}
	/**
	 * Return date from DB as ISO date string.
	 * @param $d string date from DB
	 * @return string
	 */
	function dateFromDB($d) {
		if ($d === null) return null;
		return date('Y-m-d', strtotime($d));
	}

	/**
	 * Convert a stored type from the database
	 * @param $value string Value from DB
	 * @param $type string Type from DB
	 * @return mixed
	 */
	function convertFromDB($value, $type) {
		switch ($type) {
			case 'bool':
			case 'boolean':
				$value = (bool) $value;
				break;
			case 'int':
			case 'integer':
				$value = (int) $value;
				break;
			case 'float':
			case 'number':
				$value = (float) $value;
				break;
			case 'object':
			case 'array':
				$decodedValue = json_decode($value, true);
				// FIXME: pkp/pkp-lib#6250 Remove after 3.3.x upgrade code is removed (see also pkp/pkp-lib#5772)
				if (!is_null($decodedValue)) {
					$value = $decodedValue;
				} else {
					$value = unserialize($value);
				}
				break;
			case 'date':
				if ($value !== null) $value = strtotime($value);
				break;
			case 'string':
			default:
				// Nothing required.
				break;
		}
		return $value;
	}

	/**
	 * Get the type of a value to be stored in the database
	 * @param $value string
	 * @return string
	 */
	function getType($value) {
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
	 * @param $value mixed
	 * @param $type string
	 * @return string
	 */
	function convertToDB($value, &$type) {
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
					if (!is_numeric($value)) $value = strtotime($value);
					$value = strftime('%Y-%m-%d %H:%M:%S', $value);
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
	 * @param $value mixed
	 * @return string|null
	 */
	function nullOrInt($value) {
		return (empty($value)?null:(int) $value);
	}

	/**
	 * Get a list of additional field names to store in this DAO.
	 * This can be used to extend the table with virtual "columns",
	 * typically using the ..._settings table.
	 * @return array List of strings representing field names.
	 */
	function getAdditionalFieldNames() {
		$returner = array();
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "sessiondao::getAdditionalFieldNames"
		// (class names lowercase)
		HookRegistry::call(strtolower_codesafe(get_class($this)) . '::getAdditionalFieldNames', array($this, &$returner));

		return $returner;
	}

	/**
	 * Get locale field names. Like getAdditionalFieldNames, but for
	 * localized (multilingual) fields.
	 * @see getAdditionalFieldNames
	 * @return array Array of string field names.
	 */
	function getLocaleFieldNames() {
		$returner = array();
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "sessiondao::getLocaleFieldNames"
		// (class names lowercase)
		HookRegistry::call(strtolower_codesafe(get_class($this)) . '::getLocaleFieldNames', array($this, &$returner));

		return $returner;
	}

	/**
	 * Update the settings table of a data object.
	 * @param $tableName string
	 * @param $dataObject DataObject
	 * @param $idArray array
	 */
	function updateDataObjectSettings($tableName, $dataObject, $idArray) {
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
							$values = $dataObject->getData($fieldName);
							if (!is_array($values)) {
								// Inconsistent data: should have been an array
								assert(false);
								continue;
							}
						} else {
							// Transform shared data into an array so that
							// we can handle them the same way as translated data.
							$values = array(
								$noLocale => $dataObject->getData($fieldName)
							);
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
			$removeParams = array();
			foreach ($idArray as $idField => $idValue) {
				if (!empty($removeWhere)) $removeWhere .= ' AND ';
				$removeWhere .= $idField.' = ?';
				$removeParams[] = $idValue;
			}
			$removeWhere .= rtrim(' AND setting_name IN ( '.str_repeat('? ,', count($staleSettings)), ',').')';
			$removeParams = array_merge($removeParams, $staleSettings);
			$removeSql = 'DELETE FROM '.$tableName.' WHERE '.$removeWhere;
			$this->update($removeSql, $removeParams);
		}
	}

	/**
	 * Get contents of the _settings table, storing entries in the specified
	 * data object.
	 * @param $tableName string Settings table name
	 * @param $idFieldName string Name of ID column
	 * @param $dataObject DataObject Object in which to store retrieved values
	 */
	function getDataObjectSettings($tableName, $idFieldName, $idFieldValue, $dataObject) {
		if ($idFieldName !== null) {
			$sql = "SELECT * FROM $tableName WHERE $idFieldName = ?";
			$params = [$idFieldValue];
		} else {
			$sql = "SELECT * FROM $tableName";
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
				empty($row->locale)?null:$row->locale
			);
		}
	}

	/**
	 * Get the driver for this connection.
	 * @param $direction int
	 * @return string
	 */
	function getDirectionMapping($direction) {
		switch ($direction) {
			case SORT_DIRECTION_ASC:
				return 'ASC';
			case SORT_DIRECTION_DESC:
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
	 * @param $elementId string (Optional) To refresh a single element
	 *  give the element ID here. Otherwise all elements will
	 *  be refreshed.
	 * @param $parentElementId string (Optional) To refresh a single
	 *  element that is associated with another one give the parent
	 *  element ID here.
	 * @param $content mixed (Optional) Additional content to pass back
	 *  to the handler of the JSON message.
	 * @return JSONMessage
	 */
	static function getDataChangedEvent($elementId = null, $parentElementId = null, $content = '') {
		// Create the event data.
		$eventData = null;
		if ($elementId) {
			$eventData = array($elementId);
			if ($parentElementId) {
				$eventData['parentElementId'] = $parentElementId;
			}
		}

		// Create and render the JSON message with the
		// event to be triggered on the client side.
		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage(true, $content);
		$json->setEvent('dataChanged', $eventData);
		return $json;
	}

	/**
	 * Format a passed date (in English textual datetime)
	 * to Y-m-d H:i:s format, used in database.
	 * @param $date string Any English textual datetime.
	 * @param $defaultNumWeeks int If passed and date is null,
	 * used to calculate a data in future from today.
	 * @param $acceptPastDate boolean Will not accept past dates,
	 * returning today if false and the passed date
	 * is in the past.
	 * @return string or null
	 */
	function formatDateToDB($date, $defaultNumWeeks = null, $acceptPastDate = true) {
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
