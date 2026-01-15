<?php

/**
 * @file classes/search/engines/DatabaseEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Laravel Scout driver for database fulltext search
 */

namespace PKP\search\engines;

use APP\core\Application;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder as SearchBuilder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use PKP\config\Config;
use PKP\controlledVocab\ControlledVocab;
use PKP\facades\Locale;
use PKP\publication\PKPPublication;
use PKP\submission\PKPSubmission;

class DatabaseEngine extends ScoutEngine
{
    public const MINIMUM_DATA_LENGTH = 4096;

    protected function getIndexName(): string
    {
        return Config::getVar('search', 'search_index_name', 'submissions');
    }

    public function update($models)
    {
        // Clear out any related entries before updating the index.
        $this->delete($models);

        $models->each(function ($submission) {
            dispatch(new \PKP\jobs\submissions\UpdateSubmissionSearchJob($submission->getId()));
        });
    }

    public function delete($models)
    {
        DB::table('submissions_fulltext')
            ->whereIn('submission_id', $models->map(fn (PKPSubmission $s) => $s->getId()))
            ->delete();
    }

    protected function buildQuery(SearchBuilder $builder): DatabaseBuilder
    {
        // Handle "where" conditions
        $contextId = $publishedFrom = $publishedTo = null;
        foreach ($builder->wheres as $field => $value) {
            $$field = match($field) {
                'contextId' => (int) $value,
                'publishedFrom', 'publishedTo' => $value ? new \Carbon\Carbon($value) : null,
            };
        };

        // Handle "whereIn" conditions
        $sectionIds = $categoryIds = $keywords = $subjects = null;
        foreach ($builder->whereIns as $field => $list) {
            $$field = match($field) {
                'sectionIds', 'categoryIds', 'keywords', 'subjects' => is_null($list) ? null : (array) $list,
            };
        };

        // Handle options
        foreach ($builder->options as $option => &$value) {
            switch ($option) {
                default: throw new \Exception("Unsupported options {$option}!");
            }
        };

        // Handle ordering
        switch (count((array) $builder->orders)) {
            case 0: $orderBy = $orderDirection = null;
                break;
            case 1:
                $order = $builder->orders[0];
                switch ($order['column']) {
                    case 'datePublished':
                    case 'title':
                        $orderBy = $order['column'];
                        $orderDirection = $order['direction'];
                        break;
                    case 'featured': // OMP only
                        if ($applicationName = Application::get()->getName() != 'omp') {
                            throw new \Exception("Features not supported in {$applicationName}!");
                        }
                        $orderBy = 'feature';
                        $orderDirection = $order['direction'];
                        break;
                    default: throw new \Exception('Order-by "' . $order['column'] . '" not supported by DatabaseEngine!');
                }
                break;
            default: throw new \Exception('Only a single order condition is accepted by DatabaseEngine!');
        }

        return DB::table('submissions_fulltext AS ft')
            ->join('submissions AS s', 'ft.submission_id', 's.submission_id')
            ->when($contextId, fn (DatabaseBuilder $q) => $q->where('context_id', $contextId))
            ->whereIn('s.submission_id', DB::table('publications')->where('status', PKPPublication::STATUS_PUBLISHED)->select('submission_id'))
            ->when($publishedFrom || $publishedTo || is_array($sectionIds) || is_array($categoryIds) || is_array($keywords) || is_array($subjects), fn ($q) => $q->whereExists(
                fn ($q) => $q->selectRaw(1)
                    ->from('publications AS p')
                    ->whereColumn('p.submission_id', 's.submission_id')
                    ->where('p.status', PKPPublication::STATUS_PUBLISHED)
                    ->when($publishedFrom, fn ($q) => $q->whereDate('p.date_published', '>=', $publishedFrom))
                    ->when($publishedTo, fn ($q) => $q->whereDate('p.date_published', '<', $publishedTo))
                    ->when(is_array($sectionIds), fn ($q) => $q->whereIn('p.section_id', $sectionIds))
                    ->when(is_array($categoryIds), fn ($q) => $q->whereIn(
                        'p.publication_id',
                        DB::table('publication_categories')->whereIn('category_id', $categoryIds)->select('publication_id')
                    ))
                    ->when(is_array($keywords), function ($q) use ($keywords) {
                        foreach ($keywords as $keyword) {
                            $q->whereExists(function (DatabaseBuilder $query) use ($keyword) {
                                $query->select(DB::raw(1))
                                    ->from('controlled_vocabs AS cv')
                                    ->join('controlled_vocab_entries AS cve', 'cv.controlled_vocab_id', 'cve.controlled_vocab_id')
                                    ->join('controlled_vocab_entry_settings AS cves', 'cve.controlled_vocab_entry_id', 'cves.controlled_vocab_entry_id')
                                    ->where('cv.assoc_type', Application::ASSOC_TYPE_PUBLICATION)
                                    ->whereColumn('cv.assoc_id', 'p.publication_id')
                                    ->where('cv.symbolic', ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD)
                                    ->where('cves.setting_value', $keyword)
                                    ->whereIn('cves.setting_name', ['name', 'identifier']);
                            });
                        }
                    })
                    ->when(is_array($subjects), function ($q) use ($subjects) {
                        foreach ($subjects as $subject) {
                            $q->whereExists(function (DatabaseBuilder $query) use ($subject) {
                                $query->select(DB::raw(1))
                                    ->from('controlled_vocabs AS cv')
                                    ->join('controlled_vocab_entries AS cve', 'cv.controlled_vocab_id', 'cve.controlled_vocab_id')
                                    ->join('controlled_vocab_entry_settings AS cves', 'cve.controlled_vocab_entry_id', 'cves.controlled_vocab_entry_id')
                                    ->where('cv.assoc_type', ASSOC_TYPE_PUBLICATION)
                                    ->whereColumn('cv.assoc_id', 'p.publication_id')
                                    ->where('cv.symbolic', ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT)
                                    ->where('cves.setting_value', $subject)
                                    ->whereIn('cves.setting_name', ['name', 'identifier']);
                            });
                        }
                    })
            ))
            ->when($orderBy == 'datePublished', function ($q) use ($orderBy, $orderDirection) {
                $q->leftJoin('publications AS cp', 's.current_publication_id', 'cp.publication_id')
                    ->orderBy('cp.date_published', $orderDirection);
            })
            ->when($orderBy == 'feature', function ($q) {
                $q->leftJoin('features as f', 's.submission_id', 'f.submission_id')
                    ->orderByRaw('COALESCE(MAX(f.seq), 999999)'); // MySQL and PostgreSQL don't agree on ORDER BY with NULLs
            })
            ->when($orderBy == 'title', function ($q) use ($orderBy, $orderDirection) {
                $q->leftJoin(
                    'publication_settings AS title_current',
                    fn (JoinClause $j) =>
                    $j->on('s.current_publication_id', '=', 'title_current.publication_id')
                        ->where('title_current.setting_name', '=', 'title')
                        ->where('title_current.locale', '=', Locale::getLocale())
                )
                    ->orderBy($q->raw('MIN(title_current.setting_value)'), $orderDirection);
            })
            ->when($builder->query, fn ($q) => $q->whereFullText(['title', 'abstract', 'body', 'authors'], $builder->query))
            ->groupBy('s.submission_id');
    }

    public function search(SearchBuilder $builder): array
    {
        $query = $this->buildQuery($builder);
        $results = $query->pluck('s.submission_id');
        return [
            'results' => $results,
            'total' => $results->count()
        ];
    }

    public function paginate(SearchBuilder $builder, $perPage, $page): LengthAwarePaginator
    {
        return $this->buildQuery($builder)
            ->select(['s.submission_id AS submissionId'])
            ->paginate($perPage, ['submissionId'], 'submissions', $page);
    }

    public function mapIds($results)
    {
        throw new \BadMethodCallException('Unimplemented function.');
    }

    public function lazyMap(SearchBuilder $builder, $results, $model)
    {
        throw new \BadMethodCallException('Unimplemented function.');
    }

    public function map(SearchBuilder $builder, $results, $model)
    {
        return $results;
    }

    public function getTotalCount($results)
    {
        if ($results instanceof LengthAwarePaginator) {
            return $results->total();
        }
        return $results['total'];
    }

    public function createIndex($name, array $options = [])
    {
    }

    public function deleteIndex($name)
    {
    }

    public function flush($model)
    {
        DB::table('submissions_fulltext')->truncate();
    }
}
