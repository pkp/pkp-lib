<?php

/**
 * @file classes/search/SubmissionSearchResult.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief A submission search result.
 */

namespace PKP\search;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use PKP\core\PKPRequest;
use PKP\db\DBResultRange;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

class SubmissionSearchResult
{
    /**
     * Return a Laravel Scout Builder from the current request
     *
     * @hook SubmissionSearchResult::builderFromRequest ['builder' => $builder, 'request' => $request]
     */
    public function builderFromRequest(PKPRequest $request, DBResultRange $rangeInfo): Builder
    {
        $context = $request->getContext();
        $contextId = $context?->getId() ?? (int) $request->getUserVar('searchContext');

        $query = (string) $request->getUserVar('query');
        $dateFrom = $request->getUserDateVar('dateFrom');
        $dateTo = $request->getUserDateVar('dateTo');

        $builder = new Builder($this, $query);
        $builder
            ->where('contextId', $contextId)
            ->where('publishedFrom', $dateFrom)
            ->where('publishedTo', $dateTo)
            ->whereIn('categoryIds', $request->getUserVar('categoryIds'))
            ->whereIn('sectionIds', $request->getUserVar('sectionIds'))
            ->whereIn('keywords', $request->getUserVar('keywords'))
            ->whereIn('subjects', $request->getUserVar('subjects'));

        if ($orderBy = $request->getUserVar('orderBy')) {
            $builder->orderBy($orderBy, $request->getUserVar('orderDir') == 'asc' ? 'asc' : 'desc');
        }

        // Allow hook registrants to adjust the builder before querying
        Hook::run('SubmissionSearchResult::builderFromRequest', ['builder' => $builder, 'request' => $request]);

        return $builder;
    }

    /**
     * @see Laravel\Scout\Searchable.
     */
    public function searchableUsing()
    {
        return app(\Laravel\Scout\EngineManager::class)->engine();
    }

    /**
     * @see Illuminate\Database\Eloquent\HasCollection.
     *
     * @param $models PKPSubmission[]
     */
    public function newCollection(array $models = [])
    {
        $contextCache = [];
        $sectionCache = [];

        return LazyCollection::make(function () use ($models, &$contextCache, &$sectionCache) {
            foreach ($models as $data) {
                $submissionId = is_scalar($data) ? (int) $data : (int) $data->submissionId;

                $submission = Repo::submission()->get($submissionId);
                if (!$submission || $submission->getData('status') != PKPSubmission::STATUS_PUBLISHED) {
                    continue;
                }
                $currentPublication = $submission->getCurrentPublication();

                $contextId = $submission->getData('contextId');
                $context = $contextCache[$contextId] ??= Application::getContextDAO()->getById($contextId);

                $sectionId = $currentPublication->getData('sectionId');
                $section = $sectionId ? ($sectionCache[$sectionId] ??= Repo::section()->get($sectionId)) : null;

                yield [
                    'submission' => $submission,
                    'currentPublication' => $currentPublication,
                    'context' => $context,
                    'section' => $section,
                ];
            }
        });
    }
}
