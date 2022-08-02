<?php

/**
 * @file classes/core/ArrayItemIterator.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArrayItemIterator
 * @ingroup db
 *
 * @brief Provides paging and iteration for arrays.
 */

namespace PKP\core;

class ArrayItemIterator extends ItemIterator
{
    /** @var array The array of contents of this iterator. */
    public $theArray;

    /** @var int Number of items to iterate through on this page */
    public $itemsPerPage;

    /** @var int The current page. */
    public $page;

    /** @var int The total number of items. */
    public $count;

    /** @var bool Whether or not the iterator was empty from the start */
    public $wasEmpty;

    /**
     * Constructor.
     *
     * @param array $theArray The array of items to iterate through
     * @param int $page the current page number
     * @param int $itemsPerPage Number of items to display per page
     */
    public function __construct(&$theArray, $page = -1, $itemsPerPage = -1)
    {
        parent::__construct();
        if ($page >= 1 && $itemsPerPage >= 1) {
            $this->theArray = array_slice($theArray, ($page - 1) * $itemsPerPage, $itemsPerPage, true);
            $this->page = $page;
        } else {
            $this->theArray = & $theArray;
            $this->page = 1;
            $this->itemsPerPage = max(count($this->theArray), 1);
        }
        $this->count = count($theArray);
        $this->itemsPerPage = $itemsPerPage;
        $this->wasEmpty = count($this->theArray) == 0;
        reset($this->theArray);
    }

    /**
     * Static method: Generate an iterator from an array and rangeInfo object.
     *
     * @param array $theArray
     * @param object $theRange
     */
    public function &fromRangeInfo(&$theArray, &$theRange)
    {
        if ($theRange && $theRange->isValid()) {
            $theIterator = new ArrayItemIterator($theArray, $theRange->getPage(), $theRange->getCount());
        } else {
            $theIterator = new ArrayItemIterator($theArray);
        }
        return $theIterator;
    }

    /**
     * Return the next item in the iterator.
     *
     * @return object
     */
    public function &next()
    {
        if (!is_array($this->theArray)) {
            $value = null;
            return $value;
        }
        $value = current($this->theArray);
        if (next($this->theArray) === false) {
            $this->theArray = null;
        }
        return $value;
    }

    /**
     * Return the next item in the iterator, with key.
     *
     * @return array (key, value)
     */
    public function nextWithKey()
    {
        $key = key($this->theArray);
        $value = $this->next();
        return [$key, $value];
    }

    /**
     * Determine whether or not this iterator represents the first page
     *
     * @return bool
     */
    public function atFirstPage()
    {
        return $this->page == 1;
    }

    /**
     * Determine whether or not this iterator represents the last page
     *
     * @return bool
     */
    public function atLastPage()
    {
        return ($this->page * $this->itemsPerPage) + 1 > $this->count;
    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Get the total count of items
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get the number of pages
     *
     * @return int
     */
    public function getPageCount()
    {
        return max(1, ceil($this->count / $this->itemsPerPage));
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     *
     * @return bool
     */
    public function eof()
    {
        return (($this->theArray == null) || (count($this->theArray) == 0));
    }

    /**
     * Return a boolean indicating whether or not this iterator was empty from the beginning
     *
     * @return bool
     */
    public function wasEmpty()
    {
        return $this->wasEmpty;
    }

    /**
     * Convert this iterator to an array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->theArray;
    }

    /**
     * Return this iterator as an associative array.
     */
    public function toAssociativeArray()
    {
        return $this->theArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\ArrayItemIterator', '\ArrayItemIterator');
}
