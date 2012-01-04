<?php

/**
 * @file classes/language/LanguageDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LanguageDAO
 * @ingroup language
 * @see Language
 *
 * @brief Operations for retrieving and modifying Language objects.
 *
 */

import('lib.pkp.classes.language.Language');

class LanguageDAO extends DAO {

	/**
	 * Constructor.
	 */
	function LanguageDAO() {
		parent::DAO();
	}

	function &_getCache() {
		$locale = AppLocale::getLocale();
		$cache =& Registry::get('languageCache', true, null);
		if ($cache === null) {
			$cacheManager = CacheManager::getManager();
			$cache =& $cacheManager->getFileCache(
				'languages', $locale,
				array(&$this, '_cacheMiss')
			);
			$cacheTime = $cache->getCacheTime();
			if ($cacheTime !== null && $cacheTime < filemtime($this->getLanguageFilename($locale))) {
				$cache->flush();
			}
		}

		return $cache;
	}

	function _cacheMiss(&$cache, $id) {
		$allLanguages =& Registry::get('allLanguages', true, null);
		if ($allLanguages === null) {
			// Add a locale load to the debug notes.
			$notes =& Registry::get('system.debug.notes');
			$filename = $this->getLanguageFilename(AppLocale::getLocale());
			$notes[] = array('debug.notes.languageListLoad', array('filename' => $filename));

			// Reload locale registry file
			$xmlDao = new XMLDAO();
			$data = $xmlDao->parseStruct($filename, array('language'));

			// Build array with ($charKey => array(stuff))
			if (isset($data['language'])) {
				foreach ($data['language'] as $languageData) {
					$allLanguages[$languageData['attributes']['code']] = array(
						$languageData['attributes']['name'],
					);
				}
			}
			asort($allLanguages);
			$cache->setEntireCache($allLanguages);
		}
		return null;
	}

	/**
	 * Get the filename of the language database
	 * @param $locale string
	 * @return string
	 */
	function getLanguageFilename($locale) {
		return "lib/pkp/locale/$locale/languages.xml";
	}

	/**
	 * Retrieve a language by code.
	 * @param $languageId int
	 * @return Language
	 */
	function &getLanguageByCode($code) {
		$cache =& $this->_getCache();
		$returner =& $this->_returnLanguageFromRow($code, $cache->get($code));
		return $returner;
	}

	/**
	 * Retrieve an array of all languages.
	 * @return array of Languages
	 */
	function &getLanguages() {
		$cache =& $this->_getCache();
		$returner = array();
		foreach ($cache->getContents() as $code => $entry) {
			$returner[] =& $this->_returnLanguageFromRow($code, $entry);
		}
		return $returner;
	}

	/**
	 * Internal function to return a Language object from a row.
	 * @param $row array
	 * @return Language
	 */
	function &_returnLanguageFromRow($code, &$entry) {
		$language = new Language();
		$language->setCode($code);
		$language->setName($entry[0]);

		HookRegistry::call('LanguageDAO::_returnLanguageFromRow', array(&$language, &$code, &$entry));

		return $language;
	}
}

?>
