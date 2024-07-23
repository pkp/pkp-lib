<?php

/**
 * @file classes/core/ItemIterator.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ItemIterator
 *
 * @brief Generic iterator class; needs to be overloaded by subclasses
 * providing specific implementations.
 */

namespace PKP\core;

/**
 * @template TKey of array-key
 * @template TValue
 */
class ItemIterator
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Return the next item in the iterator.
     */
    public function next(): mixed
    {
        return null;
    }

    /**
     * Return the next item with key.
     */
    public function nextWithKey(): array
    {
        return [null, null];
    }

    /**
     * Determine whether this iterator represents the first page of a set.
     */
    public function atFirstPage(): bool
    {
        return true;
    }

    /**
     * Determine whether this iterator represents the last page of a set.
     */
    public function atLastPage(): bool
    {
        return true;
    }

    /**
     * Get the page number of a set that this iterator represents.
     */
    public function getPage(): int
    {
        return 1;
    }

    /**
     * Get the total number of items in the set.
     */
    public function getCount(): int
    {
        return 0;
    }

    /**
     * Get the total number of pages in the set.
     */
    public function getPageCount(): int
    {
        return 0;
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     */
    public function eof(): bool
    {
        return true;
    }

    /**
     * Return a boolean indicating whether or not this iterator was empty from the beginning
     */
    public function wasEmpty(): bool
    {
        return true;
    }

    /**
     * Convert this iterator to an array.
     */
    public function toArray(): array
    {
        return [];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\ItemIterator', '\ItemIterator');
}
