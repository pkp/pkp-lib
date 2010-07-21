<?php

/**
 * @file classes/citation/CitationManager.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationManager
 * @ingroup citation
 *
 * @brief Class providing citation lookup and parsing services.
 * 
 * TODO: Parsing and lookup usually involve a web service request or launching
 *       an external tool. This can be very slow. Therefore it might be useful
 *       to cache all parsing and lookup results to the user session or even to
 *       file/db. This way we can make sure that we avoid unnecessary (costly)
 *       parsing/lookup service call when the user is re-parsing the same set
 *       of citations over and over (which is a realistic scenario). Alternatively
 *       we can implement a (persistent) dirty-pattern in the citation.
 */

// $Id$


class CitationManager {
	/** @var list of configured citation parser services identified by name */
	var $_citationParserServices = array();
		
	/** @var list of configured citation lookup services identified by Name */
	var $_citationLookupServices = array();
	
		
	/**
	 * Take in a Citation in state CITATION_RAW or CITATION_EDITED, pass through parser
	 * services, and return the same object in state CITATION_PARSED.
	 * @param $citation Citation the citation object to be parsed.
	 * @return Citation the parsed citation object
	 */
	function &parse(&$citation) {
		assert(!empty($this->_citationParserServices));

		// Initialize the array that will contain parsed citations by score.
		// This is a two-dimensional array that with the score as key and
		// the scored citations as values.
		$scoredCitations = array();
		
		// Let each configured parser service generate citation meta-data according
		// to its specific implementation.
		foreach ($this->_citationParserServices as $citationParserService) {
			// Make a copy of the citation so that the parsers don't interfere
			// with each other.
			$parsedCitation =& $this->_cloneObject($citation);
			
			// Get the parsed citation from the the current parser service.
			$parsedCitation =& $citationParserService->parse($parsedCitation);

			// TODO: Ignoring the parser service isn't the best option
			//       when we get an error (e.g. web-service call, invalid meta-data).
			//       We should try to get hold of the offending citation string
			//       and parser service so that we can fix the service.
			// Ignore the parser if it caused an error
			if (is_null($parsedCitation)) continue;
			
			// If the genre is not set, take a guess
			if ($parsedCitation->getGenre() == METADATA_GENRE_UNKNOWN) {
				$genre = $this->_guessGenre($parsedCitation);
				if ($genre != METADATA_GENRE_UNKNOWN) {
					$parsedCitation->setGenre($genre);
				}
			}

			// Calculate the score for this parsed citation
			$parseScore = $this->_parseScore($parsedCitation);
			$parsedCitation->setParseScore($parseScore);

			// Save the parsed citation hashed by its parse score.
			// We save them as a sub-array in case several citations
			// receive the same parse score.
			if (!isset($scoredCitations[$parseScore])) {
				$scoredCitations[$parseScore] = array();
			}
			$scoredCitations[$parseScore][] =& $parsedCitation;
		}

		// Get a single set of "best" values for the citation
		// and set them in the citation.
		$this->_guessValues($citation, $scoredCitations);
		$citation->setState(CITATION_PARSED);
		
		return $citation;
	}
	
	/**
	 * Take in a parsed citation, pass through configured lookup
	 * services, and return a revised Citation in state CITATION_LOOKED_UP.
	 * @param $citation Citation
	 * @return Citation the revised citation object
	 */
	function &lookup(&$citation) {
		assert(!empty($this->_citationLookupServices));

		// Initialize the array that will contain looked up citations by score.
		// This is a two-dimensional array that with the score as key and
		// the scored citations as values.
		$scoredCitations = array();
		
		// Let each configured parser service generate citation meta-data according
		// to its specific implementation.
		$originalElements = $mergedElements = $citation->getNonEmptyElementsAsArray();
		foreach ($this->_citationLookupServices as $citationLookupService) {

			// Only try the lookup if the given citation is supported
			if ($citationLookupService->supports($citation)) {
				// Make a copy of the citation so that the lookup services don't interfere
				// with each other.
				$lookedUpCitation =& $this->_cloneObject($citation);
				
				// Get an array of looked up citations from the the current lookup service.
				$lookedUpCitation =& $citationLookupService->lookup($lookedUpCitation);
				$lookedUpElements = $lookedUpCitation->getNonEmptyElementsAsArray();

				if (!empty($lookedUpElements)) {
					$mergedElements = array_merge($mergedElements, $lookedUpElements);

					// Calculate the score for this lookup
					// NB: This averages a comparison score of the current metadata with
					// a comparison score of merged results to even results.
					// TODO: This algorithm seems quite arbitrary.
					$lookupScore = ($this->lookupScore($originalElements, $lookedUpElements)
							+ $this->_lookupScore($originalElements, $mergedElements)) / 2;
					$lookedUpCitation->setLookupScore($lookupScore);

					// Save the parsed citation hashed by its parse score.
					// We save them as a sub-array in case several citations
					// receive the same parse score.
					if (!isset($scoredCitations[$lookupScore])) {
						$scoredCitations[$lookupScore] = array();
					}
					$scoredCitations[$lookupScore][] =& $lookedUpCitation;
				}
			}
		}

		// Get a single set of "best" values for the citation
		// and set them in the citation.
		$this->_guessValues($citation, $scoredCitations);
		$citation->setState(CITATION_LOOKED_UP);
		
		return $citation;
	}

