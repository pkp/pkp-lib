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
     */
    public function newCollection(array $models = [])
    {
        $issueCache = [];
        $journalCache = [];
        $sectionCache = [];

        return LazyCollection::make(function () use ($models, &$issueCache, &$journalCache, &$sectionCache) {
            foreach ($models as $data) {
                $submissionId = is_scalar($data) ? (int) $data : (int) $data->submissionId;

                $submission = Repo::submission()->get($submissionId);
                if (!$submission) {
                    continue;
                }
                $currentPublication = $submission->getCurrentPublication();

                $issueId = $currentPublication->getData('issueId');
                $issue = $issueId ? $issueCache[$issueId] ?? ($issueCache[$issueId] = Repo::issue()->get($issueId)) : null;

                $contextId = $submission->getData('contextId');
                $journal = $journalCache[$contextId] ?? ($journalCache[$contextId] = Application::getContextDAO()->getById($contextId));

                $sectionId = $currentPublication->getData('sectionId');
                $section = $sectionId ? $sectionCache[$sectionId] ?? ($sectionCache[$sectionId] = Repo::section()->get($sectionId)) : null;

                yield [
                    'publishedSubmission' => $submission = Repo::submission()->get($submissionId),
                    'issue' => $issue,
                    'journal' => $journal,
                    'issueAvailable' => true, // FIXME
                    'section' => $section,
                ];
            }
        });
    }
}
