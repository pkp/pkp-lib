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

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use OpenSearch\Client;
use PKP\config\Config;
use PKP\search\parsers\SearchFileParser;
use PKP\submissionFile\SubmissionFile;

class OpenSearchEngine extends ScoutEngine
{
    public const MINIMUM_DATA_LENGTH = 4096;

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
        $hosts = Config::getVar('search', 'opensearch_hosts', null)
            ?? throw new \Exception('The opensearch_hosts configuration is missing. Review your config.inc.php file for details.');

        $username = Config::getVar('search', 'opensearch_username', null);
        $password = Config::getVar('search', 'opensearch_password', null);
        if ($username === null || $password === null) {
            throw new \Exception('The opensearch username and/or password is missing. Review your config.inc.php file for details.');
        }

        return (new \OpenSearch\ClientBuilder())
            ->setHosts(json_decode($hosts, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR))
            ->setBasicAuthentication($username, $password)
            ->setSSLVerification((bool) Config::getVar('search', 'opensearch_ssl_verification', true))
            ->build();
    }

    public function update($models)
    {
        // Delete any related records before re-indexing.
        $this->delete($models);

        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            $publication = $submission->getCurrentPublication();

            // Index all galleys
            $submissionFiles = Repo::submissionFile()
                ->getCollector()
                ->filterByAssoc(Application::ASSOC_TYPE_REPRESENTATION, [$publication->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_PROOF])
                ->getMany();

            $bodies = [];
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
                    error_log("Error parsing submission file {$submissionFile->getId()}: {$e}");
                } finally {
                    $parser->close();
                }
            }

            // Index all authors
            $authors = [];
            foreach ($publication->getData('authors') as $author) {
                foreach ($author->getFullNames() as $locale => $fullName) {
                    $authors[$locale] = ($authors[$locale] ?? '') . $fullName . ' ';
                }
            }

            $client->create([
                'index' => $this->getIndexName(),
                'id' => $submission->getId(),
                'body' => [
                    'titles' => $publication->getFullTitles(),
                    'abstracts' => $publication->getData('abstract'),
                    'bodies' => $bodies,
                    'authors' => $authors,
                    'contextId' => $submission->getData('contextId'),
                    'datePublished' => $publication->getData('datePublished'),
                    'sectionId' => $publication->getData('sectionId'),
                    'categoryId' => $publication->getData('categoryIds'),
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

    protected function buildQuery(Builder $builder): array
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
        $sectionIds = $categoryIds = null;
        foreach ($builder->whereIns as $field => $list) {
            $$field = match($field) {
                'sectionIds', 'categoryIds' => (array) $list,
            };
        };

        // Handle options
        foreach ($builder->options as $option => $value) {
            switch ($option) {
                default: throw new \Exception("Unsupported options {$option}!");
            }
        };

        return [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $builder->query,
                                'fields' => ['titles.*', 'abstracts.*, bodies.*, authors.*'],
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
        ];
    }

    public function search(Builder $builder)
    {
        $client = $this->getClient();
        $results = $client->search($this->buildQuery($builder));
        return [
            'results' => array_map(fn ($result) => $result['_id'], $results['hits']['hits']),
            'total' => $results['hits']['total']['value'],
        ];
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $query = $this->buildQuery($builder);
        $query['from'] = ($page - 1) * $perPage;
        $query['size'] = $perPage;

        $client = $this->getClient();
        $results = $client->search($query);
        return new LengthAwarePaginator(
            array_map(fn ($result) => $result['_id'], $results['hits']['hits']),
            $results['hits']['total']['value'],
            $perPage,
            $page
        );
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
