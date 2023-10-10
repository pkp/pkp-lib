<?php

/**
 * @file classes/core/VirtualArrayIterator.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VirtualArrayIterator
 *
 * @ingroup db
 *
 * @brief Provides paging and iteration for "virtual" arrays -- arrays for which only
 * the current "page" is available, but are much bigger in entirety.
 */

namespace PKP\core;

use PKP\db\DBResultRange;

class VirtualArrayIterator extends ItemIterator
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
     * @param int $totalItems The total number of items in the virtual "larger" array
     * @param int $page the current page number
     * @param int $itemsPerPage Number of items to display per page
     */
    public function __construct($theArray, $totalItems, $page = -1, $itemsPerPage = -1)
    {
        parent::__construct();
        if ($page >= 1 && $itemsPerPage >= 1) {
            $this->page = $page;
        } else {
            $this->page = 1;
            $this->itemsPerPage = max(count($this->theArray), 1);
        }
        $this->theArray = $theArray;
        $this->count = $totalItems;
        $this->itemsPerPage = $itemsPerPage;
        $this->wasEmpty = count($this->theArray) == 0;
        reset($this->theArray);
    }

    /**
     * Factory Method.
     * Extracts the appropriate page items from the whole array and
     * calls the constructor.
     *
     * @param array $wholeArray The whole array of items
     * @param DBResultRange $rangeInfo The number of items per page
     *
     * @return object VirtualArrayIterator
     */
    public static function factory($wholeArray, $rangeInfo)
    {
        if ($rangeInfo->isValid()) {
            $slicedArray = array_slice($wholeArray, $rangeInfo->getCount() * ($rangeInfo->getPage() - 1), $rangeInfo->getCount(), true);
        }
        return new VirtualArrayIterator($slicedArray, count($wholeArray), $rangeInfo->getPage(), $rangeInfo->getCount());
    }

    /**
     * Return the next item in the iterator.
     *
     * @return ?object
     */
    public function &next()
    {
        if (!is_array($this->theArray)) {
            $nullVar = null;
            return $nullVar;
        }
        $value = current($this->theArray);
        if (next($this->theArray) == null) {
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
     * Check whether or not this iterator is for the first page of a sequence
     *
     * @return bool
     */
    public function atFirstPage()
    {
        return $this->page == 1;
    }

    /**
     * Check whether or not this iterator is for the last page of a sequence
     *
     * @return bool
     */
    public function atLastPage()
    {
        return ($this->page * $this->itemsPerPage) + 1 > $this->count;
    }

    /**
     * Get the page number that this iterator represents
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Get the total number of items in the virtual array
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get the total number of pages in the virtual array
     *
     * @return int
     */
    public function getPageCount()
    {
        return max(1, ceil($this->count / $this->itemsPerPage));
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     * Note: This implementation requires that next() be called before every eof() will
     * function properly (except the first call).
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
     * Convert the iterator into an array
     *
     * @return array
     */
    public function &toArray()
    {
        return $this->theArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\VirtualArrayIterator', '\VirtualArrayIterator');
}
