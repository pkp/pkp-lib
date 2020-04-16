<?php

/**
 * @file classes/i18n/LocaleFile.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFile
 * @ingroup i18n
 *
 * @brief Abstraction of a locale file
 */

class LocaleFile {
	/** @var string The identifier for this locale file */
	var $locale;

	/** @var string The filename for this locale file */
	var $filename;

	/**
	 * Constructor.
	 * @param $locale string Key for this locale file
	 * @param $filename string Filename to this locale file
	 */
	function __construct($locale, $filename) {
		$this->locale = $locale;
		$this->filename = $filename;
	}

	/**
	 * Get the filename for this locale file.
	 */
	function getFilename() {
		return $this->filename;
	}

	/**
	 * Translate a string using the selected locale.
	 * Substitution works by replacing tokens like "{$foo}" with the value of
	 * the parameter named "foo" (if supplied).
	 * @param $key string
	 * @param $params array named substitution parameters
	 * @param $locale string the locale to use
	 * @return string
	 */
	function translate($key, $params = array(), $locale = null) {
		if (!$this->isValid()) return null;
		$key = trim($key);
		if ($key === '') return '';

		$pool = new Stash\Pool(Core::getStashDriver());
		$item = $pool->getItem('locale-' . md5($this->getFilename()));
		if ($item->isMiss() || filemtime($this->filename) >= $item->getCreation()->getTimestamp()) {
			$item->set(LocaleFile::load($this->filename));
			$pool->save($item);
		}
		$messages = $item->get();

		if (!isset($messages[$key])) return null;
		$message = $messages[$key];

		// Substitute custom parameters
		foreach ($params as $key => $value) {
			$message = str_replace("{\$$key}", $value, $message);
		}

		// if client encoding is set to iso-8859-1, transcode string from utf8 since we store all XML files in utf8
		if (LOCALE_ENCODING == "iso-8859-1") $message = utf8_decode($message);

		return $message;
	}

	/**
	 * Static method: Load a locale array from a file. Not cached!
	 * @param $filename string Filename to locale XML to load
	 * @param array
	 */
	static function &load($filename) {
		$localeData = array();
		// This compatibility code for XML file fallback will eventually be removed.
		// See https://github.com/pkp/pkp-lib/issues/5090.
		if (file_exists($filename) && substr($filename, -3) == '.po') {
			// Prefer a PO file, if one exists.
			$translations = Gettext\Translations::fromPoFile($filename);
			foreach ($translations as $translation) {
				$localeData[$translation->getOriginal()] = $translation->getTranslation();
			}
		} else {
			// Try a fallback to an old-style XML locale file.
			$xmlFilename = preg_replace('/\.po$/', '.xml', $filename);
			if ($xmlFilename && file_exists($xmlFilename)) {
				$xmlDao = new XMLDAO();
				$data = $xmlDao->parseStruct($filename, array('message'));

				// Build array with ($key => $string)
				if (isset($data['message'])) {
					foreach ($data['message'] as $messageData) {
						$localeData[$messageData['attributes']['key']] = $messageData['value'];
					}
				}
			}
		}
		return $localeData;
	}

	/**
	 * Check if a locale is valid.
	 * @param $locale string
	 * @return boolean
	 */
	function isValid() {
		return isset($this->locale) && file_exists($this->filename);
	}

	/**
	 * Test a locale file against the given reference locale file and
	 * return an array of errorType => array(errors).
	 * @param $referenceLocaleFile object
	 * @return array
	 */
	function testLocale(&$referenceLocaleFile) {
		$errors = array(
			LOCALE_ERROR_MISSING_KEY => array(),
			LOCALE_ERROR_EXTRA_KEY => array(),
			LOCALE_ERROR_DIFFERING_PARAMS => array(),
			LOCALE_ERROR_MISSING_FILE => array()
		);

		if ($referenceLocaleFile->isValid()) {
			if (!$this->isValid()) {
				$errors[LOCALE_ERROR_MISSING_FILE][] = array(
					'locale' => $this->locale,
					'filename' => $this->filename
				);
				return $errors;
			}
		} else {
			// If the reference file itself does not exist or is invalid then
			// there's nothing to be translated here.
			return $errors;
		}

		$localeContents = LocaleFile::load($this->filename);
		$referenceContents = LocaleFile::load($referenceLocaleFile->filename);

		foreach ($referenceContents as $key => $referenceValue) {
			if (!isset($localeContents[$key])) {
				$errors[LOCALE_ERROR_MISSING_KEY][] = array(
					'key' => $key,
					'locale' => $this->locale,
					'filename' => $this->filename,
					'reference' => $referenceValue
				);
				continue;
			}
			$value = $localeContents[$key];

			$referenceParams = AppLocale::getParameterNames($referenceValue);
			$params = AppLocale::getParameterNames($value);
			if (count(array_diff($referenceParams, $params)) > 0) {
				$errors[LOCALE_ERROR_DIFFERING_PARAMS][] = array(
					'key' => $key,
					'locale' => $this->locale,
					'mismatch' => array_diff($referenceParams, $params),
					'filename' => $this->filename,
					'reference' => $referenceValue,
					'value' => $value
				);
			}
			// After processing a key, remove it from the list;
			// this way, the remainder at the end of the loop
			// will be extra unnecessary keys.
			unset($localeContents[$key]);
		}

		// Leftover keys are extraneous.
		foreach ($localeContents as $key => $value) {
			$errors[LOCALE_ERROR_EXTRA_KEY][] = array(
				'key' => $key,
				'locale' => $this->locale,
				'filename' => $this->filename
			);
		}

		return $errors;
	}
}


