<?php

/**
 * @defgroup currency Currency
 * Implements currency data objects for managing lists of currencies for e-commerce.
 */

/**
 * @file classes/currency/Currency.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Currency
 * @ingroup currency
 * @see CurrencyDAO
 *
 * @brief Basic class describing a currency.
 *
 */

class Currency extends DataObject {
	/**
	 * Constructor
	 */
	function Currency() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * Get the name of the currency.
	 * @return string
	 */
	function getName() {
		return $this->getData('name');
	}

	/**
	 * Set the name of the currency.
	 * @param $name string
	 */
	function setName($name) {
		$this->setData('name', $name);
	}

	/**
	 * Get currency alpha code.
	 * @return string
	 */
	function getCodeAlpha() {
		return $this->getData('codeAlpha');
	}

	/**
	 * Set currency alpha code.
	 * @param $alphaCode string
	 */
	function setCodeAlpha($codeAlpha) {
		$this->setData('codeAlpha', $codeAlpha);
	}

	/**
	 * Get currency numeric code.
	 * @return int
	 */
	function getCodeNumeric() {
		return $this->getData('codeNumeric');
	}

	/**
	 * Set currency numeric code.
	 * @param $codeNumeric string
	 */
	function setCodeNumeric($codeNumeric) {
		$this->setData('codeNumeric', $codeNumeric);
	}
}

?>