	//
	// Get/set methods
	//
	
	/**
	 * add citation parser service
	 * @param $citationParserService CitationParserService
	 */
	function addCitationParserService(&$citationParserService) {
		assert(is_a($citationParserService, 'CitationParserService'));
		$citationParserServiceName = (string)$citationParserService->getName();
		assert($citationParserServiceName > 0 && !isset($this->_citationParserServices[$citationParserServiceName]));
		$this->_citationParserServices[$citationParserServiceName] =& $citationParserService;
		return true;
	}
	
	/**
	 * remove citation parser service
	 * @param $citationParserServiceName Name of the citation parser service to remove
	 * @return boolean true if the citation parser service was found and removed, otherwise false
	 */
	function removeCitationParserService($citationParserServiceName) {
		// Remove the citation parser service if it is in the list
		if (isset($citationParserServiceName) && isset($this->_citationParserServices[$citationParserServiceName])) {
			assert($this->_citationParserServices[$citationParserServiceName]->getName() == $citationParserServiceName);
			unset($this->_citationParserServices[$citationParserServiceName]);
			return true;
		}
	
		return false;
	}
	
	/**
	 * get all citation parser services
	 * @return array citation parser services
	 */
	function &getCitationParserServices() {
		return $this->_citationParserServices;
	}
	
	/**
	 * get a specific citation parser service
	 * @param $citationParserServiceName string
	 * @return array citation parser services
	 */
	function &getCitationParserService($citationParserServiceName) {
		$citationParserService = null;
	
		if (isset($citationParserServiceName) && isset($this->_citationParserServices[$citationParserServiceName])) {
			assert($this->_citationParserServices[$citationParserServiceName]->getName() == $citationParserServiceName);
			$citationParserService =& $this->_citationParserServices[$citationParserServiceName];
		}
	
		return $citationParserService;
	}
	
	/**
	 * set citation parser services
	 * @param $citationParserServices array citation parser services
	 */
	function setCitationParserServices(&$citationParserServices) {
		foreach($citationParserServices as &$citationParserService) {
			if (!($this->addCitationParserService($citationParserService))) {
				$this->_citationParserServices = array();
				return false;
			}
		}
		return true;
	}

	/**
	 * add citation lookup service
	 * @param $citationLookupService CitationLookupService
	 */
	function addCitationLookupService(&$citationLookupService) {
		assert(is_a($citationLookupService, 'CitationLookupService'));
		$citationLookupServiceName = (string)$citationLookupService->getName();
		assert($citationLookupServiceName > 0 && !isset($this->_citationLookupServices[$citationLookupServiceName]));
		$this->_citationLookupServices[$citationLookupServiceName] =& $citationLookupService;
		return true;
	}
	
	/**
	 * remove citation lookup service
	 * @param $citationLookupServiceName Name of the citation lookup service to remove
	 * @return boolean true if the citation lookup service was found and removed, otherwise false
	 */
	function removeCitationLookupService($citationLookupServiceName) {
		// Remove the citation lookup service if it is in the list
		if (isset($citationLookupServiceName) && isset($this->_citationLookupServices[$citationLookupServiceName])) {
			assert($this->_citationLookupServices[$citationLookupServiceName]->getName() == $citationLookupServiceName);
			unset($this->_citationLookupServices[$citationLookupServiceName]);
			return true;
		}
	
		return false;
	}
	
	/**
	 * get all citation lookup services
	 * @return array citation lookup services
	 */
	function &getCitationLookupServices() {
		return $this->_citationLookupServices;
	}
	
