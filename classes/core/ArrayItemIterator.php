<?php

/**
 * @file classes/core/ArrayItemIterator.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArrayItemIterator
 *
 * @ingroup db
 *
 * @brief Provides paging and iteration for arrays.
 */

namespace PKP\core;

class ArrayItemIterator extends ItemIterator
{
    /** @var ?array The array of contents of this iterator. */
    public ?array $theArray;

    /** @var int Number of items to iterate through on this page */
    public int $itemsPerPage;

    /** @var int The current page. */
    public int $page;

    /** @var int The total number of items. */
    public int $count;

    /** @var bool Whether or not the iterator was empty from the start */
    public bool $wasEmpty;

    /**
     * Constructor.
     *
     * @param $theArray The array of items to iterate through
     * @param $page the current page number
     * @param $itemsPerPage Number of items to display per page
     */
    public function __construct(array $theArray, int $page = -1, int $itemsPerPage = -1)
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
     * Return the next item in the iterator.
     */
    public function next(): mixed
    {
        if (!is_array($this->theArray)) {
            return null;
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
    public function nextWithKey(): array
    {
        $key = key($this->theArray);
        $value = $this->next();
        return [$key, $value];
    }

    /**
     * Determine whether or not this iterator represents the first page
     */
    public function atFirstPage(): bool
    {
        return $this->page == 1;
    }

    /**
     * Determine whether or not this iterator represents the last page
     */
    public function atLastPage(): bool
    {
        return ($this->page * $this->itemsPerPage) + 1 > $this->count;
    }

    /**
     * Get the current page number
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the total count of items
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the number of pages
     */
    public function getPageCount(): int
    {
        return max(1, ceil($this->count / $this->itemsPerPage));
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
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
     * Convert this iterator to an array
     */
    public function toArray(): array
    {
        if ($this->theArray === null) {
            throw new \Exception('Iterated array has already been discarded.');
        }
        return $this->theArray;
    }

    /**
     * Return this iterator as an associative array.
     */
    public function toAssociativeArray(): ?array
    {
        return $this->theArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\ArrayItemIterator', '\ArrayItemIterator');
}
