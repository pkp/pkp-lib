<?php

/**
 * @file classes/search/SubmissionSearch.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearch
 * @ingroup search
 * @see SubmissionSearchDAO
 *
 * @brief Class for retrieving search results.
 *
 * FIXME: NEAR; precedence w/o parens?; stemming; weighted counting
 */

// Search types
define('SUBMISSION_SEARCH_AUTHOR',		0x00000001);
define('SUBMISSION_SEARCH_TITLE',		0x00000002);
define('SUBMISSION_SEARCH_ABSTRACT',		0x00000004);
define('SUBMISSION_SEARCH_DISCIPLINE',		0x00000008);
define('SUBMISSION_SEARCH_SUBJECT',		0x00000010);
define('SUBMISSION_SEARCH_TYPE',		0x00000020);
define('SUBMISSION_SEARCH_COVERAGE',		0x00000040);
define('SUBMISSION_SEARCH_GALLEY_FILE',		0x00000080);
define('SUBMISSION_SEARCH_SUPPLEMENTARY_FILE',	0x00000100);
define('SUBMISSION_SEARCH_INDEX_TERMS',		0x00000078);

define('SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT', 20);

import('lib.pkp.classes.search.SubmissionSearchIndex');

class SubmissionSearch {
	/**
	 * Constructor
	 */
	function SubmissionSearch() {
	}

	/**
	 * Parses a search query string.
	 * Supports +/-, AND/OR, parens
	 * @param $query
	 * @return array of the form ('+' => <required>, '' => <optional>, '-' => excluded)
	 */
	function _parseQuery($query) {
		$count = preg_match_all('/(\+|\-|)("[^"]+"|\(|\)|[^\s\)]+)/', $query, $matches);
		$pos = 0;
		return $this->_parseQueryInternal($matches[1], $matches[2], $pos, $count);
	}

	/**
	 * Query parsing helper routine.
	 * Returned structure is based on that used by the Search::QueryParser Perl module.
	 */
	function _parseQueryInternal($signTokens, $tokens, &$pos, $total) {
		$return = array('+' => array(), '' => array(), '-' => array());
		$postBool = $preBool = '';

		$notOperator = String::strtolower(__('search.operator.not'));
		$andOperator = String::strtolower(__('search.operator.and'));
		$orOperator = String::strtolower(__('search.operator.or'));
		while ($pos < $total) {
			if (!empty($signTokens[$pos])) $sign = $signTokens[$pos];
			else if (empty($sign)) $sign = '+';
			$token = String::strtolower($tokens[$pos++]);
			switch ($token) {
				case $notOperator:
					$sign = '-';
					break;
				case ')':
					return $return;
				case '(':
					$token = $this->_parseQueryInternal($signTokens, $tokens, $pos, $total);
				default:
					$postBool = '';
					if ($pos < $total) {
						$peek = String::strtolower($tokens[$pos]);
						if ($peek == $orOperator) {
							$postBool = 'or';
							$pos++;
						} else if ($peek == $andOperator) {
							$postBool = 'and';
							$pos++;
						}
					}
					$bool = empty($postBool) ? $preBool : $postBool;
					$preBool = $postBool;
					if ($bool == 'or') $sign = '';
					if (is_array($token)) {
						$k = $token;
					} else {
						$submissionSearchIndex = new SubmissionSearchIndex();
						$k = $submissionSearchIndex->filterKeywords($token, true);
					}
					if (!empty($k)) $return[$sign][] = $k;
					$sign = '';
					break;
			}
		}
		return $return;
	}

