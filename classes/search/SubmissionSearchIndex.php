<?php

/**
 * @file classes/search/SubmissionSearchIndex.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearchIndex
 *
 * @ingroup search
 *
 * @brief Class to maintain a submission search index.
 */

namespace PKP\search;

use APP\submission\Submission;
use PKP\config\Config;
use PKP\core\PKPString;

abstract class SubmissionSearchIndex
{
    public const SEARCH_STOPWORDS_FILE = 'lib/pkp/registry/stopwords.txt';

    // Words are truncated to at most this length
    public const SEARCH_KEYWORD_MAX_LENGTH = 40;

    /**
     * Split a string into a clean array of keywords
     *
     * @param string $text
     * @param bool $allowWildcards
     *
     * @return array of keywords
     */
    public function filterKeywords($text, $allowWildcards = false)
    {
        $minLength = Config::getVar('search', 'min_word_length');
        $stopwords = $this->_loadStopwords();

        // Join multiple lines into a single string
        if (is_array($text)) {
            $text = join("\n", $text);
        }

        // Remove punctuation
        $text = PKPString::regexp_replace('/[!"\#\$%\'\(\)\.\?@\[\]\^`\{\}~]/', '', $text);
        $text = PKPString::regexp_replace('/[\+,:;&\/<=>\|\\\]/', ' ', $text);
        $text = PKPString::regexp_replace('/[\*]/', $allowWildcards ? '%' : ' ', $text);
        $text = PKPString::strtolower($text);

        // Split into words
        $words = PKPString::regexp_split('/\s+/', $text);

        // FIXME Do not perform further filtering for some fields, e.g., author names?

        // Remove stopwords
        $keywords = [];
        foreach ($words as $k) {
            if (!isset($stopwords[$k]) && PKPString::strlen($k) >= $minLength && !is_numeric($k)) {
                $keywords[] = PKPString::substr($k, 0, self::SEARCH_KEYWORD_MAX_LENGTH);
            }
        }
        return $keywords;
    }

    /**
     * Return list of stopwords.
     * FIXME: Should this be locale-specific?
     *
     * @return array with stopwords as keys
     */
    protected function _loadStopwords()
    {
        static $searchStopwords;

        if (!isset($searchStopwords)) {
            // Load stopwords only once per request
            $searchStopwords = array_count_values(
                array_filter(
                    array_map('trim', file(dirname(__FILE__, 5) . '/' . self::SEARCH_STOPWORDS_FILE)),
                    function ($a) {
                        return !empty($a) && $a[0] != '#';
                    }
                )
            );
            $searchStopwords[''] = 1;
        }

        return $searchStopwords;
    }

    /**
     * Let the indexing back-end know that the current transaction
     * finished so that the index can be batch-updated.
     */
    abstract public function submissionChangesFinished();

    /**
     * Signal to the indexing back-end that the metadata of a submission
     * changed.
     *
     * Push indexing implementations will try to immediately update
     * the index to reflect the changes. Pull implementations will
     * mark articles as "changed" and let the indexing back-end decide
     * the best point in time to actually index the changed data.
     *
     * @param Submission $submission
     */
    abstract public function submissionMetadataChanged($submission);

    /**
     * Remove indexed file contents for a submission
     *
     * @param Submission $submission
     */
    abstract public function clearSubmissionFiles($submission);

    /**
     * Delete a submission's search indexing
     *
     * @param int $type optional
     * @param int $assocId optional
     */
    abstract public function deleteTextIndex(
        int $submissionId,
        $type = null,
        $assocId = null
    );
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SubmissionSearchIndex', '\SubmissionSearchIndex');
    foreach (['SEARCH_STOPWORDS_FILE', 'SEARCH_KEYWORD_MAX_LENGTH'] as $constantName) {
        define($constantName, constant('\SubmissionSearchIndex::' . $constantName));
    }
}
