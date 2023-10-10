<?php

/**
 * @file classes/search/SubmissionSearch.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearch
 *
 * @ingroup search
 *
 * @see SubmissionSearchDAO
 *
 * @brief Class for retrieving search results.
 *
 * FIXME: NEAR; precedence w/o parens?; stemming; weighted counting
 */

namespace PKP\search;

use APP\core\Application;
use APP\core\Request;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPString;
use PKP\core\VirtualArrayIterator;
use PKP\db\DAO;
use PKP\plugins\Hook;
use PKP\user\User;

abstract class SubmissionSearch
{
    // Search types
    public const SUBMISSION_SEARCH_AUTHOR = 1;
    public const SUBMISSION_SEARCH_TITLE = 2;
    public const SUBMISSION_SEARCH_ABSTRACT = 4;
    public const SUBMISSION_SEARCH_DISCIPLINE = 8;
    public const SUBMISSION_SEARCH_SUBJECT = 16;
    public const SUBMISSION_SEARCH_KEYWORD = 17;
    public const SUBMISSION_SEARCH_TYPE = 32;
    public const SUBMISSION_SEARCH_COVERAGE = 64;
    public const SUBMISSION_SEARCH_GALLEY_FILE = 128;
    public const SUBMISSION_SEARCH_SUPPLEMENTARY_FILE = 256;
    public const SUBMISSION_SEARCH_INDEX_TERMS = 120;

    public const SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT = 20;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Parses a search query string.
     * Supports +/-, AND/OR, parens
     *
     * @param string $query
     *
     * @return array of the form ('+' => <required>, '' => <optional>, '-' => excluded)
     */
    public function _parseQuery($query)
    {
        $count = PKPString::regexp_match_all('/(\+|\-|)("[^"]+"|\(|\)|[^\s\)]+)/', $query, $matches);
        $pos = 0;
        return $this->_parseQueryInternal($matches[1], $matches[2], $pos, $count);
    }

    /**
     * Query parsing helper routine.
     * Returned structure is based on that used by the Search::QueryParser Perl module.
     */
    public function _parseQueryInternal($signTokens, $tokens, &$pos, $total)
    {
        $return = ['+' => [], '' => [], '-' => []];
        $postBool = $preBool = '';

        $submissionSearchIndex = Application::getSubmissionSearchIndex();

        $notOperator = PKPString::strtolower(__('search.operator.not'));
        $andOperator = PKPString::strtolower(__('search.operator.and'));
        $orOperator = PKPString::strtolower(__('search.operator.or'));
        while ($pos < $total) {
            if (!empty($signTokens[$pos])) {
                $sign = $signTokens[$pos];
            } elseif (empty($sign)) {
                $sign = '+';
            }
            $token = PKPString::strtolower($tokens[$pos++]);
            switch ($token) {
                case $notOperator:
                    $sign = '-';
                    break;
                case ')':
                    return $return;
                case '(':
                    $token = $this->_parseQueryInternal($signTokens, $tokens, $pos, $total);
                    // no break
                default:
                    $postBool = '';
                    if ($pos < $total) {
                        $peek = PKPString::strtolower($tokens[$pos]);
                        if ($peek == $orOperator) {
                            $postBool = 'or';
                            $pos++;
                        } elseif ($peek == $andOperator) {
                            $postBool = 'and';
                            $pos++;
                        }
                    }
                    $bool = empty($postBool) ? $preBool : $postBool;
                    $preBool = $postBool;
                    if ($bool == 'or') {
                        $sign = '';
                    }
                    if (is_array($token)) {
                        $k = $token;
                    } else {
                        $k = $submissionSearchIndex->filterKeywords($token, true);
                    }
                    if (!empty($k)) {
                        $return[$sign][] = $k;
                    }
                    $sign = '';
                    break;
            }
        }
        return $return;
    }

    /**
     * Takes an unordered list of search result data, flattens it, orders it
     * and excludes unwanted results.
     *
     * @return array An ordered and flattened list of article IDs.
     */
    public function _getMergedArray($context, &$keywords, $publishedFrom, $publishedTo)
    {
        $resultsPerKeyword = Config::getVar('search', 'results_per_keyword', 100);

        $mergedKeywords = ['+' => [], '' => [], '-' => []];
        foreach ($keywords as $type => $keyword) {
            if (!empty($keyword['+'])) {
                $mergedKeywords['+'][] = ['type' => $type, '+' => $keyword['+'], '' => [], '-' => []];
            }
            if (!empty($keyword[''])) {
                $mergedKeywords[''][] = ['type' => $type, '+' => [], '' => $keyword[''], '-' => []];
            }
            if (!empty($keyword['-'])) {
                $mergedKeywords['-'][] = ['type' => $type, '+' => [], '' => $keyword['-'], '-' => []];
            }
        }
        return $this->_getMergedKeywordResults($context, $mergedKeywords, null, $publishedFrom, $publishedTo, $resultsPerKeyword);
    }