	/**
	 * get a specific citation lookup service
	 * @param $citationLookupServiceName string
	 * @return array citation lookup services
	 */
	function &getCitationLookupService($citationLookupServiceName) {
		$citationLookupService = null;
	
		if (isset($citationLookupServiceName) && isset($this->_citationLookupServices[$citationLookupServiceName])) {
			assert($this->_citationLookupServices[$citationLookupServiceName]->getName() == $citationLookupServiceName);
			$citationLookupService =& $this->_citationLookupServices[$citationLookupServiceName];
		}
	
		return $citationLookupService;
	}
	
	/**
	 * set citation lookup services
	 * @param $citationLookupServices array citation lookup services
	 */
	function setCitationLookupServices(&$citationLookupServices) {
		foreach($citationLookupServices as &$citationLookupService) {
			if (!($this->addCitationLookupService($citationLookupService))) {
				$this->_citationLookupServices = array();
				return false;
			}
		}
		return true;
	}
	
	//
	// Private methods
	//
	/**
	 * Try to guess a citation's genre based on detected elements
	 * @param $metadata Citation
	 * @return integer one of METADATA_GENRE_*
	 */
	function _guessGenre(&$citation) {
		$currentGenre = $citation->getGenre();
		if (isset($currentGenre) && $currentGenre != METADATA_GENRE_UNKNOWN) {
			return $currentGenre;
		}
		
		if (!empty($citation->getVolume()) && !empty($citation->getIssue())
				&& !empty($citation->getArticleTitle()) && !empty($citation->getAuthors())
				&& !empty($citation->getIssuedDate())) {
			return METADATA_GENRE_JOURNALARTICLE;
		}
		
		if (!empty($citation->getPublisher()) && !empty($citation->getPlace())
				&& !empty($citation->getBookTitle()) && !empty($citation->getIssuedDate())
				&& (!empty($citation->getAuthors())) || !empty($citation->getEditor())) {
			return METADATA_GENRE_BOOK;
		}

		return METADATA_GENRE_UNKNOWN;
	}

	/**
	 * Derive a confidence score calculated as the number of set elements divided by
	 * the number of valid elements for the citation's genre.
	 * @param Citation $parsedCitation
	 * @return integer parse score
	 */
	function _parseScore(&$parsedCitation) {
		$setElementNames = array_keys($parsedCitation->getNonEmptyElementsAsArray());
		$allElementNames = $parsedCitation->getValidElementNames();

		// TODO: A weighted average would give a better result
		// (ie. to not penalize book citations without pages).
		$parseScore = min(((count($setElementNames) / count($allElementNames))*100), 100);

		return $parseScore;
	}

	/**
	 * Derive a confidence score calculated as the average % difference between the
	 * original and the looked up meta-data.
	 * @param array $lookedUpElements
	 * @param array $originalElements
	 * @return integer parse score
	 */
	function _lookupScore(&$lookedUpElements, &$originalElements) {
		// Don't include these elements in the calculation as
		// they are not relevant for the similarity calculation
		// and unduly reduce the similarity score.
		$ignoredElements = array('url', 'comment', 'accessDate', 'genre', 'doi', 'isbn', 'pmid');
		foreach($ignoredElements as $ignoredElement) {
			unset($lookedUpElements[$ignoredElements]);
		}

		// Calculate the sum of all similarity percentages
		$overallSimilaritySum = 0.0;
		foreach ($lookedUpElements as $elementName => $elementValue) {
			$percentSimilarity = 0.0;
			similar_text($elementValue, $originalElements[$elementName], $percentSimilarity);
			$overallSimilaritySum += $percentSimilarity;
		}

		// Calculate the average similarity
		$averageSimilarity = (integer)round($overallSimilaritySum / count($lookedUpElements));
		return $averageSimilarity;
	}

