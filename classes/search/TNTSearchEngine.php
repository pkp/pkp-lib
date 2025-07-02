<?php

/**
 * @file classes/search/TNTSearchEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TNTSearchEngine
 *
 * @brief Laravel Scout driver for TNTSearch
 */

namespace PKP\search;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use PKP\config\Config;
use PKP\core\VirtualArrayIterator;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchEngine extends ScoutEngine
{
    protected TNTSearch $tnt;

    public function __construct()
    {
        $tnt = new TNTSearch();

        $tnt->loadConfig([ // FIXME all of this
            'engine' => \TeamTNT\TNTSearch\Engines\MysqlEngine::class,
            'stemmer' => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class,
            'tokenizer' => \TeamTNT\TNTSearch\Support\Tokenizer::class,
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'ojs-main',
            'username' => 'ojs-ci',
            'password' => 'ojs-ci',
            'storage' => 'files/tntsearch',
        ]);
        // FIXME $tnt->setDatabaseHandle(app('db')->connection()->getPdo());

        $this->tnt = $tnt;
    }

    protected function getIndexName(): string
    {
        return Config::getVar('search', 'search_index_name', 'submissions') . '.index';
    }

    protected function getStorageArea(): string
    {
        $filesDir = Config::getVar('files', 'files_dir');
        if (empty($filesDir) || !is_dir($filesDir)) {
            throw new \Exception('Invalid files directory for search storage!');
        }
        return $filesDir . DIRECTORY_SEPARATOR . 'tntsearch.index';
    }

    public function update($models)
    {
        $this->tnt->selectIndex($this->getIndexName());
        $index = $this->tnt->getIndex();

        $submissionData = [];
        $models->each(function ($submission) use (&$submissionData) {
            $submissionData[$submission->getId()] = [
                'submission_id' => $submission->getId(),
                'contextId' => $submission->getData('contextId'),
                'currentPublicationId' => $submission->getData('currentPublicationId'),
            ];
        });

        foreach ($submissionData as $data) {
            $currentPublication = Repo::publication()->get($data['currentPublicationId']);
            $locale = $currentPublication->getData('locale');
            unset($data['currentPublicationId']);
            $data = array_merge($data, [
                'title' => $currentPublication->getLocalizedTitle($locale),
                'abstract' => $currentPublication->getData('abstract', $locale),
                'datePublished' => $currentPublication->getData('datePublished'),
                'sectionId' => $currentPublication->getData('sectionId'),
                'categoryId' => $currentPublication->getData('categoryIds'),
            ]);
            error_log(print_r($data, true));
            $index->insert($data);
        }
    }

    public function delete($models)
    {
        $this->tnt->selectIndex($this->getIndexName());

        $models->each(function ($submission) use ($client) {
            $this->tnt->delete($submission->getId());
        });
    }

    public function search(Builder $builder)
    {
        $this->tnt->selectIndex($this->getIndexName());

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
                    $$field = (array) $list;
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

        /*
        $results = $client->search([
            'index' => $this->getIndexName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $builder->query,
                                'fields' => ['title', 'abstract'],
                            ],
                        ],
                        'filter' => [
                            ...$contextId ? [['term' => ['contextId' => $contextId]]] : [],
                            ...($publishedFrom || $publishedTo) ? [['range' => ['datePublished' => ['gte' => $publishedFrom, 'lte' => $publishedTo]]]] : [],
                            ...$sectionIds ? [['terms' => ['sectionId' => $sectionIds]]] : [],
                            ...$categoryIds ? [['terms' => ['categoryId' => $categoryIds]]] : [],
                        ],
                    ],
                ]
            ]
        ]);
        $articleIds = array_map(fn ($result) => $result['_id'], $results['hits']['hits']);*/
        $results = $this->tnt->search($builder->query);
        $articleIds = $results['ids'];

        // Pagination
        if ($rangeInfo && $rangeInfo->isValid()) {
            $page = $rangeInfo->getPage();
            $itemsPerPage = $rangeInfo->getCount();
        } else {
            $page = 1;
            $itemsPerPage = self::SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT;
        }

        $totalResults = $results['hits'];

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

        $articleSearch = new \APP\search\ArticleSearch();
        return new VirtualArrayIterator($articleSearch->formatResults($articleIds), $totalResults, $page, $itemsPerPage);
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
        $indexer = $this->tnt->createIndex($this->getIndexName());
        // FIXME $indexer->setDatabaseHandle(app('db')->connection()->getPdo());
        $indexer->setPrimaryKey('submission_id');
    }

    public function deleteIndex($name)
    {
        $this->tnt->selectIndex($this->getIndexName());
        $this->tnt->engine->flushIndex($this->getIndexName());
    }

    public function flush($model)
    {
        $this->tnt->selectIndex($this->getIndexName());
        $this->tnt->engine->flushIndex($this->getIndexName());
    }
}
