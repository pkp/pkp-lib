<?php

/**
 * @defgroup language Language
 * Language and internationalization code.
 */

/**
 * @file classes/language/Language.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Language
 * @ingroup language
 * @see LanguageDAO
 * @deprecated Use \Sokil\IsoCodes directly.
 *
 * @brief Basic class describing a language.
 *
 */

class Language extends DataObject {

	//
	// Get/set methods
	//

	/**
	 * Get the name of the language.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @return string
	 */
	function getName() {
		return $this->getData('name');
	}

	/**
	 * Set the name of the language.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @param $name string
	 */
	function setName($name) {
		$this->setData('name', $name);
	}

	/**
	 * Get language code.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @return string
	 */
	function getCode() {
		return $this->getData('code');
	}

	/**
	 * Set language code.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @param $code string
	 */
	function setCode($code) {
		$this->setData('code', $code);
	}
}

