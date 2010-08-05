<?php
/**
 * @file classes/citation/PlainTextReferencesList.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PlainTextReferencesList
 * @ingroup citation
 *
 * @brief Class representing an ordered list of plain text citation output.
 */


// Plain text references can be ordered numerically or alphabetically.
define('PLAIN_TEXT_REFERENCES_NUMERIC', 0x01);
define('PLAIN_TEXT_REFERENCES_ALPHABETICAL', 0x02);

import('lib.pkp.classes.core.DataObject');

class PlainTextReferencesList extends DataObject {
	/** @var int the reference list ordering */
	var $_ordering = PLAIN_TEXT_REFERENCES_ALPHABETICAL;


	/**
	 * Constructor.
	 * @param $rawCitation string an unparsed citation string
	 */
	function PlainTextReferencesList() {
		parent::DataObject();
	}


	//
	// Getters and Setters
	//
	/**
	 * Set the ordering
	 * @param $ordering integer
	 */
	function setOrdering($ordering) {
		$this->_ordering = $ordering;
	}

	/**
	 * Get the ordering
	 * @return integer
	 */
	function getOrdering() {
		return $this->_ordering;
	}

}
?>