	/**
	 * See implementation of retrieveResults for a description of this
	 * function.
	 */
	function &_getMergedArray($context, &$keywords, $publishedFrom, $publishedTo) {
		$resultsPerKeyword = Config::getVar('search', 'results_per_keyword');
		$resultCacheHours = Config::getVar('search', 'result_cache_hours');
		if (!is_numeric($resultsPerKeyword)) $resultsPerKeyword = 100;
		if (!is_numeric($resultCacheHours)) $resultCacheHours = 24;

		$mergedKeywords = array('+' => array(), '' => array(), '-' => array());
		foreach ($keywords as $type => $keyword) {
			if (!empty($keyword['+']))
				$mergedKeywords['+'][] = array('type' => $type, '+' => $keyword['+'], '' => array(), '-' => array());
			if (!empty($keyword['']))
				$mergedKeywords[''][] = array('type' => $type, '+' => array(), '' => $keyword[''], '-' => array());
			if (!empty($keyword['-']))
				$mergedKeywords['-'][] = array('type' => $type, '+' => array(), '' => $keyword['-'], '-' => array());
		}
		$mergedResults = $this->_getMergedKeywordResults($context, $mergedKeywords, null, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours);

		return $mergedResults;
	}

	/**
	 * Recursive helper for _getMergedArray.
	 */
	function _getMergedKeywordResults($context, &$keyword, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours) {
		$mergedResults = null;

		if (isset($keyword['type'])) {
			$type = $keyword['type'];
		}

		foreach ($keyword['+'] as $phrase) {
			$results =& $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours);
			if ($mergedResults === null) {
				$mergedResults = $results;
			} else {
				foreach ($mergedResults as $articleId => $count) {
					if (isset($results[$articleId])) {
						$mergedResults[$articleId] += $results[$articleId];
					} else {
						unset($mergedResults[$articleId]);
					}
				}
			}
		}

		if ($mergedResults == null) {
			$mergedResults = array();
		}

		if (!empty($mergedResults) || empty($keyword['+'])) {
			foreach ($keyword[''] as $phrase) {
				$results =& $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours);
				foreach ($results as $articleId => $count) {
					if (isset($mergedResults[$articleId])) {
						$mergedResults[$articleId] += $count;
					} else if (empty($keyword['+'])) {
						$mergedResults[$articleId] = $count;
					}
				}
			}

