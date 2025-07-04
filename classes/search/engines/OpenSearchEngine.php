<?php

/**
 * @file classes/search/engines/OpenSearchEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenSearchEngine
 *
 * @brief Laravel Scout driver for OpenSearch
 */

namespace PKP\search\engines;

use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use OpenSearch\Client;
use PKP\config\Config;

class OpenSearchEngine extends ScoutEngine
{
    /**
     * Get the name of the index to be used for OpenSearch indexing.
     */
    protected function getIndexName(): string
    {
        return Config::getVar('search', 'search_index_name', 'submissions');
    }

    /**
     * Get an OpenSearch client.
     */
    protected function getClient(): Client
    {
        $hosts = Config::getVar('search', 'opensearch_hosts', null);
        if ($hosts === null) {
            throw new \Exception('The opensearch_hosts configuration is missing. Review your config.inc.php file for details.');
        }

        $username = Config::getVar('search', 'opensearch_username', null);
        $password = Config::getVar('search', 'opensearch_password', null);
        if ($username === null || $password === null) {
            throw new \Exception('The opensearch username and/or password is missing. Review your config.inc.php file for details.');
        }

        return (new \OpenSearch\ClientBuilder())
            ->setHosts(json_decode($hosts, flags: JSON_OBJECT_AS_ARRAY))
            ->setBasicAuthentication($username, $password)
            ->setSSLVerification((bool) Config::getVar('search', 'opensearch_ssl_verification', true))
            ->build();
    }

    public function update($models)
    {
        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            $currentPublication = $submission->getCurrentPublication();
            $locale = $currentPublication->getData('locale');
            $client->create([
                'index' => $this->getIndexName(),
                'id' => $submission->getId(),
                'body' => [
                    'title' => $currentPublication->getLocalizedTitle($locale),
                    'abstract' => $currentPublication->getData('abstract', $locale),
                    'contextId' => $submission->getData('contextId'),
                    'datePublished' => $currentPublication->getData('datePublished'),
                    'sectionId' => $currentPublication->getData('sectionId'),
                    'categoryId' => $currentPublication->getData('categoryIds'),
                ],
            ]);
        });
    }

    public function delete($models)
    {
        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            $client->delete([
                'index' => $this->getIndexName(),
                'id' => $submission->getId(),
            ]);
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

        $client = $this->getClient();
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
        return [
            'results' => array_map(fn ($result) => $result['_id'], $results['hits']['hits']),
            'total' => $results['hits']['total']['value'],
        ];
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $results = $this->search($builder);
        return new LengthAwarePaginator($results['results'], $results['total'], $perPage, $page);
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
        if ($results instanceof LengthAwarePaginator) {
            return $results->total();
        }
        return $results['total'];
    }

    public function createIndex($name, array $options = [])
    {
        $client = $this->getClient();
        $client->indices()->create([
            'index' => $name,
            'body' => [
                'settings' => [
                ]
            ]
        ]);
    }

    public function deleteIndex($name)
    {
        $client = $this->getClient();
        $client->indices()->delete([
            'index' => $name,
        ]);
    }

    public function flush($model)
    {
        $this->deleteIndex($this->getIndexName());
    }
}
