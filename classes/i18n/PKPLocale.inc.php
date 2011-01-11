<?php

/**
 * @defgroup i18n
 */

/**
 * @file classes/i18n/PKPLocale.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup i18n
 *
 * @brief Provides methods for loading locale data and translating strings identified by unique keys
 */


import('lib.pkp.classes.i18n.LocaleFile');

if (!defined('LOCALE_REGISTRY_FILE')) {
	define('LOCALE_REGISTRY_FILE', Config::getVar('general', 'registry_dir') . DIRECTORY_SEPARATOR . 'locales.xml');
}
if (!defined('LOCALE_DEFAULT')) {
	define('LOCALE_DEFAULT', Config::getVar('i18n', 'locale'));
}
if (!defined('LOCALE_ENCODING')) {
	define('LOCALE_ENCODING', Config::getVar('i18n', 'client_charset'));
}

define('MASTER_LOCALE', 'en_US');

// Error types for locale checking.
// Note: Cannot use numeric symbols for the constants below because
// array_merge_recursive doesn't treat numeric keys nicely.
define('LOCALE_ERROR_MISSING_KEY', 'LOCALE_ERROR_MISSING_KEY');
define('LOCALE_ERROR_EXTRA_KEY', 'LOCALE_ERROR_EXTRA_KEY');
define('LOCALE_ERROR_DIFFERING_PARAMS', 'LOCALE_ERROR_DIFFERING_PARAMS');
define('LOCALE_ERROR_MISSING_FILE', 'LOCALE_ERROR_MISSING_FILE');

define('EMAIL_ERROR_MISSING_EMAIL', 'EMAIL_ERROR_MISSING_EMAIL');
define('EMAIL_ERROR_EXTRA_EMAIL', 'EMAIL_ERROR_EXTRA_EMAIL');
define('EMAIL_ERROR_DIFFERING_PARAMS', 'EMAIL_ERROR_DIFFERING_PARAMS');

// Locale components
define('LOCALE_COMPONENT_PKP_COMMON', 0x00000001);
define('LOCALE_COMPONENT_PKP_ADMIN', 0x00000002);
define('LOCALE_COMPONENT_PKP_INSTALLER', 0x00000003);
define('LOCALE_COMPONENT_PKP_MANAGER', 0x00000004);
define('LOCALE_COMPONENT_PKP_READER', 0x00000005);
define('LOCALE_COMPONENT_PKP_SUBMISSION', 0x00000006);
define('LOCALE_COMPONENT_PKP_USER', 0x00000007);
define('LOCALE_COMPONENT_PKP_GRID', 0x00000008);

class PKPLocale {
	/**
	 * Get a list of locale files currently registered, either in all
	 * locales (in an array for each locale), or for a specific locale.
	 * @param $locale string Locale identifier (optional)
	 */
	function &getLocaleFiles($locale = null) {
		$localeFiles =& Registry::get('localeFiles', true, array());
		if ($locale !== null) {
			if (!isset($localeFiles[$locale])) $localeFiles[$locale] = array();
			return $localeFiles[$locale];
		}
		return $localeFiles;
	}

	/**
	 * Translate a string using the selected locale.
	 * Substitution works by replacing tokens like "{$foo}" with the value
	 * of the parameter named "foo" (if supplied).
	 * @param $key string
	 * @param $params array named substitution parameters
	 * @param $locale string the locale to use
	 * @return string
	 */
	function translate($key, $params = array(), $locale = null) {
		if (!isset($locale)) $locale = Locale::getLocale();
		if (($key = trim($key)) == '') return '';

		$localeFiles =& Locale::getLocaleFiles($locale);
		$value = '';
		for ($i = 0; $i < count($localeFiles); $i++) { // By reference
			$value = $localeFiles[$i]->translate($key, $params);
			if ($value !== null) return $value;
		}

		// Add a missing key to the debug notes.
		$notes =& Registry::get('system.debug.notes');
		$notes[] = array('debug.notes.missingLocaleKey', array('key' => $key));

		// Add some octothorpes to missing keys to make them more obvious
		return '##' . $key . '##';
	}

