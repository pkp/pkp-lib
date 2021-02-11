<?php

/**
 * @file classes/db/DBDataXMLParser.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DBDataXMLParser
 * @ingroup db
 *
 * @brief Class to import and export database data from an XML format.
 * See dbscripts/xml/dtd/xmldata.dtd for the XML schema used.
 */

import('lib.pkp.classes.xml.PKPXMLParser');

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class DBDataXMLParser {
	/** @var array the array of parsed SQL statements */
	var $sql;

	/**
	 * Constructor.
	 */
	function __construct() {
		$this->sql = array();
	}

	/**
	 * Parse an XML data file into SQL statements.
	 * @param $file string path to the XML file to parse
	 * @return array the array of SQL statements parsed
	 */
	function parseData($file) {
		$this->sql = array();
		$parser = new PKPXMLParser();
		$tree = $parser->parse($file);
		if (!$tree) return array();

		$allTables = Capsule::connection()->getDoctrineSchemaManager()->listTableNames();

		foreach ($tree->getChildren() as $type) switch($type->getName()) {
			case 'table':
				$fieldDefaultValues = array();

				// Match table element
				foreach ($type->getChildren() as $row) {
					switch ($row->getName()) {
						case 'row':
							// Match a row element
							$fieldValues = array();

							foreach ($row->getChildren() as $field) {
								// Get the field names and values for this INSERT
								list($fieldName, $value) = $this->_getFieldData($field);
								$fieldValues[$fieldName] = $value;
							}

							$fieldValues = array_merge($fieldDefaultValues, $fieldValues);

							if (count($fieldValues) > 0) {
								$this->sql[] = sprintf(
										'INSERT INTO %s (%s) VALUES (%s)',
										$type->getAttribute('name'),
										join(', ', array_keys($fieldValues)),
										join(', ', array_values($fieldValues)));
							}
							break;
						default: assert(false);
					}
				}
				break;
			case 'sql':
				// Match sql element (set of SQL queries)
				foreach ($type->getChildren() as $child) switch ($child->getName()) {
					case 'drop':
						$table = $child->getAttribute('table');
						$column = $child->getAttribute('column');
						if ($column) {
							$this->sql = array_merge($this->sql, array_column(Capsule::pretend(function() use ($table, $column) {
								Capsule::schema()->table($table, function (Blueprint $table) use ($column) {
									$table->dropColumn('column');
								});
							}), 'query'));
						} else {
							$this->sql = array_merge($this->sql, array_column(Capsule::pretend(function() use ($table) {
								Capsule::schema()->drop($table);
							}), 'query'));
						}
						break;
					case 'rename':
						$table = $child->getAttribute('table');
						$column = $child->getAttribute('column');
						$to = $child->getAttribute('to');
						if ($column) {
							// Rename a column.
							$this->sql = array_merge($this->sql, array_column(Capsule::pretend(function() use ($table, $column, $to) {
								Capsule::schema()->table($table, function (Blueprint $table) use ($column, $to) {
									$table->renameColumn($column, $to);
								});
							}), 'query'));
						} else {
							// Rename the table.
							$this->sql = array_merge($this->sql, array_column(Capsule::pretend(function() use ($table, $to) {
								Capsule::schema()->rename($table, $to);
							}), 'query'));
						}
						break;
					case 'dropindex':
						$table = $child->getAttribute('table');
						$index = $child->getAttribute('index');
						if (!$table || !$index) {
							throw new Exception('dropindex called without table or index');
						}

						$schemaManager = Capsule::connection()->getDoctrineSchemaManager();
						if ($child->getAttribute('ifexists') && !in_array($index, array_keys($schemaManager->listTableIndexes($table)))) break;
						$this->sql = array_merge($this->sql, array_column(Capsule::pretend(function() use ($table, $index) {
							Capsule::schema()->table($table, function (Blueprint $table) use ($index) {
								$table->dropIndex($index);
							});
						}), 'query'));
						break;
					case 'query':
						// If a "driver" attribute is specified, multiple drivers can be
						// specified with a comma separator.
						$driver = $child->getAttribute('driver');
						if (empty($driver) || in_array(Config::getVar('database', 'driver'), array_map('trim', explode(',', $driver)))) {
							$this->sql[] = $child->getValue();
						}
						break;
				}
				break;
		}
		return $this->sql;
	}

	/**
	 * Execute the parsed SQL statements.
	 * @param $continueOnError boolean continue to execute remaining statements if a failure occurs
	 * @return boolean success
	 */
	function executeData($continueOnError = false) {
		$this->errorMsg = null;
		foreach ($this->sql as $stmt) {
			try {
				Capsule::statement($stmt);
			} catch (Exception $e) {
				if (!$continueOnError) throw $e;
			}
		}
		return true;
	}

	/**
	 * Return the parsed SQL statements.
	 * @return array
	 */
	function getSQL() {
		return $this->sql;
	}

	/**
	 * Quote a string to be appear as a value in an SQL INSERT statement.
	 * @param $str string
	 * @return string
	 */
	function quoteString($str) {
		return Capsule::connection()->getPdo()->quote($str);
	}


	//
	// Private helper methods
	//
	/**
	 * retrieve a field name and value from a field node
	 * @param $fieldNode XMLNode
	 * @return array an array with two entries: the field
	 *  name and the field value
	 */
	function _getFieldData($fieldNode) {
		$fieldName = $fieldNode->getAttribute('name');
		$fieldValue = $fieldNode->getValue();

		// Is this field empty? If so: do we want NULL or
		// an empty string?
		$isEmpty = $fieldNode->getAttribute('null');
		if (!is_null($isEmpty)) {
			assert(is_null($fieldValue));
			switch($isEmpty) {
				case 1:
					$fieldValue = null;
					break;

				case 0:
					$fieldValue = '';
					break;
			}
		}

		// Translate null to 'NULL' for SQL use.
		if (is_null($fieldValue)) {
			$fieldValue = 'NULL';
		} else {
			// Quote the value.
			if (!is_numeric($fieldValue)) {
				$fieldValue = $this->quoteString($fieldValue);
			}
		}

		return array($fieldName, $fieldValue);
	}
}


