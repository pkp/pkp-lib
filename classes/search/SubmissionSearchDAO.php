<?php

/**
 * @file classes/search/SubmissionSearchDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearchDAO
 *
 * @ingroup search
 *
 * @see SubmissionSearch
 *
 * @brief DAO class for submission search index.
 */

namespace PKP\search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPString;

class SubmissionSearchDAO extends \PKP\db\DAO
{
    public const MAX_KEYWORD_LENGTH = 60;

    /**
     * Add a word to the keyword list (if it doesn't already exist).
     *
     * @param string $keyword
     *
     * @return ?int the keyword ID
     */
    public function insertKeyword($keyword)
    {
        if (PKPString::strlen($keyword) > self::MAX_KEYWORD_LENGTH) {
            return null;
        }

        static $submissionSearchKeywordIds = [];
        if (isset($submissionSearchKeywordIds[$keyword])) {
            return $submissionSearchKeywordIds[$keyword];
        }
        $result = $this->retrieve(
            'SELECT keyword_id FROM submission_search_keyword_list WHERE keyword_text = ?',
            [$keyword]
        );
        if ($row = $result->current()) {
            $keywordId = $row->keyword_id;
        } else {
            if ($this->update(
                'INSERT INTO submission_search_keyword_list (keyword_text) VALUES (?)',
                [$keyword],
                true,
                false
            )) {
                $keywordId = $this->_getInsertId();
            } else {
                $keywordId = null; // Bug #2324
            }
        }

        $submissionSearchKeywordIds[$keyword] = $keywordId;

        return $keywordId;
    }

    /**
     * Delete all keywords for a submission.
     *
     * @param int $submissionId
     * @param int $type optional
     * @param int $assocId optional
     */
    public function deleteSubmissionKeywords($submissionId, $type = null, $assocId = null)
    {
        DB::table('submission_search_objects')
            ->where('submission_id', '=', $submissionId)
            ->when(isset($type), fn (Builder $query) => $query->where('type', '=', $type))
            ->when(isset($assocId), fn (Builder $query) => $query->where('assoc_id', '=', $assocId))
            ->delete();
    }

    /**
     * Add a submission object to the index (if already exists, indexed keywords are cleared).
     *
     * @param int $submissionId
     * @param int $type
     * @param ?int $assocId
     *
     * @return int the object ID
     */
    public function insertObject($submissionId, $type, $assocId)
    {
        $objectId = DB::table('submission_search_objects')
            ->where('submission_id', '=', $submissionId)
            ->where('type', '=', $type)
            ->when($assocId !== null, fn (Builder $query) => $query->where('assoc_id', '=', $assocId))
            ->value('object_id');

        if ($objectId) {
            // Clear the old keywords
            DB::table('submission_search_object_keywords')
                ->where('object_id', '=', $objectId)
                ->delete();
            return $objectId;
        }

        return DB::table('submission_search_objects')->insertGetId([
            'submission_id' => $submissionId,
            'type' => $type,
            'assoc_id' => $assocId
        ]);
    }

    /**
     * Index an occurrence of a keyword in an object.
     */
    public function insertObjectKeywords(int $objectId, array $keywords): void
    {
        // Get the latest position if exists
        $position = DB::table('submission_search_object_keywords')->where('object_id', $objectId)->max('pos');
        if ($position === null) {
            $position = -1;
        }

        $collection = collect();
        foreach ($keywords as $keyword) {
            $keywordId = $this->insertKeyword($keyword);
            if ($keywordId === null) {
                continue;
            } // Bug #2324

            $collection->push([
                'object_id' => $objectId,
                'keyword_id' => $keywordId,
                'pos' => ++$position,
            ]);
        }
        if ($collection->isEmpty()) {
            return;
        }

        $chunks = $collection->chunk(1000);
        foreach ($chunks as $chunk) {
            DB::table('submission_search_object_keywords')->insert($chunk->toArray());
        }
    }

    /**
     * Clear the search index.
     */
    public function clearIndex()
    {
        DB::table('submission_search_objects')->truncate();
        DB::table('submission_search_keyword_list')->truncate();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SubmissionSearchDAO', '\SubmissionSearchDAO');
}
