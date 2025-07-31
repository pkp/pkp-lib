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
use PKP\submission\PKPSubmission;

class SubmissionSearchResult
{
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
