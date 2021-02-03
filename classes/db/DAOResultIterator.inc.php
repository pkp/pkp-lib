<?php

/**
 * @file classes/db/DAOResultIterator.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAOResultIterator
 * @ingroup db
 *
 * @brief Wrapper around a DAOResultFactory providing a proper PHP Iterator implementation
 */


class DAOResultIterator implements Iterator, Countable {
	/** @var DAOResultFactory */
	var $_resultFactory;

	/** @var DataObject Current return value data object. */
	var $_current = null;

	/** @var $_i int 0-based index of current data object. */
	var $_i = 0;

	/**
	 * Create an Iterator for the specified DAOResultFactory.
	 * @param $itemIterator ItemIterator
	 */
	public function __construct($resultFactory) {
		$this->_resultFactory = $resultFactory;
		$this->_current = $this->_resultFactory->next();
	}

	/**
	 * @copydoc Iterator::current
	 */
	public function current() {
		return $this->_current;
	}

	/**
	 * Return the 0-based index for the current object.
	 * Note that this is NOT the DataObject's ID -- for that, call
	 * getId() on the current element.
	 * @return int|null
	 */
	public function key() {
		if (!$this->_current) return null;
		return $this->_i;
		return $this->_current->getId();
	}

	/**
	 * @copydoc Iterator::next()
	 */
	public function next() {
		$this->_current = $this->_resultFactory->next();
		$this->_i++;
	}

	/**
	 * Rewind the DAOResultFactory to the begining. WARNING that this
	 * operation is not arbitrarily supported -- it can only be called
	 * before the first call to `next()`.
	 */
	public function rewind() {
		if ($this->_i != 0) throw new Exception('DAOResultIterator currently does not support rewind() once iteration has started.');
	}

	/**
	 * @copydoc Iterator::valid()
	 */
	public function valid() {
		return ($this->_current !== null);
	}

	/**
	 * @copydoc Countable::count()
	 */
	public function count() {
		return $this->_resultFactory->getCount();
	}
}

