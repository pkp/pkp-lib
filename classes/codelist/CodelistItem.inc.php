<?php

/**
 * @defgroup codelist
 */

/**
 * @file classes/codelist/CodelistItem.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CodelistItem
 * @ingroup codelist
 * @see CodelistItemDAO
 *
 * @brief Basic class describing a codelist item.
 *
 */

class CodelistItem extends DataObject {
	/**
	 * Constructor
	 */

	function CodelistItem() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * Get the text component of the codelist.
	 * @return string
	 */
	function getText() {
		return $this->getData('text');
	}

	/**
	 * Set the text component of the codelist.
	 * @param $text string
	 */
	function setText($text) {
		return $this->setData('text', $text);
	}

	/**
	 * Get codelist code.
	 * @return string
	 */
	function getCode() {
		return $this->getData('code');
	}

	/**
	 * Set codelist code.
	 * @param $code string
	 */
	function setCode($code) {
		return $this->setData('code', $code);
	}

	/**
	 * @return String the numerical value representing this item in the ONIX 3.0 schema
	 */
	function getOnixSubjectSchemeIdentifier() {
		assert(false); // provided by subclasses
	}
}

?>
