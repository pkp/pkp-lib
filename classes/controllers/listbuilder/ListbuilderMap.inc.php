<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderMap.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderMap
 * @ingroup controllers_listbuilder
 *
 * @brief Utility class representing a simple name / value association
 */

class ListbuilderMap {
	/** @var $key mixed */
	var $key;

	/** @var $value string */
	var $value;

	/**
	 * Constructor
	 */
	function ListbuilderMap($key, $value) {
		$this->key = $key;
		$this->value = $value;
	}

	/**
	 * Get the key for this map
	 * @return mixed
	 */
	function getKey() {
		return $this->key;
	}

	/**
	 * Get the value for this map
	 * @return string
	 */
	function getValue() {
		return $this->value;
	}
}

?>
