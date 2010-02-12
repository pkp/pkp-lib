<?php

/**
 * @file classes/citation/CitationManager.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationManager
 * @ingroup citation
 *
 * @brief Class providing citation lookup and parsing services.
 */

// $Id$


class CitationManager {
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
				$lookedUpCitation =& cloneObject($citation);

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
}
?>