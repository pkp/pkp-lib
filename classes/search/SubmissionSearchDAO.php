<?php

/**
 * @file classes/search/SubmissionSearchDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSearchDAO
 * @ingroup search
 *
 * @see SubmissionSearch
 *
 * @brief DAO class for submission search index.
 */

namespace PKP\search;

use Illuminate\Support\Facades\DB;

class SubmissionSearchDAO extends \PKP\db\DAO
{
    /**
     * Add a word to the keyword list (if it doesn't already exist).
     *
     * @param string $keyword
     *
     * @return int the keyword ID
     */
    public function insertKeyword($keyword)
    {
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
                $keywordId = $this->_getInsertId('submission_search_keyword_list', 'keyword_id');
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
        $sql = 'SELECT object_id FROM submission_search_objects WHERE submission_id = ?';
        $params = [(int) $submissionId];

        if (isset($type)) {
            $sql .= ' AND type = ?';
            $params[] = (int) $type;
        }

        if (isset($assocId)) {
            $sql .= ' AND assoc_id = ?';
            $params[] = (int) $assocId;
        }

        $result = $this->retrieve($sql, $params);
        foreach ($result as $row) {
            $this->update('DELETE FROM submission_search_object_keywords WHERE object_id = ?', [$row->object_id]);
            $this->update('DELETE FROM submission_search_objects WHERE object_id = ?', [$row->object_id]);
        }
    }

    /**
     * Add a submission object to the index (if already exists, indexed keywords are cleared).
     *
     * @param int $submissionId
     * @param int $type
     * @param int $assocId
     *
     * @return int the object ID
     */
    public function insertObject($submissionId, $type, $assocId, $keepExisting = false)
    {
        $result = $this->retrieve(
            'SELECT object_id FROM submission_search_objects WHERE submission_id = ? AND type = ? AND assoc_id = ?',
            [(int) $submissionId, (int) $type, (int) $assocId]
        );
        if ($row = $result->current()) {
            $this->update(
                'DELETE FROM submission_search_object_keywords WHERE object_id = ?',
                [(int) $row->object_id]
            );
            return $row->object_id;
        } else {
            $this->update(
                'INSERT INTO submission_search_objects (submission_id, type, assoc_id) VALUES (?, ?, ?)',
                [(int) $submissionId, (int) $type, (int) $assocId]
            );
            return $this->_getInsertId('submission_search_objects', 'object_id');
        }
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
        $this->update('DELETE FROM submission_search_object_keywords');
        $this->update('DELETE FROM submission_search_objects');
        $this->update('DELETE FROM submission_search_keyword_list');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SubmissionSearchDAO', '\SubmissionSearchDAO');
}
