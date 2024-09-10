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
use Illuminate\Support\Str;

class SubmissionSearchDAO extends \PKP\db\DAO
{
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
        ], 'object_id');
    }

    /**
     * Index an occurrence of a keyword in an object.
     */
    public function insertObjectKeywords(int $objectId, array $keywords): void
    {
        /** @var array<string,?int> */
        static $keywordMap = [];

        // Discard long keywords
        $keywords = collect($keywords)
            ->filter(fn (string $keyword) => Str::length($keyword) <= SubmissionSearchIndex::SEARCH_KEYWORD_MAX_LENGTH);

        // Quit if there's no keywords
        if (!$keywords->count()) {
            return;
        }

        $chunkedUnmappedKeywords = $keywords
            // Skip mapped keywords
            ->diff(array_keys($keywordMap))
            // Chunk by 1000
            ->chunk(1000);

        $chunkedUnmappedKeywords->map(function (Collection $keywords) use (&$keywordMap) {
            $missingKeywords = collect();
            // Update the map with the existing IDs. Due to the database collation, very similar keywords might end up with the same ID
            foreach ($this->getKeywordIdMap($keywords) as $keyword => $id) {
                if ($id) {
                    $keywordMap[$keyword] = $id;
                } else {
                    $missingKeywords->push($keyword);
                }
            }

            // Batch insert keywords that don't exist using the "ignore" feature to deal with collation issues (e.g. attempt to insert "a" and "ã" at the same time might fail)
            // This isn't executed first just to avoid "burning" IDs due to existing keywords
            DB::table('submission_search_keyword_list')->insertOrIgnore(
                $missingKeywords
                    ->map(fn (string $keyword) => ['keyword_text' => $keyword])
                    ->toArray()
            );

            // Grab the the map with the new IDs
            foreach ($this->getKeywordIdMap($missingKeywords) as $keyword => $id) {
                $keywordMap[$keyword] = $id;
            }
        });

        // Get the current position
        $position = DB::table('submission_search_object_keywords')
            ->where('object_id', $objectId)
            ->max('pos') ?? -1;

        $keywords
            // Skip missed keywords (probably not needed, present for correctness)
            ->filter(fn (string $keyword) => isset($keywordMap[$keyword]))
            // Convert to batch insert format
            ->map(function (string $keyword) use (&$position, $objectId, $keywordMap) {
                return [
                    'object_id' => $objectId,
                    'keyword_id' => $keywordMap[$keyword],
                    'pos' => ++$position
                ];
            })
            // Chunk by 1000
            ->chunk(1000)
            // Batch insert
            ->map(fn (Collection $data) => DB::table('submission_search_object_keywords')->insert($data->toArray()));
    }

    /**
     * Clear the search index, either completely or by context ID.
     */
    public function clearIndex(?int $contextId = null)
    {
        if ($contextId === null) {
            DB::table('submission_search_objects')->delete();
            DB::table('submission_search_keyword_list')->delete();
        } else {
            // If a context is specified, just delete submission_search_objects for the journal's submissions
            // and allow submission_search_object_keywords entries to delete by cascade. Do not clean out
            // submission_search_keyword_list as entries may be used in other contexts.
            DB::statement('DELETE FROM submission_search_objects WHERE submission_id IN (SELECT submission_id FROM submissions WHERE context_id = ?)', [$contextId]);
        }
    }

    /**
     * Retrieves a keyword => ID map for the given keywords
     *
     * @param Collection<int,string>
     *
     * @return Collection<string,int>
     */
    private function getKeywordIdMap(Collection $keywords): Collection
    {
        if (!$keywords->count()) {
            return collect();
        }

        // Generates a temporary keyword table (sequence of "SELECT ? AS keyword UNION ALL SELECT ?...")
        return DB::table(
            DB::raw('(SELECT ? AS keyword' . str_repeat(' UNION ALL SELECT ?', $keywords->count() - 1) . ') AS tmp')
        )
            ->setBindings($keywords->toArray(), 'from')
            ->leftJoin('submission_search_keyword_list AS sskl', 'sskl.keyword_text', '=', 'tmp.keyword')
            ->pluck('sskl.keyword_id', 'tmp.keyword');
    }
}
