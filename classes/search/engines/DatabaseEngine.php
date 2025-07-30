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
use APP\facades\Repo;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder as SearchBuilder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use PKP\config\Config;
use PKP\search\parsers\SearchFileParser;
use PKP\submissionFile\SubmissionFile;

class DatabaseEngine extends ScoutEngine
{
    public const MINIMUM_DATA_LENGTH = 4096;

    protected function getIndexName(): string
    {
        return Config::getVar('search', 'search_index_name', 'submissions');
    }

    protected function getTableName(): string
    {
        return $this->getIndexName() . '_fulltext';
    }

    public function update($models)
    {
        $models->each(function ($submission) {
            Repo::publication()->getCollector()->filterBySubmissionIds([$submission->getId()])->getMany()->each(function ($publication) use ($submission) {
                $titles = (array) $publication->getFullTitles();
                $abstracts = (array) $publication->getData('abstract');
                $bodies = [];
                $authors = [];

                // Index all galleys
                $submissionFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(Application::ASSOC_TYPE_REPRESENTATION, [$publication->getId()])
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_PROOF])
                    ->getMany();

                foreach ($submissionFiles as $submissionFile) {
                    $galley = Application::getRepresentationDAO()->getById($submissionFile->getData('assocId'));
                    $parser = SearchFileParser::fromFile($submissionFile);
                    if (!$parser) {
                        continue;
                    }
                    try {
                        $parser->open();
                        do {
                            for ($buffer = ''; ($chunk = $parser->read()) !== false && strlen($buffer .= $chunk) < static::MINIMUM_DATA_LENGTH;);
                            if (strlen($buffer)) {
                                $bodies[$galley->getLocale()] = ($bodies[$galley->getLocale()] ?? '') . $buffer;
                            }
                        } while ($chunk !== false);
                    } catch (\Throwable $e) {
                        error_log($e);
                    } finally {
                        $parser->close();
                    }
                }

                // Index all authors
                foreach ($publication->getData('authors') as $author) {
                    foreach ($author->getFullNames() as $locale => $fullName) {
                        $authors[$locale] = ($authors[$locale] ?? '') . $fullName . ' ';
                    }
                }

                $locales = array_unique(array_merge(array_keys($titles), array_keys($abstracts), array_keys($bodies), array_keys($authors)));

                foreach ($locales as $locale) {
                    DB::table($this->getTableName())->upsert(
                        [
                            'submission_id' => $submission->getId(),
                            'publication_id' => $publication->getId(),
                            'locale' => $locale,
                            'title' => $titles[$locale] ?? '',
                            'abstract' => $abstracts[$locale] ?? '',
                            'body' => $bodies[$locale] ?? '',
                            'authors' => $authors[$locale] ?? '',
                        ],
                        ['submission_id', 'publication_id', 'locale'],
                        ['title', 'abstract', 'body']
                    );
                }
            });
        });
    }

    public function delete($models)
    {
        $models->each(function ($submission) {
            DB::table($this->getTableName())->where('submission_id', $submission->getId())->delete();
        });
    }

    protected function buildQuery(SearchBuilder $builder): DatabaseBuilder
    {
        // Handle "where" conditions
        $contextId = null;
        $publishedFrom = $publishedTo = null;
        foreach ($builder->wheres as $field => $value) {
            switch ($field) {
                case 'contextId':
                    $$field = (int) $value;
                    break;
                case 'publishedFrom':
                case 'publishedTo':
                    $$field = $value ? new \Carbon\Carbon($value) : null;
                    break;
                default: throw new \Exception("Unsupported field {$field}!");
            }
        };

        // Handle "whereIn" conditions
        $sectionIds = $categoryIds = null;
        foreach ($builder->whereIns as $field => $list) {
            switch ($field) {
                case 'sectionIds':
                case 'categoryIds':
                    $$field = is_null($list) ? null : (array) $list;
                    break;
                default: throw new \Exception("Unsupported field {$field}!");
            }
        };

        // Handle options
        foreach ($builder->options as $option => &$value) {
            switch ($option) {
                default: throw new \Exception("Unsupported options {$option}!");
            }
        };

        return DB::table($this->getTableName() . ' AS ftsearch')
            ->join('submissions AS s', 'ftsearch.submission_id', 's.submission_id')
            ->when($contextId, fn (Builder $q) => $q->where('context_id', $contextId))
            ->when($publishedFrom || $publishedTo || is_array($sectionIds), fn ($q) => $q->whereExists(
                fn ($q) => $q->selectRaw(1)
                    ->from('publications AS p')
                    ->whereColumn('p.submission_id', 's.submission_id')
                    ->where('p.published', 1)
                    ->when($publishedFrom, fn ($q) => $q->where('p.date_published', '>=', $publishedFrom))
                    ->when($publishedTo, fn ($q) => $q->where('p.date_published', '<', $publishedTo))
                    ->when(is_array($sectionIds), fn ($q) => $q->whereIn('p.section_id', $sectionIds))
                    ->when(is_array($categoryIds), fn ($q) => $q->whereIn(
                        'p.publication_id',
                        DB::table('publication_categories')->whereIn('category_id', $categoryIds)->select('publication_id')
                    ))
            ))
            ->whereFullText(['title', 'abstract', 'body', 'authors'], $builder->query)
            ->groupBy('s.submission_id');
    }

    public function search(SearchBuilder $builder): Collection
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
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function lazyMap(SearchBuilder $builder, $results, $model)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
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
        DB::table($this->getTableName())->truncate();
    }
}
