<?php

/**
 * @file classes/db/DAOResultFactory.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAOResultFactory
 *
 * @ingroup db
 *
 * @brief Wrapper around Enumerable providing "factory" features for generating
 * objects from DAOs.
 */

namespace PKP\db;

use APP\submission\DAO;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\core\ItemIterator;
use ReflectionClass;

/**
 * @template T of \PKP\core\DataObject
 * @extends ItemIterator<mixed,T>
 */
class DAOResultFactory extends ItemIterator
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

    /** @var \Generator<int,object>|Enumerable<int,object> The results to be wrapped around */
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
     * @var bool Does $functionName expect each record to be converted to an array
     */
    public $expectsArray = true;

    /** @var ?int Cached row count */
    private $rowCount = null;

    /**
     * Constructor.
     * Initialize the DAOResultFactory
     *
     * @param object $records ADO record set, Generator, or Enumerable
     * @param object $dao DAO class for factory
     * @param string $functionName The function to call on $dao to create an object
     * @param array $idFields an array of primary key field names that uniquely identify a result row in the record set. Should be data object _data array key, not database column name
     * @param string $sql Optional SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
     * @param array $params Optional parameters for SQL query used to generate paged result set. Necessary when total row counts will be needed (e.g. when paging). WARNING: New code should not use this.
     * @param ?DBResultRange $rangeInfo Optional pagination information. WARNING: New code should not use this.
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

        // Determine if the "fromRow" method expects to receive an array or a stdClass.
        // EntityDAOs expect an object. DAOs that extend PKP\db\DAO expect an array.
        $reflector = new ReflectionClass(get_class($this->dao));
        if ($reflector->hasMethod($this->functionName)) {
            $params = $reflector->getMethod($this->functionName)->getParameters();
            if (!empty($params) && $params[0]->hasType() && $params[0]->getType()->getName() === 'object') {
                $this->expectsArray = false;
            }
        }
    }

    /**
     * Return the object representing the next row.
     *
     * @return ?T
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
            $row = $this->records->current();
            $this->records->next();
        } elseif ($this->records instanceof Collection) {
            $row = $this->records->shift();
        } else {
            throw new \Exception('Unsupported record set type (' . join(', ', class_implements($this->records)) . ')');
        }
        if (!$row) {
            return null;
        }
        if ($this->expectsArray) {
            $row = (array) $row;
        }
        return $dao->$functionName($row);
    }

    /**
     * @copydoc ItemIterator::count()
     */
    public function getCount()
    {
        if ($this->sql === null) {
            throw new \Exception('DAOResultFactory instances cannot be counted unless supplied in constructor (DAO ' . $this->dao::class . ')!');
        }
        // EntityDAOs do not support the countRecords method, but it can
        // be accessed through an instance of PKP\db\DAO attached to them
        $dao = property_exists($this->dao, 'deprecatedDao') ? $this->dao->deprecatedDao : $this->dao;
        return $this->rowCount ??= $dao->countRecords($this->sql, $this->params);
    }

    /**
     * Return the next row, with key.
     *
     * @param null|mixed $idField
     *
     * @return ?array{mixed,T} ($key, $value)
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
     * @return bool
     */
    public function eof()
    {
        if ($this->records == null) {
            return true;
        }
        /** @var DAOResultIterator */
        $records = $this->records;
        return !$records->valid();
    }

    /**
     * Return true iff the result list was empty.
     *
     * @return bool
     */
    public function wasEmpty()
    {
        return $this->getCount() === 0;
    }

    /**
     * Convert this iterator to an array.
     *
     * @return T[]
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
     * @return DAOResultIterator<T>
     */
    public function toIterator()
    {
        return new DAOResultIterator($this);
    }

    /**
     * Convert this iterator to an associative array by database ID.
     *
     * @return array<array-key,T>
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
