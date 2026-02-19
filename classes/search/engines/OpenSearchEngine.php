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
use PKP\facades\Locale;
use PKP\plugins\Hook;
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
            if (!$publication) {
                return;
            }

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
            $json = [
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
                    'keyword' => collect($publication->getData('keywords'))
                        ->map(fn ($items) => collect($items)->pluck('name')->all())
                        ->all(),
                    'subject' => collect($publication->getData('subjects'))
                        ->map(fn ($items) => collect($items)->pluck('name')->all())
                        ->all(),
                ]
            ];
            // Give hooks a chance to alter the record before indexing
            if (Hook::run('OpenSearchEngine::update', ['json' => &$json, 'submission' => $submission]) !== Hook::ABORT) {
                $client->create($json);
            }
        });
    }

    public function delete($models)
    {
        $client = $this->getClient();
        $models->each(function ($submission) use ($client) {
            try {
                $client->delete([
                    'index' => $this->getIndexName(),
                    'id' => $submission->getId(),
                ]);
            } catch (\OpenSearch\Common\Exceptions\Missing404Exception $e) {
                // Ignore data that's not in the index.
            }
        });
    }

    protected function buildQuery(Builder $builder): array
    {
        // Ensure we don't disturb reuse of the builder, which is consumed here
        $originalBuilder = $builder;
        $builder = clone $builder;

        $filter = [];
        $sort = [];
        $query = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => [
                    'bool' => [
                        ...($builder->query ? ['must' => [
                            'multi_match' => [
                                'query' => $builder->query,
                                'fields' => ['titles.*', 'abstracts.*, bodies.*, authors.*'],
                            ],
                        ]] : []),
                        'filter' => &$filter,
                    ],
                ],
                'sort' => &$sort,
            ],
        ];

        // Handle "where" conditions
        $publishedFrom = $publishedTo = null;
        foreach ($builder->wheres as $field => $value) {
            switch ($field) {
                case 'contextId':
                    if ($value) {
                        $filter[] = ['term' => ['contextId' => (int) $value]];
                    }
                    break;
                case 'publishedFrom':
                case 'publishedTo':
                    if ($value) {
                        $$field = new \Carbon\Carbon($value);
                    }
                    break;
                default: continue 2;
            }
            unset($builder->wheres[$field]);
        }
        if ($publishedFrom || $publishedTo) {
            $filter[] = ['range' => ['datePublished' => ['gte' => $publishedFrom, 'lte' => $publishedTo]]];
        }

        // Handle "whereIn" conditions
        foreach ($builder->whereIns as $field => $list) {
            $list = array_filter((array) $list);
            if (!empty($list)) {
                switch ($field) {
                    case 'sectionIds':
                        $filter[] = ['terms' => ['sectionId' => $list]];
                        break;
                    case 'categoryIds':
                        $filter[] = ['terms' => ['categoryId' => $list]];
                        break;
                    case 'keywords':
                        $filter[] = ['terms' => ['keyword.' . Locale::getLocale() . '.keyword' => $list]];
                        break;
                    case 'subjects':
                        $filter[] = ['terms' => ['subject.' . Locale::getLocale() . '.keyword' => $list]];
                        break;
                    default: continue 2;
                }
            };
            unset($builder->whereIns[$field]);
        };

        // Handle ordering
        foreach ($builder->orders as $index => $order) {
            switch ($order['column']) {
                case 'datePublished':
                    $sort[] = (object) [
                        $order['column'] => (object) ['order' => $order['direction']]
                    ];
                    unset($builder->orders[$index]);
                    break;
                case 'title':
                    $sort[] = (object) [
                        'titles.' . Locale::getLocale() => (object) [
                            'order' => $order['direction'],
                        ]
                    ];
                    unset($builder->orders[$index]);
                    break;
            }
        }

        // Allow hook registrants to process and consume additional query builder elements
        Hook::run('OpenSearchEngine::buildQuery', ['query' => &$query, 'filter' => &$filter, 'sort' => &$sort, 'builder' => $builder, 'originalBuilder' => $originalBuilder]);

        // Ensure that the query builder was completely consumed (there were no unsupported details provided)
        if (!empty($builder->whereIns)) {
            throw new \Exception('Unsupported "whereIn" query: ' . json_encode($builder->whereIns));
        }
        if (!empty($builder->wheres)) {
            throw new \Exception('Unsupported "where" query: ' . json_encode($builder->wheres));
        }
        if (!empty($builder->options)) {
            throw new \Exception('Unsupported option: ' . json_encode($builder->options));
        }
        if (!empty($builder->orders)) {
            throw new \Exception('Unsupported order-by: ' . json_encode($builder->orders));
        }

        return $query;
    }

    public function search(Builder $builder): array
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
        // Determine all metadata locales supported by the site
        $metadataLocales = [];
        $contexts = Application::get()->getContextDAO()->getAll();
        while ($context = $contexts->next()) {
            $metadataLocales = [...$metadataLocales, ...$context->getSupportedSubmissionMetadataLocales()];
        }
        $metadataLocales = array_unique($metadataLocales);

        $typicalKeywordClause = fn ($fielddata = false) => [
            'properties' => [
                ...array_map(fn ($e) => [
                    'type' => 'text',
                    'fielddata' => $fielddata,
                    'fields' => [
                        'keyword' => [
                            'type' => 'keyword',
                        ],
                    ],
                ], array_flip($metadataLocales)),
            ],
        ];

        $mapping = [
            'index' => $this->getIndexName(),
            'body' => [
                'mappings' => [
                    'properties' => [
                        'abstracts' => $typicalKeywordClause(),
                        'titles' => $typicalKeywordClause(true),
                        'authors' => $typicalKeywordClause(),
                        'categoryId' => ['type' => 'long'],
                        'contextId' => ['type' => 'long'],
                        'datePublished' => ['type' => 'date'],
                        'keyword' => $typicalKeywordClause(),
                        'subject' => $typicalKeywordClause(),
                        'sectionId' => ['type' => 'long'],
                    ],
                ],
            ],
        ];

        if (Hook::run('OpenSearchEngine::createIndex', ['mapping' => &$mapping]) !== Hook::ABORT) {
            $this->getClient()->indices()->create($mapping);
        }
    }

    public function deleteIndex($name)
    {
        $client = $this->getClient();
        $client->indices()->delete([
            'index' => $this->getIndexName(),
        ]);
    }

    public function flush($model)
    {
        $client = $this->getClient();
        try {
            $client->indices()->flush(['index' => $this->getIndexName()]);
        } catch (\Throwable $t) {
            // Ignore errors
        }
    }
}
