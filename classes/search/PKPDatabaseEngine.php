<?php

/**
 * @file classes/search/PKPDatabaseEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDatabaseEngine
 *
 * @brief Laravel Scout driver for the PKP built-in inverted search index
 */

namespace PKP\search;

use APP\core\Application;
use Laravel\Scout\Builder;
use PKP\jobs\submissions\RemoveSubmissionFromSearchIndexJob;
use PKP\jobs\submissions\UpdateSubmissionSearchJob;

class PKPDatabaseEngine extends \Laravel\Scout\Engines\Engine
{
    public function update($models)
    {
        $models->each(fn ($model) => dispatch(new UpdateSubmissionSearchJob($model->getId())));
    }

    public function delete($models)
    {
        $models->each(fn ($model) => dispatch(new RemoveSubmissionFromSearchIndexJob($model->getId())));
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

        $articleSearch = new \APP\search\ArticleSearch();
        $application = \APP\core\Application::get();
        return $articleSearch->retrieveResults(
            request: $application->getRequest(),
            context: $application->getContextDao()->getById($contextId),
            keywords: ['query' => $builder->query],
            publishedFrom: $publishedFrom,
            publishedTo: $publishedTo,
            categoryIds: $categoryIds,
            sectionIds: $sectionIds,
            rangeInfo: $rangeInfo
        );
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
        Application::get()->getSubmissionSearchDAO()->clearIndex();
    }
}