	/**
	 * Initialize the locale system.
	 */
	function initialize() {
		// Use defaults if locale info unspecified.
		$locale = Locale::getLocale();

		$sysLocale = $locale . '.' . LOCALE_ENCODING;
		if (!@setlocale(LC_ALL, $sysLocale, $locale)) {
			// For PHP < 4.3.0
			if(setlocale(LC_ALL, $sysLocale) != $sysLocale) {
				setlocale(LC_ALL, $locale);
			}
		}

		Locale::registerLocaleFile($locale, "lib/pkp/locale/$locale/common.xml");
	}

	function makeComponentMap($locale) {
		$baseDir = "lib/pkp/locale/$locale/";

		return array(
			LOCALE_COMPONENT_PKP_COMMON => $baseDir . 'common.xml',
			LOCALE_COMPONENT_PKP_ADMIN => $baseDir . 'admin.xml',
			LOCALE_COMPONENT_PKP_INSTALLER => $baseDir . 'installer.xml',
			LOCALE_COMPONENT_PKP_MANAGER => $baseDir . 'manager.xml',
			LOCALE_COMPONENT_PKP_READER => $baseDir . 'reader.xml',
			LOCALE_COMPONENT_PKP_SUBMISSION => $baseDir . 'submission.xml',
			LOCALE_COMPONENT_PKP_USER => $baseDir . 'user.xml',
			LOCALE_COMPONENT_PKP_GRID => $baseDir . 'grid.xml'
		);
	}

	function getFilenameComponentMap($locale) {
		$filenameComponentMap =& Registry::get('localeFilenameComponentMap', true, array());
		if (!isset($filenameComponentMap[$locale])) {
			$filenameComponentMap[$locale] = Locale::makeComponentMap($locale);
		}
		return $filenameComponentMap[$locale];
	}

	function requireComponents($components, $locale = null) {
		$loadedComponents =& Registry::get('loadedLocaleComponents', true, array());
		if ($locale === null) $locale = Locale::getLocale();
		$filenameComponentMap = Locale::getFilenameComponentMap($locale);
		foreach ($components as $component) {
			// Don't load components twice
			if (isset($loadedComponents[$locale][$component])) continue;

			if (!isset($filenameComponentMap[$component])) fatalError('Unknown locale component ' . $component);
			$filename = $filenameComponentMap[$component];
			Locale::registerLocaleFile($locale, $filename);
			$loadedComponents[$locale][$component] = true;
		}
	}

	/**
	 * Register a locale file against the current list.
	 * @param $locale string Locale key
	 * @param $filename string Filename to new locale XML file
	 * @param $addToTop boolean Whether to add to the top of the list (true)
	 * 	or the bottom (false). Allows overriding.
	 */
	function &registerLocaleFile ($locale, $filename, $addToTop = false) {
		$localeFiles =& Locale::getLocaleFiles($locale);
		$localeFile = new LocaleFile($locale, $filename);
		if (!$localeFile->isValid()) {
			$localeFile = null;
			return $localeFile;
		}
		if ($addToTop) {
			// Work-around: unshift by reference.
			array_unshift($localeFiles, '');
			$localeFiles[0] =& $localeFile;
		} else {
			$localeFiles[] =& $localeFile;
		}
		HookRegistry::call('PKPLocale::registerLocaleFile', array(&$locale, &$filename, &$addToTop));
		return $localeFile;
	}

	function getLocaleStyleSheet($locale) {
		$contents =& Locale::_getAllLocalesCacheContent();
		if (isset($contents[$locale]['stylesheet'])) {
			return $contents[$locale]['stylesheet'];
		}
		return null;
	}

	/**
	 * Determine whether or not a locale is marked incomplete.
	 * @param $locale xx_XX symbolic name of locale to check
	 * @return boolean
	 */
	function isLocaleComplete($locale) {
		$contents =& Locale::_getAllLocalesCacheContent();
		if (!isset($contents[$locale])) return false;
		if (isset($contents[$locale]['complete']) && $contents[$locale]['complete'] == 'false') {
			return false;
		}
		return true;
	}