			foreach ($keyword['-'] as $phrase) {
				$results =& $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours);
				foreach ($results as $articleId => $count) {
					if (isset($mergedResults[$articleId])) {
						unset($mergedResults[$articleId]);
					}
				}
			}
		}

		return $mergedResults;
	}

	/**
	 * Recursive helper for _getMergedArray.
	 */
	function &_getMergedPhraseResults($context, &$phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours) {
		if (isset($phrase['+'])) {
			$mergedResults =& $this->_getMergedKeywordResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword, $resultCacheHours);
			return $mergedResults;
		}

		$mergedResults = array();
		$articleSearchDao = DAORegistry::getDAO('ArticleSearchDAO'); /* @var $articleSearchDao ArticleSearchDAO */
		$results = $articleSearchDao->getPhraseResults(
			$context,
			$phrase,
			$publishedFrom,
			$publishedTo,
			$type,
			$resultsPerKeyword,
			$resultCacheHours
		);
		while ($resuult = $results->next()) {
			$articleId = $result['submission_id'];
			if (!isset($mergedResults[$articleId])) {
				$mergedResults[$articleId] = $result['count'];
			} else {
				$mergedResults[$articleId] += $result['count'];
			}
		}
		return $mergedResults;
	}

	/**
	 * See implementation of retrieveResults for a description of this
	 * function.
	 */
	function &_getSparseArray(&$mergedResults) {
		$resultCount = count($mergedResults);
		$results = array();
		$i = 0;
		foreach ($mergedResults as $articleId => $count) {
				$frequencyIndicator = ($resultCount * $count) + $i++;
				$results[$frequencyIndicator] = $articleId;
		}
		krsort($results);
		return $results;
	}

	/**
	 * Load the keywords array from a given search filter.
	 * @param $searchFilters array Search filters as returned from
	 *  SubmissionSearch::getSearchFilters()
	 * @return array Keyword array as required by SubmissionSearch::retrieveResults()
	 */
	function getKeywordsFromSearchFilters($searchFilters) {
		$indexFieldMap = $this->getIndexFieldMap();
		$indexFieldMap[SUBMISSION_SEARCH_INDEX_TERMS] = 'indexTerms';
		$keywords = array();
		if (isset($searchFilters['query'])) {
			$keywords[null] = $searchFilters['query'];
		}
		foreach($indexFieldMap as $bitmap => $searchField) {
			if (isset($searchFilters[$searchField]) && !empty($searchFilters[$searchField])) {
				$keywords[$bitmap] = $searchFilters[$searchField];
			}
		}
		return $keywords;
	}

	/**
	 * Return an array of search results matching the supplied
	 * keyword IDs in decreasing order of match quality.
	 * Keywords are supplied in an array of the following format:
	 * $keywords[SUBMISSION_SEARCH_AUTHOR] = array('John', 'Doe');
	 * $keywords[SUBMISSION_SEARCH_...] = array(...);
	 * $keywords[null] = array('Matches', 'All', 'Fields');
	 * @param $context object The context to search
	 * @param $keywords array List of keywords
	 * @param $error string a reference to a variable that will
	 *  contain an error message if the search service produces
	 *  an error.
	 * @param $publishedFrom object Search-from date
	 * @param $publishedTo object Search-to date
	 * @param $rangeInfo Information on the range of results to return
	 * @return VirtualArrayIterator An iterator with one entry per retrieved
	 *  article containing the article, published article, issue, context, etc.
	 */
	function retrieveResults($context, $keywords, &$error, $publishedFrom = null, $publishedTo = null, $rangeInfo = null) {
		// Pagination
		if ($rangeInfo && $rangeInfo->isValid()) {
			$page = $rangeInfo->getPage();
			$itemsPerPage = $rangeInfo->getCount();
		} else {
			$page = 1;
			$itemsPerPage = SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT;
		}

		// Check whether a search plug-in jumps in to provide ranked search results.
		$totalResults = null;
		$results = HookRegistry::call(
			'SubmissionSearch::retrieveResults',
			array(&$context, &$keywords, $publishedFrom, $publishedTo, $page, $itemsPerPage, &$totalResults, &$error)
		);

		// If no search plug-in is activated then fall back to the
		// default database search implementation.
		if ($results === false) {
			// Parse the query.
			foreach($keywords as $searchType => $query) {
				$keywords[$searchType] = $this->_parseQuery($query);
			}

			// Fetch all the results from all the keywords into one array
			// (mergedResults), where mergedResults[submission_id]
			// = sum of all the occurences for all keywords associated with
			// that article ID.
			$mergedResults =& $this->_getMergedArray($context, $keywords, $publishedFrom, $publishedTo);

			// Convert mergedResults into an array (frequencyIndicator =>
			// $articleId).
			// The frequencyIndicator is a synthetically-generated number,
			// where higher is better, indicating the quality of the match.
			// It is generated here in such a manner that matches with
			// identical frequency do not collide.
			$results =& $this->_getSparseArray($mergedResults);
			$totalResults = count($results);

			// Use only the results for the specified page.
			$offset = $itemsPerPage * ($page-1);
			$length = max($totalResults - $offset, 0);
			$length = min($itemsPerPage, $length);
			if ($length == 0) {
				$results = array();
			} else {
				$results = array_slice(
					$results,
					$offset,
					$length
				);
			}
		}

		// Take the range of results and retrieve the Article, Journal,
		// and associated objects.
		$results = $this->formatResults($results);

		// Return the appropriate iterator.
		import('lib.pkp.classes.core.VirtualArrayIterator');
		return new VirtualArrayIterator($results, $totalResults, $page, $itemsPerPage);
	}

	function getIndexFieldMap() {
		return array(
			SUBMISSION_SEARCH_AUTHOR => 'authors',
			SUBMISSION_SEARCH_TITLE => 'title',
			SUBMISSION_SEARCH_ABSTRACT => 'abstract',
			SUBMISSION_SEARCH_GALLEY_FILE => 'galleyFullText',
			SUBMISSION_SEARCH_SUPPLEMENTARY_FILE => 'suppFiles',
			SUBMISSION_SEARCH_DISCIPLINE => 'discipline',
			SUBMISSION_SEARCH_SUBJECT => 'subject',
			SUBMISSION_SEARCH_TYPE => 'type',
			SUBMISSION_SEARCH_COVERAGE => 'coverage'
		);
	}
}

?>

