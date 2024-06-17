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
    /** @var The array of contents of this iterator. */
    public ?array $theArray;

    /** @var Number of items to iterate through on this page */
    public int $itemsPerPage;

    /** @var The current page. */
    public int $page;

    /** @var The total number of items. */
    public int $count;

    /** @var Whether or not the iterator was empty from the start */
    public bool $wasEmpty;

    /**
     * Constructor.
     *
     * @param $theArray The array of items to iterate through
     * @param $totalItems The total number of items in the virtual "larger" array
     * @param $page the current page number
     * @param $itemsPerPage Number of items to display per page
     */
    public function __construct(array $theArray, int $totalItems, int $page = -1, int $itemsPerPage = -1)
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
     * Extracts the appropriate page items from the whole array and calls the constructor.
     */
    public static function factory(array $wholeArray, DBResultRange $rangeInfo): static
    {
        if ($rangeInfo->isValid()) {
            $slicedArray = array_slice($wholeArray, $rangeInfo->getCount() * ($rangeInfo->getPage() - 1), $rangeInfo->getCount(), true);
        }
        return new static($slicedArray, count($wholeArray), $rangeInfo->getPage(), $rangeInfo->getCount());
    }

    /**
     * Return the next item in the iterator.
     */
    public function next(): mixed
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
     */
    public function nextWithKey(): array
    {
        $key = key($this->theArray);
        $value = $this->next();
        return [$key, $value];
    }

    /**
     * Check whether or not this iterator is for the first page of a sequence
     */
    public function atFirstPage(): bool
    {
        return $this->page == 1;
    }

    /**
     * Check whether or not this iterator is for the last page of a sequence
     */
    public function atLastPage(): bool
    {
        return ($this->page * $this->itemsPerPage) + 1 > $this->count;
    }

    /**
     * Get the page number that this iterator represents
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the total number of items in the virtual array
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the total number of pages in the virtual array
     */
    public function getPageCount(): int
    {
        return max(1, ceil($this->count / $this->itemsPerPage));
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     * Note: This implementation requires that next() be called before every eof() will
     * function properly (except the first call).
     */
    public function eof(): bool
    {
        return (($this->theArray == null) || (count($this->theArray) == 0));
    }

    /**
     * Return a boolean indicating whether or not this iterator was empty from the beginning
     */
    public function wasEmpty(): bool
    {
        return $this->wasEmpty;
    }

    /**
     * Convert the iterator into an array
     */
    public function toArray(): array
    {
        if ($this->theArray === null) {
            throw new \Exception('The iterated array has already been discarded!');
        }
        return $this->theArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\VirtualArrayIterator', '\VirtualArrayIterator');
}