	/**
	 * Check if the supplied locale is currently installable.
	 * @param $locale string
	 * @return boolean
	 */
	function isLocaleValid($locale) {
		if (empty($locale)) return false;
		if (!preg_match('/^[a-z][a-z]_[A-Z][A-Z]$/', $locale)) return false;
		if (file_exists('locale/' . $locale)) return true;
		return false;
	}

	/**
	 * Load a locale list from a file.
	 * @param $filename string
	 * @return array
	 */
	function &loadLocaleList($filename) {
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parseStruct($filename, array('locale'));
		$allLocales = array();

		// Build array with ($localKey => $localeName)
		if (isset($data['locale'])) {
			foreach ($data['locale'] as $localeData) {
				$allLocales[$localeData['attributes']['key']] = $localeData['attributes'];
			}
		}

		return $allLocales;
	}

	/**
	 * Return a list of all available locales.
	 * @return array
	 */
	function &getAllLocales() {
		$rawContents =& Locale::_getAllLocalesCacheContent();
		$allLocales = array();

		foreach ($rawContents as $locale => $contents) {
			$allLocales[$locale] = $contents['name'];
		}

		// if client encoding is set to iso-8859-1, transcode locales from utf8
		if (LOCALE_ENCODING == "iso-8859-1") {
			$allLocales = array_map('utf8_decode', $allLocales);
		}

		return $allLocales;
	}

	/**
	 * Install support for a new locale.
	 * @param $locale string
	 */
	function installLocale($locale) {
		// Install default locale-specific data
		import('lib.pkp.classes.db.DBDataXMLParser');

		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->installEmailTemplateData($emailTemplateDao->getMainEmailTemplateDataFilename($locale));

		// Load all plugins so they can add locale data if needed
		$categories = PluginRegistry::getCategories();
		foreach ($categories as $category) {
			PluginRegistry::loadCategory($category);
		}
		HookRegistry::call('PKPLocale::installLocale', array(&$locale));
	}

	/**
	 * Uninstall support for an existing locale.
	 * @param $locale string
	 */
	function uninstallLocale($locale) {
		// Delete locale-specific data
		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->deleteEmailTemplatesByLocale($locale);
		$emailTemplateDao->deleteDefaultEmailTemplatesByLocale($locale);
	}

	/**
	 * Reload locale-specific data.
	 * @param $locale string
	 */
	function reloadLocale($locale) {
		Locale::uninstallLocale($locale);
		Locale::installLocale($locale);
	}

	/**
	 * Given a locale string, get the list of parameter references of the
	 * form {$myParameterName}.
	 * @param $source string
	 * @return array
	 */
	function getParameterNames($source) {
		$matches = null;
		String::regexp_match_all('/({\$[^}]+})/' /* '/{\$[^}]+})/' */, $source, $matches);
		array_shift($matches); // Knock the top element off the array
		return $matches;
	}

	/**
	 * Translate the ISO 2-letter language string (ISO639-1)
	 * into a ISO compatible 3-letter string (ISO639-2b).
	 * @param $iso2Letter string
	 * @return string the translated string or null if we
	 *  don't know about the given language.
	 */
	function get3LetterFrom2LetterIsoLanguage($iso2Letter) {
		assert(strlen($iso2Letter) == 2);
		$locales =& Locale::_getAllLocalesCacheContent();
		foreach($locales as $locale => $localeData) {
			if (substr($locale, 0, 2) == $iso2Letter) {
				assert(isset($localeData['iso639-2b']));
				return $localeData['iso639-2b'];
			}
		}
		return null;
	}

	/**
	 * Translate the ISO 3-letter language string (ISO639-2b)
	 * into a ISO compatible 2-letter string (ISO639-1).
	 * @param $iso3Letter string
	 * @return string the translated string or null if we
	 *  don't know about the given language.
	 */
	function get2LetterFrom3LetterIsoLanguage($iso3Letter) {
		assert(strlen($iso3Letter) == 3);
		$locales =& Locale::_getAllLocalesCacheContent();
		foreach($locales as $locale => $localeData) {
			assert(isset($localeData['iso639-2b']));
			if ($localeData['iso639-2b'] == $iso3Letter) {
				return substr($locale, 0, 2);
			}
		}
		return null;
	}

