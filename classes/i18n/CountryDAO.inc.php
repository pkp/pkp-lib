<?php

/**
 * @file classes/i18n/CountryDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CountryDAO
 * @package i18n
 * @deprecated Use \Sokil\IsoCodes directly.
 *
 * @brief Provides methods for loading localized country name data.
 *
 */


class CountryDAO extends DAO {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Parent constructor intentionally not called
	}

	/**
	 * Return a list of all countries.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @param $locale string Name of locale (optional)
	 * @return array
	 */
	public function getCountries() {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		return $countries;
	}

	/**
	 * Return a translated country name, given a code.
	 * @deprecated Use \Sokil\IsoCodes directly.
	 * @return string?
	 */
	public function getCountry($code) {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$country = $isoCodes->getCountries()->getCountry($code);
		return $country?$country->getLocalName():null;
	}
}

