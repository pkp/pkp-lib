<?php

/**
 * @file classes/metadata/PersonStringNlmNameSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PersonStringNlmNameSchemaFilter
 * @ingroup metadata_nlm
 * @see NlmNameSchema
 *
 * @brief Filter that converts from a string
 *  to an (array of) NLM name description(s).
 */

// $Id$

import('filter.Filter');
import('metadata.MetadataDescription');
import('metadata.nlm.NlmNameSchema');

define('PERSON_STRING_FILTER_EXPECT_MULTIPLE', 0x01);
define('PERSON_STRING_FILTER_EXPECT_SINGLE', 0x02);

class PersonStringNlmNameSchemaFilter extends Filter {
	/** @var integer */
	var $_assocType;

	/** @var integer */
	var $_filterMode;

	/** @var boolean */
	var $_filterTitle;

	/** @var boolean */
	var $_filterDegrees;

	/**
	 * Constructor
	 */
	function PersonStringNlmNameSchemaFilter($assocType, $filterMode = PERSON_STRING_FILTER_EXPECT_SINGLE, $filterTitle = false, $filterDegrees = false) {
		assert(in_array($assocType, array(ASSOC_TYPE_AUTHOR, ASSOC_TYPE_EDITOR)));
		$this->_assocType = $assocType;
		$this->_filterMode = $filterMode;
		$this->_filterTitle = $filterTitle;
		$this->_filterDegrees = $filterDegrees;
	}

	//
	// Setters and Getters
	//
	/**
	 * Get the association type
	 * @return integer
	 */
	function &getAssocType() {
		return $this->_assocType;
	}

	/**
	 * Get the filter mode
	 * @return integer
	 */
	function getFilterMode() {
		return $this->_filterMode;
	}

	/**
	 * Set the filter mode
	 * @param $filterMode integer
	 */
	function setFilterMode($filterMode) {
		$this->_filterMode = $filterMode;
	}

	/**
	 * Do we parse for a title?
	 * @return boolean
	 */
	function getFilterTitle() {
		return $this->_filterTitle;
	}

	/**
	 * Set whether we parse for a title
	 * @param $filterTitle boolean
	 */
	function setFilterTitle($filterTitle) {
		$this->_filterTitle = (boolean)$filterTitle;
	}

	/**
	 * Do we parse for degrees?
	 * @return boolean
	 */
	function getFilterDegrees() {
		return $this->_filterDegrees;
	}

	/**
	 * Set whether we parse for degrees
	 * @param $filterDegrees boolean
	 */
	function setFilterDegrees($filterDegrees) {
		$this->_filterDegrees = (boolean)$filterDegrees;
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		return is_string($input);
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		// Check the filter mode
		if (is_array($output)) {
			if (!$this->_filterMode == PERSON_STRING_FILTER_EXPECT_MULTIPLE) return false;
			$validationArray = &$output;
		} else {
			if (!$this->_filterMode == PERSON_STRING_FILTER_EXPECT_SINGLE) return false;
			$validationArray = array(&$output);
		}

		// Validate all descriptions
		foreach($validationArray as $nameDescription) {
			if (!is_a($nameDescription, 'MetadataDescription')) return false;
			$metadataSchema =& $nameDescription->getMetadataSchema();
			if ($metadataSchema->getName() != 'nlm-3.0-name') return false;
			unset($metadataSchema);
		}

		return true;
	}

