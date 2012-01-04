<?php

/**
 * @file classes/metadata/PersonStringNlmNameSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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

import('metadata.nlm.NlmPersonStringFilter');

class PersonStringNlmNameSchemaFilter extends NlmPersonStringFilter {
	/** @var integer */
	var $_assocType;

	/** @var boolean */
	var $_filterTitle;

	/** @var boolean */
	var $_filterDegrees;

	/**
	 * Constructor
	 */
	function PersonStringNlmNameSchemaFilter($assocType, $filterMode = PERSON_STRING_FILTER_SINGLE, $filterTitle = false, $filterDegrees = false) {
		assert(in_array($assocType, array(ASSOC_TYPE_AUTHOR, ASSOC_TYPE_EDITOR)));
		$this->_assocType = $assocType;
		$this->_filterTitle = $filterTitle;
		$this->_filterDegrees = $filterDegrees;
		parent::NlmPersonStringFilter($filterMode);
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
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Check input type
		if (!is_string($input)) return false;

		// Check output type
		if (is_null($output)) return true;
		return $this->isValidPersonDescription($output);
	}


	/**
	 * Transform a person string to an (array of) NLM name description(s).
	 * @see Filter::process()
	 * @param $input string
	 * @return mixed Either a MetadataDescription or an array of MetadataDescriptions
	 */
	function &process(&$input) {
		switch ($this->getFilterMode()) {
			case PERSON_STRING_FILTER_MULTIPLE:
				return $this->_parsePersonsString($input, $this->_filterTitle, $this->_filterDegrees);

			case PERSON_STRING_FILTER_SINGLE:
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

		// Remove punctuation
		$personsString = trim($personsString, ':;,');

		// Cut the authors string into pieces
		$personStrings = String::iterativeExplode(array(':', ';'), $personsString);

		// Only try to cut by comma if the pieces contain more
		// than one word to avoid splitting between last name and
		// first name.
		if (count($personStrings) == 1) {
			if (String::regexp_match('/^((\w+\s+)+\w+\s*,)+\s*((\w+\s+)+\w+)$/i', $personStrings[0])) {
				$personStrings = explode(',', $personStrings[0]);
			}
		}

		// Parse persons
		$persons = array();
		foreach ($personStrings as $personString) {
			$persons[] =& $this->_parsePersonString($personString, $title, $degrees);
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
			'prefix' => 'Dell(?:[a|e])?(?:\s|$)|Dalle(?:\s|$)|D[a|e]ll\'(?:\s|$)|Dela(?:\s|$)|Del(?:\s|$)|[Dd]e(?:\s|$)(?:La(?:\s|$)|Los(?:\s|$))?|[Dd]e(?:\s|$)|[Dd][a|i|u](?:\s|$)|L[a|e|o](?:\s|$)|[D|L|O]\'|St\.?(?:\s|$)|San(?:\s|$)|[Dd]en(?:\s|$)|[Vv]on(?:\s|$)(?:[Dd]er(?:\s|$))?|(?:[Ll][ea](?:\s|$))?[Vv]an(?:\s|$)(?:[Dd]e(?:n|r)?(?:\s|$))?',
			'givenName' => '(?:[^ \t\n\r\f\v,.;()]{2,}|[^ \t\n\r\f\v,.;()]{2,}\-[^ \t\n\r\f\v,.;()]{2,})'
		);
		// The expressions for given name, suffix and surname are the same
		$personRegex['surname'] = $personRegex['suffix'] = $personRegex['givenName'];

		// Shortcut for prefixed surname
		$personRegexPrefixedSurname = "(?P<prefix>(?:".$personRegex['prefix'].")?)(?P<surname>".$personRegex['surname'].")";

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
		// ?-quantifier for optional elements like initials or middle name where they could
		// be misinterpreted.
		$personExpressions = array(
			// All upper surname
			'/^'.$personRegexPrefixedSurname.'$/i',

			// Several permutations of name elements, ordered by specificity
			'/^(?P<initials>'.$personRegex['initials'].')\s'.$personRegexPrefixedSurname.'$/',
			'/^'.$personRegexPrefixedSurname.',?\s(?P<initials>'.$personRegex['initials'].')$/',
			'/^'.$personRegexPrefixedSurname.',\s(?P<givenName>'.$personRegex['givenName'].')\s(?P<initials>'.$personRegex['initials'].')$/',
			'/^(?P<givenName>'.$personRegex['givenName'].')\s(?P<initials>'.$personRegex['initials'].')\s'.$personRegexPrefixedSurname.'$/',
			'/^'.$personRegexPrefixedSurname.',\s(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)(?P<initials>'.$personRegex['initials'].')$/',
			'/^(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)(?P<initials>'.$personRegex['initials'].')\s'.$personRegexPrefixedSurname.'$/',
			'/^'.$personRegexPrefixedSurname.',(?P<givenName>(?:\s'.$personRegex['givenName'].')+)$/',
			'/^(?P<givenName>(?:'.$personRegex['givenName'].'\s)+)'.$personRegexPrefixedSurname.'$/',

			// DRIVER guidelines 2.0 name syntax
			'/^\s*(?P<surname>'.$personRegex['surname'].')(?P<suffix>(?:\s+'.$personRegex['suffix'].')?)\s*,\s*(?P<initials>(?:'.$personRegex['initials'].')?)\s*\((?P<givenName>(?:\s*'.$personRegex['givenName'].')+)\s*\)\s*(?P<prefix>(?:'.$personRegex['prefix'].')?)$/',

			// Catch-all expression
			'/^(?P<surname>.*)$/'
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
					// Correct all-upper surname
					if (strtoupper($results['surname']) == $results['surname']) {
						$results['surname'] = ucwords(strtolower($results['surname']));
					}

					$personDescription->addStatement('surname', $results['surname']);
				}

				// Prefix/Suffix
				foreach(array('prefix', 'suffix') as $propertyName) {
					if (!empty($results[$propertyName])) {
						$results[$propertyName] = trim($results[$propertyName]);
						$personDescription->addStatement($propertyName, $results[$propertyName]);
					}
				}

				break;
			}
		}

		return $personDescription;
	}
}
?>