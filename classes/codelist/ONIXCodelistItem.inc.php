<?php

/**
 * @defgroup codelist
 */

/**
 * @file classes/codelist/ONIXCodelistItem.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ONIXCodelistItem
 * @ingroup codelist
 * @see ONIXCodelistItemDAO
 *
 * @brief Basic class describing a codelist item.
 *
 */

class ONIXCodelistItem extends DataObject {
	/**
	 * Constructor
	 */

	function ONIXCodelistItem() {
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
}
?>