    /**
     * Recursive helper for _getMergedArray.
     */
    public function _getMergedKeywordResults($context, &$keyword, $type, $publishedFrom, $publishedTo, $resultsPerKeyword)
    {
        $mergedResults = null;

        if (isset($keyword['type'])) {
            $type = $keyword['type'];
        }

        foreach ($keyword['+'] as $phrase) {
            $results = $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword);
            if ($mergedResults === null) {
                $mergedResults = $results;
            } else {
                foreach ($mergedResults as $submissionId => $data) {
                    if (isset($results[$submissionId])) {
                        $mergedResults[$submissionId]['count'] += $results[$submissionId]['count'];
                    } else {
                        unset($mergedResults[$submissionId]);
                    }
                }
            }
        }

        if ($mergedResults == null) {
            $mergedResults = [];
        }

        if (!empty($mergedResults) || empty($keyword['+'])) {
            foreach ($keyword[''] as $phrase) {
                $results = $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword);
                foreach ($results as $submissionId => $data) {
                    if (isset($mergedResults[$submissionId])) {
                        $mergedResults[$submissionId]['count'] += $data['count'];
                    } elseif (empty($keyword['+'])) {
                        $mergedResults[$submissionId] = $data;
                    }
                }
            }

            foreach ($keyword['-'] as $phrase) {
                $results = $this->_getMergedPhraseResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword);
                foreach ($results as $submissionId => $count) {
                    if (isset($mergedResults[$submissionId])) {
                        unset($mergedResults[$submissionId]);
                    }
                }
            }
        }

        return $mergedResults;
    }

    /**
     * Recursive helper for _getMergedArray.
     */
    protected function _getMergedPhraseResults($context, &$phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword)
    {
        if (isset($phrase['+'])) {
            return $this->_getMergedKeywordResults($context, $phrase, $type, $publishedFrom, $publishedTo, $resultsPerKeyword);
        }

        return $this->getSearchDao()->getPhraseResults(
            $context,
            $phrase,
            $publishedFrom,
            $publishedTo,
            $type,
            $resultsPerKeyword
        );
    }

    /**
     * Return an array of search results matching the supplied
     * keyword IDs in decreasing order of match quality.
     * Keywords are supplied in an array of the following format:
     * $keywords[SUBMISSION_SEARCH_AUTHOR] = array('John', 'Doe');
     * $keywords[SUBMISSION_SEARCH_...] = array(...);
     * $keywords[null] = array('Matches', 'All', 'Fields');
     *
     * @param Request $request
     * @param Context $context The context to search
     * @param array $keywords List of keywords
     * @param string $error a reference to a variable that will
     *  contain an error message if the search service produces
     *  an error.
     * @param string $publishedFrom Search-from date
     * @param string $publishedTo Search-to date
     * @param ?\PKP\db\DBResultRange $rangeInfo Information on the range of results to return
     * @param array $exclude An array of article IDs to exclude from the result.
     *
     * @return VirtualArrayIterator An iterator with one entry per retrieved
     *  article containing the article, published submission, issue, context, etc.
     */
    public function retrieveResults($request, $context, $keywords, &$error, $publishedFrom = null, $publishedTo = null, $rangeInfo = null, $exclude = [])
    {
        // Pagination
        if ($rangeInfo && $rangeInfo->isValid()) {
            $page = $rangeInfo->getPage();
            $itemsPerPage = $rangeInfo->getCount();
        } else {
            $page = 1;
            $itemsPerPage = self::SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT;
        }

        // Result set ordering.
        [$orderBy, $orderDir] = $this->getResultSetOrdering($request);

        // Check whether a search plug-in jumps in to provide ranked search results.
        $totalResults = null;
        $results = null;
        $hookResult = Hook::call(
            'SubmissionSearch::retrieveResults',
            [&$context, &$keywords, $publishedFrom, $publishedTo, $orderBy, $orderDir, $exclude, $page, $itemsPerPage, &$totalResults, &$error, &$results]
        );

        // If no search plug-in is activated then fall back to the
        // default database search implementation.
        if ($hookResult === false) {
            // Parse the query.
            foreach ($keywords as $searchType => $query) {
                $keywords[$searchType] = $this->_parseQuery($query);
            }

            // Fetch all the results from all the keywords into one array
            // (mergedResults), where mergedResults[submission_id]
            // = sum of all the occurrences for all keywords associated with
            // that article ID.
            $mergedResults = $this->_getMergedArray($context, $keywords, $publishedFrom, $publishedTo);

            // Convert mergedResults into an array (frequencyIndicator =>
            // $submissionId).
            // The frequencyIndicator is a synthetically-generated number,
            // where higher is better, indicating the quality of the match.
            // It is generated here in such a manner that matches with
            // identical frequency do not collide.
            $results = $this->getSparseArray($mergedResults, $orderBy, $orderDir, $exclude);
            $totalResults = count($results);

            // Use only the results for the specified page.
            $offset = $itemsPerPage * ($page - 1);
            $length = max($totalResults - $offset, 0);
            $length = min($itemsPerPage, $length);
            if ($length == 0) {
                $results = [];
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
        $results = $this->formatResults($results, $request->getUser());

        // Return the appropriate iterator.
        return new VirtualArrayIterator($results, $totalResults, $page, $itemsPerPage);
    }

    /**
     * Return the available options for the result
     * set ordering direction.
     *
     * @return array
     */
    public function getResultSetOrderingDirectionOptions()
    {
        return [
            'asc' => __('search.results.orderDir.asc'),
            'desc' => __('search.results.orderDir.desc')
        ];
    }

    /**
     * Return the currently selected result
     * set ordering option (default: descending relevance).
     *
     * @param Request $request
     *
     * @return array An array with the order field as the
     * first entry and the order direction as the second
     * entry.
     */
    public function getResultSetOrdering($request)
    {
        // Order field.
        $orderBy = $request->getUserVar('orderBy');
        $orderByOptions = $this->getResultSetOrderingOptions($request);
        if (is_null($orderBy) || !in_array($orderBy, array_keys($orderByOptions))) {
            $orderBy = 'score';
        }

        // Ordering direction.
        $orderDir = $request->getUserVar('orderDir');
        $orderDirOptions = $this->getResultSetOrderingDirectionOptions();
        if (is_null($orderDir) || !in_array($orderDir, array_keys($orderDirOptions))) {
            $orderDir = $this->getDefaultOrderDir($orderBy);
        }

        return [$orderBy, $orderDir];
    }

    //
    // Methods to be implemented by subclasses.
    //
    /**
     * See implementation of retrieveResults for a description of this
     * function.
     *
     * Note that this function is also called externally to fetch
     * results for the title index, and possibly elsewhere.
     *
     * @param array $results
     * @param User $user optional (if availability information is desired)
     *
     * @return array
     */
    abstract public function formatResults($results, $user = null);

    /**
     * Return the available options for result set ordering.
     *
     * @param Request $request
     *
     * @return array
     */
    abstract public function getResultSetOrderingOptions($request);

    /**
     * See implementation of retrieveResults for a description of this
     * function.
     */
    abstract protected function getSparseArray($unorderedResults, $orderBy, $orderDir, $exclude);

    /**
     * Return the default order direction.
     *
     * @param string $orderBy
     *
     * @return string
     */
    abstract protected function getDefaultOrderDir($orderBy);

    /**
     * Return the search DAO
     *
     * @return DAO
     */
    abstract protected function getSearchDao();
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SubmissionSearch', '\SubmissionSearch');
    foreach ([
        'SUBMISSION_SEARCH_AUTHOR',
        'SUBMISSION_SEARCH_TITLE',
        'SUBMISSION_SEARCH_ABSTRACT',
        'SUBMISSION_SEARCH_DISCIPLINE',
        'SUBMISSION_SEARCH_SUBJECT',
        'SUBMISSION_SEARCH_KEYWORD',
        'SUBMISSION_SEARCH_TYPE',
        'SUBMISSION_SEARCH_COVERAGE',
        'SUBMISSION_SEARCH_GALLEY_FILE',
        'SUBMISSION_SEARCH_SUPPLEMENTARY_FILE',
        'SUBMISSION_SEARCH_INDEX_TERMS',
        'SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT',
    ] as $constantName) {
        define($constantName, constant('\SubmissionSearch::' . $constantName));
    }
}
