<?php

/**
 * @file classes/search/DatabaseEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Laravel Scout driver for database fulltext search
 */

namespace PKP\search;

use APP\facades\Repo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use PKP\config\Config;
use PKP\core\VirtualArrayIterator;

class DatabaseEngine extends ScoutEngine
{
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
                $titles = $publication->getFullTitles();
                $abstracts = $publication->getData('abstract');
                $bodies = []; // FIXME

                $locales = array_unique(array_merge(array_keys($titles), array_keys($abstracts), array_keys($bodies)));

                foreach ($locales as $locale) {
                    DB::table($this->getTableName())->upsert(
                        [
                            'submission_id' => $submission->getId(),
                            'locale' => $locale,
                            'title' => $titles[$locale] ?? null,
                            'abstract' => $abstracts[$locale] ?? null,
                            'body' => $bodies[$locale] ?? null,
                        ],
                        ['submission_id', 'locale'],
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

    public function search(Builder $builder)
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
                    $$field = new \Carbon\Carbon($value);
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
        $rangeInfo = null;
        foreach ($builder->options as $option => &$value) {
            switch ($option) {
                case 'rangeInfo': $rangeInfo = $value;
                    break;
                default: throw new \Exception("Unsupported options {$option}!");
            }
        };

        $submissionIds = DB::table($this->getTableName() . ' AS ftsearch')
            ->join('submissions AS s', 'ftsearch.submission_id', '=', 's.submission_id')
            ->when($contextId, fn ($q) => $q->where('context_id', $contextId))
            ->when($publishedFrom || $publishedTo || is_array($sectionIds), fn ($q) => $q->whereExists(
                fn ($q) =>
        $q->select(DB::raw(1))
                ->from('publications AS p')
                ->when($publishedFrom, fn ($q) => $q->where('p.date_published', '>=', $publishedFrom))
                ->when($publishedTo, fn ($q) => $q->where('p.date_published', '<', $publishedTo))
                ->when(is_array($sectionIds), fn ($q) => $q->whereIn('p.section_id', $sectionIds))
                ->when(is_array($categoryIds), fn ($q) => $q->whereIn(
                    'p.publication_id',
                    DB::table('publication_categories')->whereIn('category_id', $categoryIds)->select('publication_id')
                ))
            ))
            ->whereFullText(['title', 'abstract', 'body'], $builder->query)
            ->pluck('s.submission_id')
            ->toArray();

        // Pagination
        if ($rangeInfo && $rangeInfo->isValid()) {
            $page = $rangeInfo->getPage();
            $itemsPerPage = $rangeInfo->getCount();
        } else {
            $page = 1;
            $itemsPerPage = self::SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT;
        }

        $totalResults = count($submissionIds);

        // Use only the results for the specified page.
        $offset = $itemsPerPage * ($page - 1);
        $length = max($totalResults - $offset, 0);
        $length = min($itemsPerPage, $length);

        $articleSearch = new \APP\search\ArticleSearch();
        return new VirtualArrayIterator($articleSearch->formatResults($submissionIds), $totalResults, $page, $itemsPerPage);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function mapIds($results)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function map(Builder $builder, $results, $model)
    {
        return $results;
    }

    public function getTotalCount($results)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function createIndex($name, array $options = [])
    {
        Schema::create($this->getTableName(), function (Blueprint $table) {
            $table->bigInteger($this->getTableName() . '_search_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');

            $table->string('locale', 28);

            $table->text('title')->nullable();
            $table->text('abstract')->nullable();
            $table->text('body')->nullable();

            $table->fulltext(['title', 'abstract', 'body']);
            $table->unique(['submission_id', 'locale']);
        });
    }

    public function deleteIndex($name)
    {
        Schema::drop($this->getTableName());
    }

    public function flush($model)
    {
        DB::table($this->getTableName())->delete();
    }
}
