<?php

/**
 * @file classes/search/OpenSearchEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenSearchEngine
 *
 * @brief Laravel Scout driver for OpenSearch
 */

namespace PKP\search;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use OpenSearch\Client;
use PKP\config\Config;
use PKP\core\VirtualArrayIterator;

class OpenSearchEngine extends ScoutEngine
{
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
            ->setBasicAuthentication($username, $password) // For testing only. Don't store credentials in code.
            ->setSSLVerification(false) // For testing only. Use certificate for validation
            ->build();
    }

    public function update($models)
    {
        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            $currentPublication = $submission->getCurrentPublication();
            $locale = $currentPublication->getData('locale');
            $client->create([
                'index' => 'submissions',
                'id' => $submission->getId(),
                'body' => [
                    'title' => $currentPublication->getLocalizedTitle($locale),
                ],
            ]);
        });
    }

    public function delete($models)
    {
        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            $client->delete([
                'index' => 'submissions',
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
                case 'publishedFrom':
                case 'publishedTo':
                    $$field = (int) $value;
                    break;
                default: throw new \Exception("Unsupported field {$field}!");
            }
        };

        // Handle "whereIn" conditions
        $sectionIds = $categoryIds = null;
        foreach ($builder->whereIns as $field => $list) {
            switch ($field) {
                case 'sectionIds': $sectionIds = $list;
                    break;
                case 'categoryIds': $categoryIds = $list;
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
            'index' => 'submissions',
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $builder->query,
                        'fields' => ['title']
                    ]
                ]
            ]
        ]);
        $articleIds = array_map(fn ($result) => $result['_id'], $results['hits']['hits']);

        // Pagination
        if ($rangeInfo && $rangeInfo->isValid()) {
            $page = $rangeInfo->getPage();
            $itemsPerPage = $rangeInfo->getCount();
        } else {
            $page = 1;
            $itemsPerPage = self::SUBMISSION_SEARCH_DEFAULT_RESULT_LIMIT;
        }

        $totalResults = $results['hits']['total']['value'];

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
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function deleteIndex($name)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function flush($model)
    {
        $client = $this->getClient();
        $client->indices()->delete([
            'index' => 'submissions',
        ]);
    }
}
