<?php

/**
 * @defgroup i18n
 */

/**
 * @file classes/i18n/PKPLocale.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
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
define('LOCALE_ERROR_SUSPICIOUS_LENGTH',	'LOCALE_ERROR_SUSPICIOUS_LENGTH');
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
	 * @params $params array named substitution parameters
	 * @params $locale string the locale to use
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
			LOCALE_COMPONENT_PKP_USER => $baseDir . 'user.xml'
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
		return $localeFile;
	}

	function getLocaleStyleSheet($locale) {
		$allLocales =& Locale::_getAllLocalesCache();
		$contents = $allLocales->getContents();
		if (isset($contents[$locale]['stylesheet'])) {
			return $contents[$locale]['stylesheet'];
		}
		return null;
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
			import('cache.CacheManager');
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
			$allLocales = Locale::loadLocaleList(LOCALE_REGISTRY_FILE);
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
		$cache =& Locale::_getAllLocalesCache();
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
	 * Get the path and filename for the email templates data for the
	 * given locale
	 * @param $locale string
	 * @return string
	 */
	function getEmailTemplateFilename($locale) {
		return 'dbscripts/xml/data/locale/' . $locale . '/email_templates_data.xml';
	}

	function getFilesToInstall($locale) {
		return array(
			Locale::getEmailTemplateFilename($locale)
		);
	}

	/**
	 * Uninstall support for an existing locale.
	 * @param $locale string
	 */
	function installLocale($locale) {
		// Install default locale-specific data
		import('db.DBDataXMLParser');

		$filesToInstall = Locale::getFilesToInstall($locale);

		$dataXMLParser = new DBDataXMLParser();
		foreach ($filesToInstall as $fileName) {
			if (file_exists($fileName)) {
				$sql = $dataXMLParser->parseData($fileName);
				$dataXMLParser->executeData();
			}
		}
		$dataXMLParser->destroy();
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
		Locale::uninstallLocale($locale);
		Locale::installLocale($locale);
	}

	/**
	 * Test all locale files for the supplied locale against the supplied
	 * reference locale, returning an array of errors.
	 * @param $locale string Name of locale to test
	 * @param $referenceLocale string Name of locale to test against
	 * @return array
	 */
	function testLocale($locale, $referenceLocale) {
		$localeFileNames = Locale::getFilenameComponentMap($locale);

		$errors = array();
		foreach ($localeFileNames as $localeFileName) {
			$referenceLocaleFileName = str_replace($locale, $referenceLocale, $localeFileName);
			$localeFile = new LocaleFile($locale, $localeFileName);
			$referenceLocaleFile = new LocaleFile($referenceLocale, $referenceLocaleFileName);
			$errors = array_merge_recursive($errors, $localeFile->testLocale($referenceLocaleFile));
			unset($localeFile);
			unset($referenceLocaleFile);
		}

		$plugins =& PluginRegistry::loadAllPlugins();
		foreach (array_keys($plugins) as $key) {
			$plugin =& $plugins[$key];
			$referenceLocaleFilename = $plugin->getLocaleFilename($referenceLocale);
			if ($referenceLocaleFilename) {
				$localeFile = new LocaleFile($locale, $plugin->getLocaleFilename($locale));
				$referenceLocaleFile = new LocaleFile($referenceLocale, $referenceLocaleFilename);
				$errors = array_merge_recursive($errors, $localeFile->testLocale($referenceLocaleFile));
				unset($localeFile);
				unset($referenceLocaleFile);
			}
			unset($plugin);
		}
		return $errors;
	}

	/**
	 * Test the emails in the supplied locale against those in the supplied
	 * reference locale.
	 * @param $locale string
	 * @param $referenceLocale string
	 * @return array List of errors
	 */
	function testEmails($locale, $referenceLocale) {
		$errors = array(
		);

		$xmlParser = new XMLParser();
		$referenceEmails =& $xmlParser->parse(Locale::getEmailTemplateFilename($referenceLocale));
		$emails =& $xmlParser->parse(Locale::getEmailTemplateFilename($locale));
		$emailsTable =& $emails->getChildByName('table');
		$referenceEmailsTable =& $referenceEmails->getChildByName('table');
		$matchedReferenceEmails = array();

		// Pass 1: For all translated emails, check that they match
		// against reference translations.
		for ($emailIndex = 0; ($email =& $emailsTable->getChildByName('row', $emailIndex)) !== null; $emailIndex++) {
			// Extract the fields from the email to be tested.
			$fields = Locale::extractFields($email);

			// Locate the reference email and extract its fields.
			for ($referenceEmailIndex = 0; ($referenceEmail =& $referenceEmailsTable->getChildByName('row', $referenceEmailIndex)) !== null; $referenceEmailIndex++) {
				$referenceFields = Locale::extractFields($referenceEmail);
				if ($referenceFields['email_key'] == $fields['email_key']) break;
			}

			// Check if a matching reference email was found.
			if (!isset($referenceEmail) || $referenceEmail === null) {
				$errors[EMAIL_ERROR_EXTRA_EMAIL][] = array(
					'key' => $fields['email_key']
				);
				continue;
			}

			// We've successfully found a matching reference email.
			// Compare it against the translation.
			$bodyParams = Locale::getParameterNames($fields['body']);
			$referenceBodyParams = Locale::getParameterNames($referenceFields['body']);
			$diff = array_diff($bodyParams, $referenceBodyParams);
			if (!empty($diff)) {
				$errors[EMAIL_ERROR_DIFFERING_PARAMS][] = array(
					'key' => $fields['email_key'],
					'mismatch' => $diff
				);
			}

			$subjectParams = Locale::getParameterNames($fields['subject']);
			$referenceSubjectParams = Locale::getParameterNames($referenceFields['subject']);

			$diff = array_diff($subjectParams, $referenceSubjectParams);
			if (!empty($diff)) {
				$errors[EMAIL_ERROR_DIFFERING_PARAMS][] = array(
					'key' => $fields['email_key'],
					'mismatch' => $diff
				);
			}

			$matchedReferenceEmails[] = $fields['email_key'];

			unset($email);
			unset($referenceEmail);
		}

		// Pass 2: Make sure that there are no missing translations.
		for ($referenceEmailIndex = 0; ($referenceEmail =& $referenceEmailsTable->getChildByName('row', $referenceEmailIndex)) !== null; $referenceEmailIndex++) {
			// Extract the fields from the email to be tested.
			$referenceFields = Locale::extractFields($referenceEmail);
			if (!in_array($referenceFields['email_key'], $matchedReferenceEmails)) {
				$errors[EMAIL_ERROR_MISSING_EMAIL][] = array(
					'key' => $referenceFields['email_key']
				);
			}
		}

		return $errors;
	}

	/**
	 * Given a parent XML node, extract child nodes of the following form:
	 * <field name="something">some_value</field>
	 * ... into an associate array $array['something'] = 'some_value';
	 * @param $node object
	 * @return array
	 */
	function extractFields(&$node) {
		$returner = array();
		foreach ($node->getChildren() as $field) if ($field->getName() === 'field') {
			$returner[$field->getAttribute('name')] = $field->getValue();
		}
		return $returner;
	}

	/**
	 * Determine whether or not the lengths of the two supplied values are
	 * "similar".
	 * @param $reference string
	 * @param $value string
	 * @return boolean True if the lengths match very roughly.
	 */
	function checkLengths($reference, $value) {
		$referenceLength = String::strlen($reference);
		$length = String::strlen($value);
		$lengthDifference = abs($referenceLength - $length);
		if ($referenceLength == 0) return ($length == 0);
		if ($lengthDifference / $referenceLength > 1 && $lengthDifference > 10) return false;
		return true;
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
}

?>
