<?php

/**
 * @file classes/db/DAOResultIterator.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAOResultIterator
 *
 * @ingroup db
 *
 * @brief Wrapper around a DAOResultFactory providing a proper PHP Iterator implementation
 */

namespace PKP\db;

class DAOResultIterator implements \Iterator, \Countable
{
    /** @var DAOResultFactory */
    public $_resultFactory;

    /** @var \PKP\core\DataObject Current return value data object. */
    public $_current = null;

    /** @var int $_i 0-based index of current data object. */
    public $_i = 0;

    /**
     * Create an Iterator for the specified DAOResultFactory.
     */
    public function __construct($resultFactory)
    {
        $this->_resultFactory = $resultFactory;
        $this->_current = $this->_resultFactory->next();
    }

    /**
     * @copydoc Iterator::current
     */
    public function current(): mixed
    {
        return $this->_current;
    }

    /**
     * Return the 0-based index for the current object.
     * Note that this is NOT the DataObject's ID -- for that, call
     * getId() on the current element.
     *
     * @return int|null
     */
    public function key(): mixed
    {
        if (!$this->_current) {
            return null;
        }
        return $this->_i;
    }

    /**
     * @copydoc Iterator::next()
     */
    public function next(): void
    {
        $this->_current = $this->_resultFactory->next();
        $this->_i++;
    }

    /**
     * Rewind the DAOResultFactory to the beginning. WARNING that this
     * operation is not arbitrarily supported -- it can only be called
     * before the first call to `next()`.
     */
    public function rewind(): void
    {
        if ($this->_i != 0) {
            throw new \Exception('DAOResultIterator currently does not support rewind() once iteration has started.');
        }
    }

    /**
     * @copydoc Iterator::valid()
     */
    public function valid(): bool
    {
        return ($this->_current !== null);
    }

    /**
     * @copydoc Countable::count()
     */
    public function count(): int
    {
        return $this->_resultFactory->getCount();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\db\DAOResultIterator', '\DAOResultIterator');
}
