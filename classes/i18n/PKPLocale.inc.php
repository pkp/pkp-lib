<?php

/**
 * @defgroup i18n
 */

/**
 * @file classes/i18n/PKPLocale.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup i18n
 *
 * @brief Provides methods for loading locale data and translating strings identified by unique keys
 */

// $Id$


import('i18n.LocaleFile');

define('LOCALE_REGISTRY_FILE', Config::getVar('general', 'registry_dir') . DIRECTORY_SEPARATOR . 'locales.xml');
define('LOCALE_DEFAULT', Config::getVar('i18n', 'locale'));
define('LOCALE_ENCODING', Config::getVar('i18n', 'client_charset'));

define('MASTER_LOCALE', 'en_US');

// Error types for locale checking.
// Note: Cannot use numeric symbols for the constants below because
// array_merge_recursive doesn't treat numeric keys nicely.
define('LOCALE_ERROR_MISSING_KEY',		'LOCALE_ERROR_MISSING_KEY');
define('LOCALE_ERROR_EXTRA_KEY',		'LOCALE_ERROR_EXTRA_KEY');
define('LOCALE_ERROR_DIFFERING_PARAMS',		'LOCALE_ERROR_DIFFERING_PARAMS');
define('LOCALE_ERROR_MISSING_FILE',		'LOCALE_ERROR_MISSING_FILE');

define('EMAIL_ERROR_MISSING_EMAIL',		'EMAIL_ERROR_MISSING_EMAIL');
define('EMAIL_ERROR_EXTRA_EMAIL',		'EMAIL_ERROR_EXTRA_EMAIL');
define('EMAIL_ERROR_DIFFERING_PARAMS',		'EMAIL_ERROR_DIFFERING_PARAMS');

// Locale components
define('LOCALE_COMPONENT_PKP_COMMON',		0x00000001);
define('LOCALE_COMPONENT_PKP_ADMIN',		0x00000002);
define('LOCALE_COMPONENT_PKP_INSTALLER',	0x00000003);
define('LOCALE_COMPONENT_PKP_MANAGER',		0x00000004);
define('LOCALE_COMPONENT_PKP_READER',		0x00000005);
define('LOCALE_COMPONENT_PKP_SUBMISSION',	0x00000006);
define('LOCALE_COMPONENT_PKP_USER',		0x00000007);
define('LOCALE_COMPONENT_PKP_GRID', 	0x00000008);

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
		if (!isset($locale)) $locale = AppLocale::getLocale();
		if (($key = trim($key)) == '') return '';

		$localeFiles =& AppLocale::getLocaleFiles($locale);
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
		$locale = AppLocale::getLocale();

		$sysLocale = $locale . '.' . LOCALE_ENCODING;
		if (!@setlocale(LC_ALL, $sysLocale, $locale)) {
			// For PHP < 4.3.0
			if(setlocale(LC_ALL, $sysLocale) != $sysLocale) {
				setlocale(LC_ALL, $locale);
			}
		}

		AppLocale::registerLocaleFile($locale, "lib/pkp/locale/$locale/common.xml");
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
			$filenameComponentMap[$locale] = AppLocale::makeComponentMap($locale);
		}
		return $filenameComponentMap[$locale];
	}

	function requireComponents($components, $locale = null) {
		$loadedComponents =& Registry::get('loadedLocaleComponents', true, array());
		if ($locale === null) $locale = AppLocale::getLocale();
		$filenameComponentMap = AppLocale::getFilenameComponentMap($locale);
		foreach ($components as $component) {
			// Don't load components twice
			if (isset($loadedComponents[$locale][$component])) continue;

			if (!isset($filenameComponentMap[$component])) fatalError('Unknown locale component ' . $component);
			$filename = $filenameComponentMap[$component];
			AppLocale::registerLocaleFile($locale, $filename);
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
		$localeFiles =& AppLocale::getLocaleFiles($locale);
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
		$allLocales =& AppLocale::_getAllLocalesCache();
		$contents = $allLocales->getContents();
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
		$allLocales =& AppLocale::_getAllLocalesCache();
		$contents = $allLocales->getContents();
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
	 * Get the cache object for the current list of all locales.
	 */
	function &_getAllLocalesCache() {
		$cache =& Registry::get('allLocalesCache', true, null);
		if ($cache === null) {
			$cacheManager =& CacheManager::getManager();
			$cache = $cacheManager->getFileCache(
				'locale', 'list',
				array('AppLocale', '_allLocalesCacheMiss')
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

	function _allLocalesCacheMiss(&$cache, $id) {
		$allLocales =& Registry::get('allLocales', true, null);
		if ($allLocales === null) {
			// Add a locale load to the debug notes.
			$notes =& Registry::get('system.debug.notes');
			$notes[] = array('debug.notes.localeListLoad', array('localeList' => LOCALE_REGISTRY_FILE));

			// Reload locale registry file
			$allLocales = AppLocale::loadLocaleList(LOCALE_REGISTRY_FILE);
			asort($allLocales);
			$cache->setEntireCache($allLocales);
		}
		return null;
	}

	/**
	 * Return a list of all available locales.
	 * @return array
	 */
	function &getAllLocales() {
		$cache =& AppLocale::_getAllLocalesCache();
		$rawContents = $cache->getContents();
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
	 * Uninstall support for an existing locale.
	 * @param $locale string
	 */
	function installLocale($locale) {
		// Install default locale-specific data
		import('db.DBDataXMLParser');

		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->installEmailTemplateData($emailTemplateDao->getMainEmailTemplateDataFilename($locale));

		// Load all plugins so they can add locale data if needed
		$categories = PluginRegistry::getCategories();
		foreach ($categories as $category) {
			PluginRegistry::loadCategory($category, true);
		}
		HookRegistry::call('PKPLocale::installLocale', array(&$locale));
	}

	/**
	 * Install support for a new locale.
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
		AppLocale::uninstallLocale($locale);
		AppLocale::installLocale($locale);
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
		if (isset($matches[0])) return $matches[0];
		return array();
	}
}

/**
 * Wrapper around PKPLocale::translate().
 *
 * Enables us to work with translated strings everywhere without
 * introducing a lot of duplicate code and without getting
 * blisters on our fingers.
 *
 * This is similar to WordPress' solution for translation, see
 * <http://codex.wordpress.org/Translating_WordPress>.
 *
 * @see PKPLocale::translate()
 *
 * @param $key string
 * @param $params array named substitution parameters
 * @param $locale string the locale to use
 * @return string
 */
function __($key, $params = array(), $locale = null) {
	return PKPLocale::translate($key, $params, $locale);
}

?>
