<?php

/**
 * @file classes/db/DAOResultFactory.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAOResultFactory
 * @ingroup db
 *
 * @brief Wrapper around Enumerable providing "factory" features for generating
 * objects from DAOs.
 */


import('lib.pkp.classes.core.ItemIterator');
import('lib.pkp.classes.db.DAOResultIterator');

use Illuminate\Support\Enumerable;

class DAOResultFactory extends ItemIterator {
	/** @var DAO The DAO used to create objects */
	var $dao;

	/** @var string The name of the DAO's factory function (to be called with an associative array of values) */
	var $functionName;

	/**
	 * @var array an array of primary key field names that uniquely
	 *   identify a result row in the record set.
	 */
	var $idFields;

	/** @var Enumerable The results to be wrapped around */
	var $records;

	/**
	 * @var string|null Fetch SQL
	 */
	var $sql;

	/**
	 * @var array|null Fetch parameters
	 */
	var $params;

	/**
	 * @var $rangeInfo DBResultRange|null Range information, if specified.
	 */
	var $rangeInfo;

	/**
	 * Constructor.
	 * Initialize the DAOResultFactory
	 * @param $records object ADO record set, Generator, or Enumerable
	 * @param $dao object DAO class for factory
	 * @param $functionName The function to call on $dao to create an object
	 * @param $idFields array an array of primary key field names that uniquely identify a result row in the record set. Should be data object _data array key, not database column name
	 * @param $sql string Optional SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
	 * @param $params array Optional parameters for SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
	 * @param $rangeInfo DBResultRange Optional pagination information. WARNING: New code should not use this.
	 */
	function __construct($records, $dao, $functionName, $idFields = [], $sql = null, $params = [], $rangeInfo = null) {
		parent::__construct();
		$this->functionName = $functionName;
		$this->dao = $dao;
		$this->idFields = $idFields;
		$this->records = $records;
		$this->sql = $sql;
		$this->params = $params;
		$this->rangeInfo = $rangeInfo;
	}

	/**
	 * Return the object representing the next row.
	 * @return object?
	 */
	function next() {
		if ($this->records == null) return $this->records;

		$row = null;
		$functionName = $this->functionName;
		$dao = $this->dao;

		if ($this->records instanceof Generator) {
			$row = (array) $this->records->current();
			$this->records->next();
		} elseif ($this->records instanceof Enumerable) {
			$row = (array) $this->records->shift();
		} else throw new Exception('Unsupported record set type (' . join(', ', class_implements($this->records)) . ')');
		if (!$row) return null;
		return $dao->$functionName($row);
	}

	/**
	 * @copydoc ItemIterator::count()
	 */
	function getCount() {
		if ($this->sql === null) throw new Exception('DAOResultFactory instances cannot be counted unless supplied in constructor (DAO ' . get_class($this->dao) . ')!');
		return $this->dao->countRecords($this->sql, $this->params);
	}

	/**
	 * Return the next row, with key.
	 * @return array? ($key, $value)
	 */
	function nextWithKey($idField = null) {
		$result = $this->next();
		if($idField) {
			assert(is_a($result, 'DataObject'));
			$key = $result->getData($idField);
		} elseif (empty($this->idFields)) {
			$key = null;
		} else {
			assert(is_a($result, 'DataObject') && is_array($this->idFields));
			$key = '';
			foreach($this->idFields as $idField) {
				assert(!is_null($result->getData($idField)));
				if (!empty($key)) $key .= '-';
				$key .= (string)$result->getData($idField);
			}
		}
		return [$key, $result];
	}

	/**
	 * Get the page number of a set that this iterator represents.
	 * @return int
	 */
	function getPage() {
		return $this->rangeInfo->getPage();
	}

	/**
	 * Get the total number of pages in this set.
	 * @return int
	 */
	function getPageCount() {
		return ceil($this->getCount() / $this->rangeInfo->getCount());
	}

	/**
	 * Return a boolean indicating whether or not we've reached the end of results
	 * @return boolean
	 */
	function eof() {
		if ($this->records == null) return true;
		return !$this->records->valid();
	}

	/**
	 * Return true iff the result list was empty.
	 * @return boolean
	 */
	function wasEmpty() {
		return $this->getCount() === 0;
	}

	/**
	 * Convert this iterator to an array.
	 * @return array
	 */
	function toArray() {
		$returner = [];
		while ($row = $this->next()) $returner[] = $row;
		return $returner;
	}

	/**
	 * Return an Iterator for this DAOResultFactory.
	 * @return Iterator
	 */
	function toIterator() {
		return new DAOResultIterator($this);
	}

	/**
	 * Convert this iterator to an associative array by database ID.
	 * @return array
	 */
	function toAssociativeArray($idField = 'id') {
		$returner = [];
		while ($row = $this->next()) $returner[$row->getData($idField)] = $row;
		return $returner;
	}
}
