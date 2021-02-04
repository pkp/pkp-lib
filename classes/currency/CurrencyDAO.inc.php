<?php

/**
 * @file classes/currency/CurrencyDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CurrencyDAO
 * @ingroup currency
 * @see Currency
 * @deprecated Use \Sokil\IsoCodes directly.
 *
 * @brief Operations for retrieving and modifying Currency objects.
 *
 */

import('lib.pkp.classes.currency.Currency');

class CurrencyDAO extends DAO {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Parent constructor intentionally not called
	}

	/**
	 * Retrieve a currency by alpha currency ID.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @param $codeAlpha string
	 * @return Currency
	 */
	public function getCurrencyByAlphaCode($codeAlpha) {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$currency = $isoCodes->getCurrencies()->getByLetterCode($codeAlpha);
		return $this->_fromIsoCodeFactoryObject($currency);
	}

	/**
	 * Retrieve an array of all currencies.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @return array of Currencies
	 */
	public function getCurrencies() {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		return array_map(function($currency) {
			return $this->_fromIsoCodeFactoryObject($currency);
		}, iterator_to_array($isoCodes->getCurrencies()));
	}

	/**
	 * Create and populate a DataObject-based Currency from the \Sokil\IsoCodes equivalent.
	 * @deprecated
	 * @param $currency Object
	 * @return Currency
	 */
	protected function _fromIsoCodeFactoryObject($currency) {
		$currencyDataObject = new Currency();
		$currencyDataObject->setCodeAlpha($currency->getLetterCode());
		$currencyDataObject->setName($currency->getLocalName());
		$currencyDataObject->setCodeNumeric($currency->getNumericCode());
		return $currencyDataObject;
	}
}