	/**
	 * Take an array of citation parse/lookup results and derive a citation with
	 * one "best" set of values.
	 * 
	 * We determine the best values within the citations that have a score above
	 * the given threshold. Citations with a score below the threshold will not be
	 * considered at all.
	 * 
	 * For these citations we count the frequency of values per meta-data element.
	 * The most frequent value will be chosen as "best" value.
	 * 
	 * If two values have the same frequency then decide based on the score. If
	 * this is still ambivalent then return the first of the remaining values.
	 *
	 * This method will also set the overall parsing score in the target citation.
	 * 
	 * @param $targetCitation the citation where the "best" values should be set
	 * @param $scoredCitations
	 * @param $scoreThreshold integer a number between 0 (=no threshold) and 100,
	 *  default: no threshold
	 * @return Citation one citation with the "best" values set
	 */
	function &_guessValues(&$targetCitation, &$scoredCitations, $scoreThreshold = 0) {
		assert($scoreThreshold >= 0 && $scoreThreshold <= 100);
		
		// Step 1: List all values and max scores that have been identified for a given element
		//         but only include values from results above a given scoring threshold
		
		// Initialize variables for the first step.
		$valuesByElementName = array();
		$maxScoresByElementNameAndValue = array();
		
		// Sort the scored citations by score with the highest score first.
		krsort($scoredCitations);
		foreach ($scoredCitations as $currentScore => $citationsForCurrentScore) {
			// Check whether the current score is below the threshold, if so
			// stop the loop. We've sorted our citations by score so the remaining
			// citations all have scores below the threshold and we can forget
			// about them.
			if ($currentScore < $scoreThreshold) {
				break;
			}
			
			foreach($citationsForCurrentScore as $citationForCurrentScore) {
				$setElements = $citationForCurrentScore->getNonEmptyElementsAsArray();
				
				// Add the element values and scores of this citation
				// to the overall element lists
				foreach($setElements as $elementName => $elementValue) {
					// Initialize sub-arrays if necessary
					if (!isset($valuesByElementName[$elementName])) {
						$valuesByElementName[$elementName] = array();
					}
					if (!isset($maxScoresByElementNameAndValue[$elementName])) {
						$maxScoresByElementNameAndValue[$elementName] = array();
					}
					
					// Add the value for the given element, as we want to count
					// value frequencies later, we explicitly allow duplicates.
					$valuesByElementName[$elementName][] = $elementValue;
					
					// As we have ordered our citations descending by score, the
					// first score found for a value is also the maximum score.
					if (!isset($maxScoresByElementNameAndValue[$elementName][$elementValue])) {
						$maxScoresByElementNameAndValue[$elementName][$elementValue] = $currentScore;
					}
				}
			}
		}
		
		// Step 2: Find out the values that were occur most frequently for each element
		//         and order these by score.
		
		foreach($valuesByElementName as $elementName => $elementValues) {
			// Count the occurrences of each value within the given element
			$elementValueFrequencies = array_count_values($elementValues);

			// Order the most frequent values to the beginning of the array
			arsort($elementValueFrequencies);
			
			// Get the most frequent values (may be several if there are more than one
			// with the same frequency).
			$scoresOfMostFrequentValues = array();
			$previousElementValueFrequency = 0;
			foreach($elementValueFrequencies as $elementValue => $elementValueFrequency) {
				// Only extract the most frequent values, jump out of the
				// loop when less frequent values start.
				if ($previousElementValueFrequency > $elementValueFrequency) break;
				$previousElementValueFrequency = $elementValueFrequency;
				
				$scoresOfMostFrequentValues[$elementValue] =
						$maxScoresByElementNameAndValue[$elementName][$elementValue];
			}
			
			// Now we can order the most frequent values by score, starting
			// with the highest score.
			arsort($scoresOfMostFrequentValues);
			
			// Now get the first key which respresents the value with the
			// highest frequency and the highest score.
			reset($scoresOfMostFrequentValues);
			$bestValue = key($scoresOfMostFrequentValues);
			
			// Set the found "best" element value in the result citation.
			$citationElementSetter = 'set'.ucfirst($elementName);
			$targetCitation->$citationElementSetter($bestValue);
		}

		// Calculate the average of all scores
		$overallScoreSum = 0;
		$overallScoreCount = 0;
		foreach ($scoredCitations as $currentScore => $citationsForCurrentScore) {
			$countCitationsForCurrentScore = count($citationsForCurrentScore);
			$overallScoreSum += $countCitationsForCurrentScore * $currentScore;
			$overallScoreCount += $countCitationsForCurrentScore;
		}
		$averageScore = $overallScoreSum / $overallScoreCount;
		
		// Get the max score (= the first key from scoredCitations
		// as these are sorted by score).
		reset($scoredCitations);
		$maxScore = key($scoredCitations);
		
		// Calculate the overall parse score as by weighing
		// the max score and the average score 50% each.
		// TODO: This algorithm seems quite arbitrary.
		$parseScore = ($maxScore + $averageScore) / 2;
		$targetCitation->setParseScore($parseScore);
	}
	
	/**
	 * FIXME: Move this somewhere in the library
	 * Create a PHP4/5 compatible shallow
	 * copy of the given object.
	 * @param $object object
	 * @return object the cloned object
	 */
	function &_cloneObject(&$object) {
		if (checkPhpVersion('5.0.0')) {
			// We use the PHP5 clone() syntax so that PHP4 doesn't
			// raise a parse error.
			$clonedObject = clone($object);
		} else {
			// PHP4 always clones objects on assignment
			$clonedObject = $object;
		}
		return $clonedObject;
	}
}
?>