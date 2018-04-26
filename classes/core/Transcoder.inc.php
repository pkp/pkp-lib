<?php

/**
 * @file classes/core/Transcoder.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Transcoder
 * @ingroup db
 *
 * @brief Multi-class transcoder; uses mbstring and iconv if available, otherwise falls back to built-in classes
 */


class Transcoder {
	/** @var string Name of source encoding */
	protected $_fromEncoding;

	/** @var string Name of target encoding */
	protected $_toEncoding;

	/** @var boolean Whether or not to transliterate while transcoding */
	protected $_translit;

	/**
	 * Constructor
	 * @param $fromEncoding string Name of source encoding
	 * @param $toEncoding string Name of target encoding
	 * @param $translit boolean Whether or not to transliterate while transcoding
	 */
	public function __construct($fromEncoding, $toEncoding, $translit = false) {
		$this->_fromEncoding = $fromEncoding;
		$this->_toEncoding = $toEncoding;
		$this->_translit = $translit;
	}

	/**
	 * Transcode a string
	 * @param $string string String to transcode
	 * @return string Result of transcoding
	 */
	public function trans($string) {
		// detect existence of encoding conversion libraries
		$mbstring = function_exists('mb_convert_encoding');
		$iconv = function_exists('iconv');

		// don't do work unless we have to
		if (strtolower($this->_fromEncoding) == strtolower($this->_toEncoding)) {
			return $string;
		}

		// 'HTML-ENTITIES' is not a valid encoding for iconv, so transcode manually
		if ($this->_toEncoding == 'HTML-ENTITIES' && !$mbstring) {
			return htmlentities($string, ENT_COMPAT, $this->_fromEncoding, false);

		} elseif ($this->_fromEncoding == 'HTML-ENTITIES' && !$mbstring) {
			return html_entity_decode($string, ENT_COMPAT, $this->_toEncoding);
		// Special cases for transliteration ("down-sampling")
		} elseif ($this->_translit && $iconv) {
			// use the iconv library to transliterate
			return iconv($this->_fromEncoding, $this->_toEncoding . '//TRANSLIT', $string);

		} elseif ($this->_translit && $this->_fromEncoding == "UTF-8" && $this->_toEncoding == "ASCII") {
			// use the utf2ascii library
			require_once './lib/pkp/lib/phputf8/utf8_to_ascii.php';
			return utf8_to_ascii($string);

		} elseif ($mbstring) {
			// use the mbstring library to transcode
			return mb_convert_encoding($string, $this->_toEncoding, $this->_fromEncoding);

		} elseif ($iconv) {
			// use the iconv library to transcode
			return iconv($this->_fromEncoding, $this->_toEncoding . '//IGNORE', $string);

		} else {
			// fail gracefully by returning the original string unchanged
			return $string;
		}
	}
}
