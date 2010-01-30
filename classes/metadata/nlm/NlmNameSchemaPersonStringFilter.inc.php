<?php

/**
 * @file classes/metadata/NlmNameSchemaPersonStringFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmNameSchemaPersonStringFilter
 * @ingroup metadata_nlm
 * @see NlmNameSchema
 *
 * @brief Filter that converts from NLM name to
 *  a string.
 */

// $Id$

import('filter.Filter');

class NlmNameSchemaPersonStringFilter extends Filter {

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		if (!is_a($input, 'MetadataDescription')) return false;
		$metadataSchema =& $input->getMetadataSchema();
		if ($metadataSchema->getName() != 'nlm-3.0-name') return false;
		return true;
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		return is_string($output);
	}

	/**
	 * Transform an NLM name description to a person string.
	 * NB: We use the style: surname suffix, initials (first-name) prefix
	 * which is relatively easy to parse back.
	 * @see Filter::process()
	 * @param $input MetadataDescription
	 * @return string
	 */
	function &process(&$input) {
		$surname = (string)$input->getStatement('surname');

		$givenNames = $input->getStatement('given-names');
		$firstName = $initials = '';
		if(is_array($givenNames) && count($givenNames)) {
			$firstName = array_shift($givenNames);
			foreach($givenNames as $givenName) {
				$initials .= substr($givenNames, 0, 1).'.';
			}
		}
		if (!empty($initials)) $initials = ' '.$initials;
		if (!empty($firstName)) $firstName = ' ('.$firstName.')';

		$prefix = (string)$input->getStatement('prefix');
		if (!empty($prefix)) $prefix = ' '.$prefix;
		$suffix = (string)$input->getStatement('suffix');
		if (!empty($suffix)) $suffix = ' '.$suffix;

		$personString = $surname.$suffix.','.$initials.$firstName.$prefix;
		return $personString;
	}
}
?>