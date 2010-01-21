<?php

/**
 * @file classes/citation/CitationService.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationService
 * @ingroup citation
 * @see CitationLookupService
 * @see CitationParserService
 *
 * @brief Abstract base class for all citation services (i.e. lookup and parser).
 */

// $Id$

define('CITATION_SERVICE_WEBSERVICE_RETRIES', 3);
define('CITATION_SERVICE_WEBSERVICE_MICROSECONDS_BEFORE_RETRY', 100000);

class CitationService {
	/**
	 * Return a mapping of text genres as returned from
	 * XSL transformations to our own genre constants.
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported meta-data genres
	 */
	function _getGenreTranslationMapping() {
		static $genreTranslationMapping = array(
			'book' => METADATA_GENRE_BOOK,
			'article' => METADATA_GENRE_JOURNALARTICLE,
			'proceeding' => METADATA_GENRE_CONFERENCEPROCEEDING,
			'dissertation' => METADATA_GENRE_DISSERTATION
		);

		return $genreTranslationMapping;
	}

	//
	// Protected methods for use by sub-classes
	//
	/**
	 * Call a web services
	 * @param unknown_type $url
	 * @param unknown_type $postOptions
	 * @return string the web service result or null on failure
	 */
	function callWebService($url, $postOptions = array()) {

		// If we have POST options, use CURL
		if (!empty($postOptions)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/xml, */*'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postOptions);

			// Relax timeout a little bit for slow servers
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

			// POST to the web service
			for ($retries = 0; $retries < CITATION_SERVICE_WEBSERVICE_RETRIES; $retries++) {
				if ($result = @curl_exec($ch)) break;

				// Wait for a short interval before trying again
				usleep(CITATION_SERVICE_WEBSERVICE_MICROSECONDS_BEFORE_RETRY);
			}

			curl_close($ch);
		} else {
			$oldSocketTimeout = ini_set('default_socket_timeout', 120);

			// GET from the web service
			for ($retries = 0; $retries < CITATION_SERVICE_WEBSERVICE_RETRIES; $retries++) {
				if ($result = @file_get_contents($url)) break;

				// Wait for a short interval before trying again
				usleep(CITATION_SERVICE_WEBSERVICE_MICROSECONDS_BEFORE_RETRY);
			}

			if ($oldSocketTimeout !== false) ini_set('default_socket_timeout', $oldSocketTimeout);
		}

		// Catch web service errors
		if (!$result) return null;

		// Clean the result
		$result = stripslashes($result);
		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && !String::utf8_compliant($result) ) {
			$result = String::utf8_normalize($result);
		}

		return $result;
	}

	/**
	 * Takes the raw xml result of a web service and
	 * transforms it using an XSL file. Then it converts
	 * the result to an array.
	 * @param $xmlResult string
	 * @param $xslFileName string
	 * @return array a metadata array
	 */
	function &transformWebServiceResults($xmlResult, $xslFileName) {
		// FIXME: DOM is PHP5 only, use DOM XML for PHP4.
		//        Can only do/test this when we have a GUI.
		//        PHPUnit is PHP5 only.

		// Create a temporary DOM document to hold the XML response
		$temporaryDOM = new DOMDocument();

		// Try to handle non-well-formed responses
		$temporaryDOM->recover = true;
		$temporaryDOM->loadXML($xmlResult);

		// Create a new XSLT processor
		$xslDOM = new DOMDocument();
		$xsltProcessor = new XsltProcessor();

		// Transform the XML into a flat array
		$xsl = dirname(__FILE__).DIRECTORY_SEPARATOR.$xslFileName;
		$xslDOM->load($xsl);
		$xsltProcessor->importStylesheet($xslDOM);
		$outDOM = $xsltProcessor->transformToDoc($temporaryDOM);

		$metadata = $this->xmlToArray($outDOM->documentElement);

		// Translate the genre
		if (isset($metadata['genre'])) {
			$genreTranslationMapping = CitationService::_getGenreTranslationMapping();
			assert(isset($genreTranslationMapping[$metadata['genre']]));
			$metadata['genre'] = $genreTranslationMapping[$metadata['genre']];
		}

		// Parse author strings
		if (isset($metadata['author'])) {
			// Get the author strings from the result
			$authorStrings = $metadata['author'];
			unset($metadata['author']);

			// If we only have one author then we'll have to
			// convert the author strings to an array first.
			if (!is_array($authorStrings)) $authorStrings = array($authorStrings);

			$authors = array();
			foreach ($authorStrings as $authorString) {
				$authors[] =& $this->parseAuthorString($authorString);
			}
			$metadata['authors'] = $authors;
		}

		// Transform comments
		if (isset($metadata['comment'])) {
			// Get comments from the result
			$comments = $metadata['comment'];
			unset($metadata['comment']);

			// If we only have one comment then we'll have to
			// convert the it to an array.
			if (!is_array($comments)) $comments = array($comments);

			$metadata['comments'] = $comments;
		}

		// Parse date string
		if (isset($metadata['issuedDate']))
				$metadata['issuedDate'] = $this->normalizeDateString($metadata['issuedDate']);

		return $metadata;
	}

	/**
	 * Take an XML node and generate a nested array.
	 * @param $xmlNode
	 * @param $keepEmpty whether to keep empty elements, default: false
	 * @return multitype:
	 */
	function &xmlToArray(&$xmlNode, $keepEmpty = false) {
		// Loop through all child nodes of the xml node.
		$resultArray = array();
		foreach ($xmlNode->childNodes as $childNode) {
			if ($childNode->nodeType == 1) {
				if ($childNode->childNodes->length > 1) {
					// Recurse
					$resultArray[$childNode->nodeName] = $this->xmlToArray($childNode);
				} elseif ( ($childNode->nodeValue == '' && $keepEmpty) || ($childNode->nodeValue != '') ) {
					if (isset($resultArray[$childNode->nodeName])) {
						if (!is_array($resultArray[$childNode->nodeName])) {
							// We got a second value with the same key,
							// let's convert this element into an array.
							$resultArray[$childNode->nodeName] = array($resultArray[$childNode->nodeName]);
						}

						// Add the child node to the result array
						$resultArray[$childNode->nodeName][] = $childNode->nodeValue;
					} else {
						// This key occurs for the first time so
						// set it as a scalar value.
						$resultArray[$childNode->nodeName] = $childNode->nodeValue;
					}
				}
			}
		}

		return $resultArray;
	}

	/**
	 * Convert a string to proper title case
	 * @param $title string
	 * @return string
	 */
	function titleCase($title) {
		$smallWords = array(
			'of', 'a', 'the', 'and', 'an', 'or', 'nor', 'but', 'is', 'if', 'then',
			'else', 'when', 'at', 'from', 'by', 'on', 'off', 'for', 'in', 'out',
			'over', 'to', 'into', 'with'
		);

		$words = explode(' ', $title);
		foreach ($words as $key => $word) {
			if ($key == 0 or !in_array(strtolower($word), $smallWords)) {
				$words[$key] = ucfirst(strtolower($word));
			} else {
				$words[$key] = strtolower($word);
			}
		}

		$newTitle = implode(' ', $words);
		return $newTitle;
	}

	/**
	 * Trim punctuation from a string
	 * @param $string string input string
	 * @return string the trimmed string
	 */
	function trimPunctuation($string) {
		return trim($string, ' ,.;:!?()[]\\/');
	}

	/**
	 * Converts a string with multiple authors
	 * to an array of author objects.
	 *
	 * @param $authorsString string
	 * @param $title true to parse for title
	 * @param $degrees true to parse for degrees
	 * @return array an array of Author objects or null
	 *  if the string could not be converted
	 */
	function &parseAuthorsString($authorsString, $title = false, $degrees = false) {
		// Remove "et al"
		$authorsString = String::regexp_replace('/et ?al$/', '', $authorsString);

		// Translate author separators to colon.
		if (strstr($authorsString, ':') === false) {
			// We search for author separators by priority. As soon as we find one kind of
			// separator we'll replace it and stop there.
			$separators = array(';', ',');
			foreach($separators as $separator) {
				if (strstr($authorsString, $separator) !== false) {
					$authorsString = strtr($authorsString, $separator, ':');
					break;
				}
			}
		}

		// Split author string into separate authors
		$authorStrings = explode(':', $this->trimPunctuation($authorsString));

		// Parse authors
		$authors = array();
		foreach ($authorStrings as $authorString) {
			$authors[] =& $this->parseAuthorString($authorString, $degrees, $title);
		}

		return $authors;
	}

	/**
	 * Converts a string with a single author
	 * to an author object.
	 *
	 * TODO: create an "AuthorParser" class so that we can
	 *       implement different parsers (e.g. i18nized ones)
	 *       as plugins.
	 * TODO: add initials from all given names to initials
	 *       element
	 *
	 * @param $authorString string
	 * @param $title true to parse for title
	 * @param $degrees true to parse for degrees
	 * @return Author an Author object or null
	 *  if the string could not be converted
	 */
	function &parseAuthorString($authorString, $title = false, $degrees = false) {
		// Expressions to parse author strings, ported from CiteULike author
		// plugin, see http://svn.citeulike.org/svn/plugins/author.tcl
		static $authorRegex = array(
			'title' => '(?:His (?:Excellency|Honou?r)\s+|Her (?:Excellency|Honou?r)\s+|The Right Honou?rable\s+|The Honou?rable\s+|Right Honou?rable\s+|The Rt\.? Hon\.?\s+|The Hon\.?\s+|Rt\.? Hon\.?\s+|Mr\.?\s+|Ms\.?\s+|M\/s\.?\s+|Mrs\.?\s+|Miss\.?\s+|Dr\.?\s+|Sir\s+|Dame\s+|Prof\.?\s+|Professor\s+|Doctor\s+|Mister\s+|Mme\.?\s+|Mast(?:\.|er)?\s+|Lord\s+|Lady\s+|Madam(?:e)?\s+|Priv\.-Doz\.\s+)+',
			'degrees' => '(,\s+(?:[A-Z\.]+))+',
			'initials' => '(?:(?:[A-Z]\.){1,4})|(?:(?:[A-Z]\.\s){1,3}[A-Z])|(?:[A-Z]{1,4})|(?:(?:[A-Z]\.-?){1,4})|(?:(?:[A-Z]\.-?){1,3}[A-Z])|(?:(?:[A-Z]-){1,3}[A-Z])|(?:(?:[A-Z]\s){1,3}[A-Z])|(?:(?:[A-Z] ){1,3}[A-Z]\.)|(?:[A-Z]-(?:[A-Z]\.){1,3})',
			'prefix' => 'Dell(?:[a|e])?\s|Dalle\s|D[a|e]ll\'\s|Dela\s|Del\s|[Dd]e (?:La |Los )?\s|[Dd]e\s|[Dd][a|i|u]\s|L[a|e|o]\s|[D|L|O]\'|St\.?\s|San\s|[Dd]en\s|[Vv]on\s(?:[Dd]er\s)?|(?:[Ll][ea] )?[Vv]an\s(?:[Dd]e(?:n|r)?\s)?',
			'givenName' => '(?:[^ \t\n\r\f\v,.]{2,}|[^ \t\n\r\f\v,.;]{2,}\-[^ \t\n\r\f\v,.;]{2,})'
		);
		$authorRegexLastName = "(?:".$authorRegex['prefix'].")?(?:".$authorRegex['givenName'].")";

		// Create the target author object
		import('submission.PKPAuthor');
		$author = new PKPAuthor();

		// Clean the author string
		$authorString = trim($authorString);

		// 1. Extract the salutation from the author string
		$salutationString = '';

		$results = array();
		if ($title && String::regexp_match_get('/^('.$authorRegex['title'].')/i', $authorString, $results)) {
			$salutationString = trim($results[1], ',:; ');
			$authorString = String::regexp_replace('/^('.$authorRegex['title'].')/i', '', $authorString);
		}

		if ($degrees && String::regexp_match_get('/('.$authorRegex['degrees'].')$/i', $authorString, $results)) {
			$degreesArray = explode(',', trim($results[1], ','));
			foreach($degreesArray as $key => $degree) {
				$degreesArray[$key] = $this->trimPunctuation($degree);
			}
			$salutationString .= ' - '.implode('; ', $degreesArray);
			$authorString = String::regexp_replace('/('.$authorRegex['degrees'].')$/i', '', $authorString);
		}

		if (!empty($salutationString)) $author->setSalutation($salutationString);

		// Space initials when followed by a given name or last name.
		$authorString = String::regexp_replace('/([A-Z])\.([A-Z][a-z])/', '\1. \2', $authorString);

		// 2. Extract names and initials from the author string

		// The parser expressions are ordered by specificity. The most specific expressions
		// come first. Only if these specific expressions don't work will we turn to less
		// specific ones. This avoids parsing errors. It also explains why we don't use the
		// ?-quantifier for optional elements like initials or middle name.
		$authorExpressions = array(
			'/^(?P<lastName>'.$authorRegexLastName.')$/i',
			'/^(?P<initials>'.$authorRegex['initials'].')\s(?P<lastName>'.$authorRegexLastName.')$/',
			'/^(?P<lastName>'.$authorRegexLastName.'),?\s(?P<initials>'.$authorRegex['initials'].')$/',
			'/^(?P<lastName>'.$authorRegexLastName.'),\s(?P<givenName>'.$authorRegex['givenName'].')\s(?P<initials>'.$authorRegex['initials'].')$/',
			'/^(?P<givenName>'.$authorRegexLastName.')\s(?P<lastName>'.$authorRegex['givenName'].')\s(?P<initials>'.$authorRegex['initials'].')$/',
			'/^(?P<lastName>'.$authorRegexLastName.'),\s(?P<givenName>'.$authorRegex['givenName'].')\s(?P<initials>'.$authorRegex['initials'].')$/',
			'/^(?P<givenName>'.$authorRegex['givenName'].')\s(?P<initials>'.$authorRegex['initials'].')\s(?P<lastName>'.$authorRegexLastName.')$/',
			'/^(?P<lastName>'.$authorRegexLastName.'),\s(?P<givenName>(?:'.$authorRegex['givenName'].'\s)+)(?P<initials>'.$authorRegex['initials'].')$/',
			'/^(?P<givenName>(?:'.$authorRegex['givenName'].'\s)+)(?P<initials>'.$authorRegex['initials'].')\s(?P<lastName>'.$authorRegexLastName.')$/',
			'/^(?P<lastName>'.$authorRegexLastName.'),(?P<givenName>(?:\s'.$authorRegex['givenName'].')+)$/',
			'/^(?P<givenName>(?:'.$authorRegex['givenName'].'\s)+)(?P<lastName>'.$authorRegexLastName.')$/',
			'/^(?P<lastName>.*)$/' // catch-all expression
		);

		$results = array();
		foreach ($authorExpressions as $expressionId => $authorExpression) {
			if ($nameFound = String::regexp_match_get($authorExpression, $authorString, $results)) {
				// Fix all-upper last name
				if (strtoupper($results['lastName']) == $results['lastName']) {
					$results['lastName'] = ucwords(strtolower($results['lastName']));
				}

				// Transfer data to the author object
				if (isset($results['givenName'])) {
					// Split given names into firstname and middlename(s)
					$givenNames = explode(' ', trim($results['givenName']));
					$author->setFirstName(array_shift($givenNames));
					if (count($givenNames)) {
						$author->setMiddleName(implode(' ', $givenNames));
					}
				}
				if (isset($results['initials'])) $author->setInitials($results['initials']);
				if (isset($results['lastName'])) $author->setLastName($results['lastName']);

				break;
			}
		}

		return $author;
	}

	/**
	 * Normalizes a date string to canonical date
	 * representation (i.e. YYYY-MM-DD)
	 * @param $dateString string
	 * @return string the normalized date string or null
	 *  if the string could not be normalized
	 */
	function normalizeDateString($dateString) {
		// TODO: We have to i18nize this when expanding citation parsing to other languages
		static $monthNames = array(
			'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
			'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
		);

		$normalizedDate = null;
		if (String::regexp_match_get("/(?P<year>\d{4})\s*(?P<month>[a-z]\w+)?\s*(?P<day>\d+)?/i", $dateString, $parsedDate) ){
			if (isset($parsedDate['year'])) {
				$normalizedDate = $parsedDate['year'];

				if (isset($parsedDate['month'])
						&& isset($monthNames[substr($parsedDate['month'], 0, 3)])) {
					// Convert the month name to a two digit numeric month representation
					// before adding it to the normalized date string.
					$normalizedDate .= '-'.$monthNames[substr($parsedDate['month'], 0, 3)];

					if (isset($parsedDate['day'])) $normalizedDate .= '-'.str_pad($parsedDate['day'], 2, '0', STR_PAD_LEFT);
				}
			}
		}

		return $normalizedDate;
	}

	/**
	 * Take a meta-data array and fix place/publisher entries:
	 * - If there is a place string in there but no publisher string
	 *   then try to extract a publisher from the place string.
	 * - Make sure that place and publisher are not the same.
	 * - Convert institution to publisher if no publisher is set,
	 *   otherwise add the institution as comment.
	 * @param $metadata array
	 */
	function fixPlaceAndPublisher(&$metadata) {
		if (isset($metadata['place'])) {
			// Extract publisher from place if we don't have a publisher
			// in the parsing result.
			if (empty($metadata['publisher'])) {
				$metadata['publisher'] = String::regexp_replace('/.*:([^,]+),?.*/', '\1', $metadata['place']);
			}

			// Remove publisher from place
			$metadata['place'] = String::regexp_replace('/^(.+):.*/', '\1', $metadata['place']);

			// Check that publisher and location are not the same
			// TODO: not well-tested
			if (!empty($metadata['publisher']) && $metadata['publisher'] == $metadata['place']) unset($metadata['publisher']);
		}

		// Convert the institution element to our internal meta-data format
		if (isset($metadata['institution'])) {
			if (empty($metadata['publisher'])) {
				$metadata['publisher'] = $metadata['institution'];
			} else {
				if (!isset($metadata['comments'])) $metadata['comments'] = array();
				$metadata['comments'][] = 'Institution: '.$metadata['institution'];
			}
			unset($metadata['institution']);
		}
	}
}
?>