	/**
	 * Transform a person string to an (array of) NLM name description(s).
	 * @see Filter::process()
	 * @param $input string
	 * @return mixed Either a MetadataDescription or an array of MetadataDescriptions
	 */
	function &process(&$input) {
		switch ($this->_filterMode) {
			case PERSON_STRING_FILTER_EXPECT_MULTIPLE:
				return $this->_parsePersonsString($input, $this->_filterTitle, $this->_filterDegrees);

			case PERSON_STRING_FILTER_EXPECT_SINGLE:
				return $this->_parsePersonString($input, $this->_filterTitle, $this->_filterDegrees);

			default:
				assert(false);
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Converts a string with multiple persons
	 * to an array of NLM name descriptions.
	 *
	 * @param $personsString string
	 * @param $title boolean true to parse for title
	 * @param $degrees boolean true to parse for degrees
	 * @return array an array of NLM name descriptions or null
	 *  if the string could not be converted
	 */
	function &_parsePersonsString($personsString, $title, $degrees) {
		// Remove "et al"
		$personsString = String::regexp_replace('/et ?al$/', '', $personsString);

		// Translate person separators to colon.
		if (strstr($personsString, ':') === false) {
			// We search for person separators by priority. As soon as we find one kind of
			// separator we'll replace it and stop there.
			$separators = array(';', ',');
			foreach($separators as $separator) {
				if (strstr($personsString, $separator) !== false) {
					$personsString = strtr($personsString, $separator, ':');
					break;
				}
			}
		}

		// Split person string into separate persons
		$personStrings = explode(':', String::trimPunctuation($personsString));

		// Parse persons
		$persons = array();
		foreach ($personStrings as $personString) {
			$persons[] =& $this->_parsePersonString($personString, $degrees, $title);
		}

		return $persons;
	}

	/**
	 * Converts a string with a single person
	 * to an NLM name description.
	 *
	 * TODO: add initials from all given names to initials
	 *       element
	 *
	 * @param $personString string
	 * @param $title boolean true to parse for title
	 * @param $degrees boolean true to parse for degrees
	 * @return MetadataDescription an NLM name description or null
	 *  if the string could not be converted
	 */
	function &_parsePersonString($personString, $title, $degrees) {
		// Expressions to parse person strings, ported from CiteULike person
		// plugin, see http://svn.citeulike.org/svn/plugins/person.tcl
		static $personRegex = array(
			'title' => '(?:His (?:Excellency|Honou?r)\s+|Her (?:Excellency|Honou?r)\s+|The Right Honou?rable\s+|The Honou?rable\s+|Right Honou?rable\s+|The Rt\.? Hon\.?\s+|The Hon\.?\s+|Rt\.? Hon\.?\s+|Mr\.?\s+|Ms\.?\s+|M\/s\.?\s+|Mrs\.?\s+|Miss\.?\s+|Dr\.?\s+|Sir\s+|Dame\s+|Prof\.?\s+|Professor\s+|Doctor\s+|Mister\s+|Mme\.?\s+|Mast(?:\.|er)?\s+|Lord\s+|Lady\s+|Madam(?:e)?\s+|Priv\.-Doz\.\s+)+',
			'degrees' => '(,\s+(?:[A-Z\.]+))+',
			'initials' => '(?:(?:[A-Z]\.){1,4})|(?:(?:[A-Z]\.\s){1,3}[A-Z])|(?:[A-Z]{1,4})|(?:(?:[A-Z]\.-?){1,4})|(?:(?:[A-Z]\.-?){1,3}[A-Z])|(?:(?:[A-Z]-){1,3}[A-Z])|(?:(?:[A-Z]\s){1,3}[A-Z])|(?:(?:[A-Z] ){1,3}[A-Z]\.)|(?:[A-Z]-(?:[A-Z]\.){1,3})',
			'prefix' => 'Dell(?:[a|e])?\s|Dalle\s|D[a|e]ll\'\s|Dela\s|Del\s|[Dd]e (?:La |Los )?\s|[Dd]e\s|[Dd][a|i|u]\s|L[a|e|o]\s|[D|L|O]\'|St\.?\s|San\s|[Dd]en\s|[Vv]on\s(?:[Dd]er\s)?|(?:[Ll][ea] )?[Vv]an\s(?:[Dd]e(?:n|r)?\s)?',
			'givenName' => '(?:[^ \t\n\r\f\v,.]{2,}|[^ \t\n\r\f\v,.;]{2,}\-[^ \t\n\r\f\v,.;]{2,})'
		);
		$personRegexSurname = "(?P<prefix>(?:".$personRegex['prefix'].")?)(?P<surname>".$personRegex['givenName'].")";

		// Instantiate the target person description
		$metadataSchema = new NlmNameSchema();
		$personDescription = new MetadataDescription($metadataSchema, $this->_assocType);

		// Clean the person string
		$personString = trim($personString);

		// 1. Extract title and degree from the person string and use this as suffix
		$suffixString = '';

		$results = array();
		if ($title && String::regexp_match_get('/^('.$personRegex['title'].')/i', $personString, $results)) {
			$suffixString = trim($results[1], ',:; ');
			$personString = String::regexp_replace('/^('.$personRegex['title'].')/i', '', $personString);
		}

		if ($degrees && String::regexp_match_get('/('.$personRegex['degrees'].')$/i', $personString, $results)) {
			$degreesArray = explode(',', trim($results[1], ','));
			foreach($degreesArray as $key => $degree) {
				$degreesArray[$key] = String::trimPunctuation($degree);
			}
			$suffixString .= ' - '.implode('; ', $degreesArray);
			$personString = String::regexp_replace('/('.$personRegex['degrees'].')$/i', '', $personString);
		}

		if (!empty($suffixString)) $personDescription->addStatement('suffix', $suffixString);

		// Space initials when followed by a given name or last name.
		$personString = String::regexp_replace('/([A-Z])\.([A-Z][a-z])/', '\1. \2', $personString);

		// 2. Extract names and initials from the person string

		// The parser expressions are ordered by specificity. The most specific expressions
		// come first. Only if these specific expressions don't work will we turn to less
		// specific ones. This avoids parsing errors. It also explains why we don't use the
		// ?-quantifier for optional elements like initials or middle name.
		$personExpressions = array(
			'/^'.$personRegexSurname.'$/i',
			'/^(?P<initials>'.$personRegex['initials'].')\s'.$personRegexSurname.'$/',
			'/^'.$personRegexSurname.',?\s(?P<initials>'.$personRegex['initials'].')$/',
			'/^'.$personRegexSurname.',\s(?P<givenName>'.$personRegex['givenName'].')\s(?P<initials>'.$personRegex['initials'].')$/',
			'/^(?P<givenName>'.$personRegex['givenName'].')\s(?P<initials>'.$personRegex['initials'].')\s'.$personRegexSurname.'$/',
			'/^'.$personRegexSurname.',\s(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)(?P<initials>'.$personRegex['initials'].')$/',
			'/^(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)(?P<initials>'.$personRegex['initials'].')\s'.$personRegexSurname.'$/',
			'/^'.$personRegexSurname.',(?P<givenName>(?:\s'.$personRegex['givenName'].')+)$/',
			'/^(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)'.$personRegexSurname.'$/',
			'/^(?P<surname>.*)$/' // catch-all expression
		);

		$results = array();
		foreach ($personExpressions as $expressionId => $personExpression) {
			if ($nameFound = String::regexp_match_get($personExpression, $personString, $results)) {
				// Given names
				if (!empty($results['givenName'])) {
					// Split given names
					$givenNames = explode(' ', trim($results['givenName']));
					foreach($givenNames as $givenName) {
						$personDescription->addStatement('given-names', $givenName);
						unset($givenName);
					}
				}

				// Initials (will also be saved as given names)
				if (!empty($results['initials'])) {
					$results['initials'] = str_replace(array('.', '-', ' '), array('', '', ''), $results['initials']);
					for ($initialNum = 0; $initialNum < String::strlen($results['initials']); $initialNum++) {
						$initial = $results['initials'][$initialNum];
						$personDescription->addStatement('given-names', $initial);
						unset($initial);
					}
				}

				// Surname
				if (!empty($results['surname'])) {
					if (strtoupper($results['surname']) == $results['surname']) {
						$results['surname'] = ucwords(strtolower($results['surname']));
					}

					$personDescription->addStatement('surname', $results['surname']);
				}

				// Prefix
				if (!empty($results['prefix'])) {
					$results['prefix'] = trim($results['prefix']);
					$personDescription->addStatement('prefix', $results['prefix']);
				}

				break;
			}
		}

		return $personDescription;
	}
}
?>