	/**
	 * Translate the PKP locale identifier into an
	 * ISO639-2b compatible 3-letter string.
	 * @param $locale string
	 * @return string
	 */
	function get3LetterIsoFromLocale($locale) {
		assert(strlen($locale) == 5);
		$iso2Letter = substr($locale, 0, 2);
		return Locale::get3LetterFrom2LetterIsoLanguage($iso2Letter);
	}

	/**
	 * Translate an ISO639-2b compatible 3-letter string
	 * into the PKP locale identifier.
	 *
	 * This can be ambiguous if several locales are defined
	 * for the same language. In this case we'll use the
	 * primary locale to disambiguate.
	 *
	 * If that still doesn't determine a unique locale then
	 * we'll choose the first locale found.
	 *
	 * @param $iso3letter string
	 * @return string
	 */
	function getLocaleFrom3LetterIso($iso3Letter) {
		assert(strlen($iso3Letter) == 3);
		$primaryLocale = Locale::getPrimaryLocale();

		$localeCandidates = array();
		$locales =& Locale::_getAllLocalesCacheContent();
		foreach($locales as $locale => $localeData) {
			assert(isset($localeData['iso639-2b']));
			if ($localeData['iso639-2b'] == $iso3Letter) {
				if ($locale == $primaryLocale) {
					// In case of ambiguity the primary locale
					// overrides all other options so we're done.
					return $primaryLocale;
				}
				$localeCandidates[] = $locale;
			}
		}

		// Return null if we found no candidate locale.
		if (empty($localeCandidates)) return null;

		if (count($localeCandidates) > 1) {
			// Check whether one of the candidate locales
			// is a supported locale. If so choose the first
			// supported locale.
			$supportedLocales = Locale::getSupportedLocales();
			foreach($supportedLocales as $supportedLocale => $localeName) {
				if (in_array($supportedLocale, $localeCandidates)) return $supportedLocale;
			}
		}

		// If there is only one candidate (or if we were
		// unable to disambiguate) then return the unique
		// (first) candidate found.
		return array_shift($localeCandidates);
	}

	//
	// Private helper methods.
	//
	/**
	 * Retrieves locale data from the locales cache.
	 * @return array
	 */
	function &_getAllLocalesCacheContent() {
		static $contents = false;
		if ($contents === false) {
			$allLocalesCache =& Locale::_getAllLocalesCache();
			$contents = $allLocalesCache->getContents();
		}
		return $contents;
	}

	/**
	 * Get the cache object for the current list of all locales.
	 * @return FileCache
	 */
	function &_getAllLocalesCache() {
		$cache =& Registry::get('allLocalesCache', true, null);
		if ($cache === null) {
			$cacheManager =& CacheManager::getManager();
			$cache = $cacheManager->getFileCache(
				'locale', 'list',
				array('Locale', '_allLocalesCacheMiss')
			);

			// Check to see if the data is outdated
			$cacheTime = $cache->getCacheTime();
			if ($cacheTime !== null && $cacheTime < filemtime(LOCALE_REGISTRY_FILE)) {
				$cache->flush();
			}
		}
		return $cache;
	}

	/**
	 * Create a cache file with locale data.
	 * @param $cache CacheManager
	 * @param $id the cache id (not used here, required by the cache manager)
	 */
	function _allLocalesCacheMiss(&$cache, $id) {
		$allLocales =& Registry::get('allLocales', true, null);
		if ($allLocales === null) {
			// Add a locale load to the debug notes.
			$notes =& Registry::get('system.debug.notes');
			$notes[] = array('debug.notes.localeListLoad', array('localeList' => LOCALE_REGISTRY_FILE));

			// Reload locale registry file
			$allLocales = Locale::loadLocaleList(LOCALE_REGISTRY_FILE);
			asort($allLocales);
			$cache->setEntireCache($allLocales);
		}
		return null;
	}
}

?>
