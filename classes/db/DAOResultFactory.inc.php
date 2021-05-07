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

namespace PKP\db;

use Illuminate\Support\Enumerable;

use PKP\core\ItemIterator;

class DAOResultFactory extends \PKP\core\ItemIterator
{
    /** @var DAO The DAO used to create objects */
    public $dao;

    /** @var string The name of the DAO's factory function (to be called with an associative array of values) */
    public $functionName;

    /**
     * @var array an array of primary key field names that uniquely
     *   identify a result row in the record set.
     */
    public $idFields;

    /** @var Enumerable The results to be wrapped around */
    public $records;

    /**
     * @var string|null Fetch SQL
     */
    public $sql;

    /**
     * @var array|null Fetch parameters
     */
    public $params;

    /**
     * @var DBResultRange|null $rangeInfo Range information, if specified.
     */
    public $rangeInfo;

    /**
     * Constructor.
     * Initialize the DAOResultFactory
     *
     * @param $records object ADO record set, Generator, or Enumerable
     * @param $dao object DAO class for factory
     * @param $functionName The function to call on $dao to create an object
     * @param $idFields array an array of primary key field names that uniquely identify a result row in the record set. Should be data object _data array key, not database column name
     * @param $sql string Optional SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
     * @param $params array Optional parameters for SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
     * @param $rangeInfo DBResultRange Optional pagination information. WARNING: New code should not use this.
     */
    public function __construct($records, $dao, $functionName, $idFields = [], $sql = null, $params = [], $rangeInfo = null)
    {
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
     *
     * @return object?
     */
    public function next()
    {
        if ($this->records == null) {
            return $this->records;
        }

        $row = null;
        $functionName = $this->functionName;
        $dao = $this->dao;

        if ($this->records instanceof \Generator) {
            $row = (array) $this->records->current();
            $this->records->next();
        } elseif ($this->records instanceof Enumerable) {
            $row = (array) $this->records->shift();
        } else {
            throw new \Exception('Unsupported record set type (' . join(', ', class_implements($this->records)) . ')');
        }
        if (!$row) {
            return null;
        }
        return $dao->$functionName($row);
    }

    /**
     * @copydoc ItemIterator::count()
     */
    public function getCount()
    {
        if ($this->sql === null) {
            throw new \Exception('DAOResultFactory instances cannot be counted unless supplied in constructor (DAO ' . get_class($this->dao) . ')!');
        }
        return $this->dao->countRecords($this->sql, $this->params);
    }

    /**
     * Return the next row, with key.
     *
     * @param null|mixed $idField
     *
     * @return array? ($key, $value)
     */
    public function nextWithKey($idField = null)
    {
        $result = $this->next();
        if ($idField) {
            assert($result instanceof \PKP\core\DataObject);
            $key = $result->getData($idField);
        } elseif (empty($this->idFields)) {
            $key = null;
        } else {
            assert($result instanceof \PKP\core\DataObject && is_array($this->idFields));
            $key = '';
            foreach ($this->idFields as $idField) {
                assert(!is_null($result->getData($idField)));
                if (!empty($key)) {
                    $key .= '-';
                }
                $key .= (string)$result->getData($idField);
            }
        }
        return [$key, $result];
    }

    /**
     * Get the page number of a set that this iterator represents.
     *
     * @return int
     */
    public function getPage()
    {
        return $this->rangeInfo->getPage();
    }

    /**
     * Get the total number of pages in this set.
     *
     * @return int
     */
    public function getPageCount()
    {
        return ceil($this->getCount() / $this->rangeInfo->getCount());
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     *
     * @return boolean
     */
    public function eof()
    {
        if ($this->records == null) {
            return true;
        }
        return !$this->records->valid();
    }

    /**
     * Return true iff the result list was empty.
     *
     * @return boolean
     */
    public function wasEmpty()
    {
        return $this->getCount() === 0;
    }

    /**
     * Convert this iterator to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $returner = [];
        while ($row = $this->next()) {
            $returner[] = $row;
        }
        return $returner;
    }

    /**
     * Return an Iterator for this DAOResultFactory.
     *
     * @return Iterator
     */
    public function toIterator()
    {
        return new DAOResultIterator($this);
    }

    /**
     * Convert this iterator to an associative array by database ID.
     *
     * @return array
     */
    public function toAssociativeArray($idField = 'id')
    {
        $returner = [];
        while ($row = $this->next()) {
            $returner[$row->getData($idField)] = $row;
        }
        return $returner;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\db\DAOResultFactory', '\DAOResultFactory');
}
