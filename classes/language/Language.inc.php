<?php

/**
 * @defgroup language
 */

/**
 * @file classes/language/Language.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Language
 * @ingroup language
 * @see LanguageDAO
 *
 * @brief Basic class describing a language.
 *
 */

class Language extends DataObject {
	/**
	 * Constructor
	 */
	function Language() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * Get the name of the language.
	 * @return string
	 */
	function getName() {
		return $this->getData('name');
	}

	/**
	 * Set the name of the language.
	 * @param $name string
	 */
	function setName($name) {
		return $this->setData('name', $name);
	}

	/**
	 * Get language code.
	 * @return string
	 */
	function getCode() {
		return $this->getData('code');
	}

	/**
	 * Set language code.
	 * @param $code string
	 */
	function setCode($code) {
		return $this->setData('code', $code);
	}

}

?>
