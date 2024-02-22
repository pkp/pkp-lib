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
     * @param string|array $text
     * @param bool $allowWildcards
     *
     * @return string[] of keywords
     */
    public static function filterKeywords($text, $allowWildcards = false, bool $allowShortWords = false, bool $allowNumericWords = false): array
    {
        $minLength = Config::getVar('search', 'min_word_length');
        $stopwords = static::loadStopwords();

        // Join multiple lines into a single string
        if (is_array($text)) {
            $text = join("\n", $text);
        }

        // Attempts to fix bad UTF-8 characters
        $previous = mb_substitute_character();
        mb_substitute_character('none');
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        mb_substitute_character($previous);

        // Removes all control (C) characters, marks (M), punctuations (P), symbols (S) and separators (Z) except "*" (which is addressed below)
        $text = PKPString::regexp_replace('/(?!\*)[\\p{C}\\p{M}\\p{P}\\p{S}\\p{Z}]+/', ' ', $text);
        $text = PKPString::regexp_replace('/[\*]/', $allowWildcards ? '%' : ' ', $text);
        $text = PKPString::strtolower($text);

        // Split into words
        $words = PKPString::regexp_split('/\s+/', $text);

        // FIXME Do not perform further filtering for some fields, e.g., author names?

        $keywords = [];
        foreach ($words as $word) {
            // Ignores: stop words, short words (when $allowShortWords is false) and words composed solely of numbers (when $allowNumericWords is false)
            if (empty($stopwords[$word]) && ($allowShortWords || PKPString::strlen($word) >= $minLength) && ($allowNumericWords || !is_numeric($word))) {
                $keywords[] = PKPString::substr($word, 0, static::SEARCH_KEYWORD_MAX_LENGTH);
            }
        }
        return $keywords;
    }

    /**
     * Return list of stopwords.
     * FIXME: Should this be locale-specific?
     *
     * @return array<string,int> Stop words (in lower case) as keys and 1 as value
     */
    protected static function loadStopwords()
    {
        static $searchStopwords;

        return $searchStopwords ??= array_fill_keys(
            collect(file(base_path(static::SEARCH_STOPWORDS_FILE)))
                ->map(fn (string $word) => trim($word))
                // Ignore comments/line-breaks
                ->filter(fn (string $word) => !empty($word) && $word[0] !== '#')
                // Include a map for empty words
                ->push('')
                ->toArray(),
            1
        );
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
