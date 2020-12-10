<?php

/**
 * @file classes/language/LanguageDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LanguageDAO
 * @ingroup language
 * @see Language
 * @deprecated Use \Sokil\IsoCodes directly.
 *
 * @brief Operations for retrieving and modifying Language objects.
 *
 */

import('lib.pkp.classes.language.Language');

class LanguageDAO extends DAO {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Parent constructor intentionally not called
	}

	/**
	 * Retrieve a language by code.
	 * @param $code string ISO 639-1
	 * @return Language
	 */
	public function getLanguageByCode($code) {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$languages = $isoCodes->getLanguages(\Sokil\IsoCodes\IsoCodesFactory::OPTIMISATION_MEMORY);
		$language = $languages->getByAlpha2($code);
		return $language?$this->_fromIsoCodeFactoryObject($language):null;
	}

	/**
	 * Retrieve an array of all languages.
	 * @return array of Languages
	 */
	public function getLanguages() {
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory(\Sokil\IsoCodes\IsoCodesFactory::OPTIMISATION_IO);
		return array_values(array_map(function($language) {
			return $this->_fromIsoCodeFactoryObject($language);
		}, array_filter(iterator_to_array($isoCodes->getLanguages()), function($language) {
			return $language->getAlpha2() && $language->getType() == 'L' && $language->getScope() == 'I';
		})));
	}

	/**
	 * Retrieve an array of all languages names.
	 * @return array of Languages names
	 */
	public function getLanguageNames() {
		return array_map(function($language) {
			return $language->getName();
		}, $this->getLanguages());
	}

	/**
	 * Create and populate a DataObject-based Language from the \Sokil\IsoCodes equivalent.
	 * @param $language Object
	 * @return Language
	 */
	protected function _fromIsoCodeFactoryObject($language) {
		$languageDataObject = new Language();
		$languageDataObject->setCode($language->getAlpha2());
		$languageDataObject->setName($language->getLocalName());
		return $languageDataObject;
	}
}


