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
 * @ingroup db
 *
 * @brief Generic iterator class; needs to be overloaded by subclasses
 * providing specific implementations.
 */

namespace PKP\core;

/**
 * @template TKey
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
     *
     * @return TValue
     */
    public function next()
    {
        return null;
    }

    /**
     * Return the next item with key.
     *
     * @return array<Tkey,TValue>
     */
    public function nextWithKey()
    {
        return [null, null];
    }

    /**
     * Determine whether this iterator represents the first page of a set.
     *
     * @return bool
     */
    public function atFirstPage()
    {
        return true;
    }

    /**
     * Determine whether this iterator represents the last page of a set.
     *
     * @return bool
     */
    public function atLastPage()
    {
        return true;
    }

    /**
     * Get the page number of a set that this iterator represents.
     *
     * @return int
     */
    public function getPage()
    {
        return 1;
    }

    /**
     * Get the total number of items in the set.
     *
     * @return int
     */
    public function getCount()
    {
        return 0;
    }

    /**
     * Get the total number of pages in the set.
     *
     * @return int
     */
    public function getPageCount()
    {
        return 0;
    }

    /**
     * Return a boolean indicating whether or not we've reached the end of results
     *
     * @return bool
     */
    public function eof()
    {
        return true;
    }

    /**
     * Return a boolean indicating whether or not this iterator was empty from the beginning
     *
     * @return bool
     */
    public function wasEmpty()
    {
        return true;
    }

    /**
     * Convert this iterator to an array.
     *
     * @return TValue[]
     */
    public function toArray()
    {
        return [];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\ItemIterator', '\ItemIterator');
}
