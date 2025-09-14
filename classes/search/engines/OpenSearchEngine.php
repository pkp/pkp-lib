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
use PKP\controlledVocab\ControlledVocab;
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
                    'keyword' => array_merge(...array_values(array_filter(
                        Repo::controlledVocab()->getBySymbolic(
                            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
                            Application::ASSOC_TYPE_PUBLICATION,
                            $submission->getCurrentPublication()->getId(),
                            [Locale::getLocale(), $submission->getData('locale'), Locale::getPrimaryLocale()]
                        )
                    ))),
                    'subject' => array_merge(...array_values(array_filter(
                        Repo::controlledVocab()->getBySymbolic(
                            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
                            Application::ASSOC_TYPE_PUBLICATION,
                            $submission->getCurrentPublication()->getId(),
                            [Locale::getLocale(), $submission->getData('locale'), Locale::getPrimaryLocale()]
                        )
                    )))
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
            $list = (array) $list;
            if (!empty($list)) {
                switch ($field) {
                    case 'sectionIds':
                        $filter[] = ['terms' => ['sectionId' => $list]];
                        break;
                    case 'categoryIds':
                        $filter[] = ['terms' => ['categoryId' => $list]];
                        break;
                    case 'keywords':
                        $filter[] = ['terms' => ['keyword.keyword' => $list]];
                        break;
                    case 'subjects':
                        $filter[] = ['terms' => ['subject.keyword' => $list]];
                        break;
                    default: continue 2;
                }
            };
            unset($builder->whereIns[$field]);
        };

        // Allow hook registrants to process and consume additional query builder elements
        Hook::run('OpenSearchEngine::buildQuery', ['query' => &$query, 'filter' => &$filter, 'builder' => $builder, 'originalBuilder' => $originalBuilder]);

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

        return $query;
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
        try {
            $this->deleteIndex($this->getIndexName());
        } catch (\OpenSearch\Common\Exceptions\Missing404Exception $e) {
            // Don't worry about missing indexes.
        }
    }
}
