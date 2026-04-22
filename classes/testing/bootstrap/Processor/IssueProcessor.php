<?php

/**
 * @file classes/testing/bootstrap/Processor/IssueProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueProcessor
 *
 * @brief Creates issues for one journal and optionally marks them published.
 *
 * Issues are OJS-specific; this processor lives in lib/pkp for layout
 * symmetry with the other bootstrap processors but is only exercised by
 * OJS's BootstrapController.
 */

namespace PKP\testing\bootstrap\Processor;

use APP\facades\Repo;
use PKP\core\Core;

class IssueProcessor
{
    /**
     * @param int $contextId
     * @param array $issueSpecs [{volume, number, year, published?, title?, description?, showTitle?}]
     * @return array Numerically-indexed list of issue metadata fragments matching the spec order.
     */
    public function run(int $contextId, array $issueSpecs): array
    {
        $results = [];
        foreach ($issueSpecs as $spec) {
            $issue = Repo::issue()->newDataObject([
                'journalId' => $contextId,
                'volume' => $spec['volume'] ?? null,
                'number' => $spec['number'] ?? null,
                'year' => $spec['year'] ?? null,
                'title' => $spec['title'] ?? [],
                'description' => $spec['description'] ?? [],
                'published' => 0,
                'showVolume' => $spec['showVolume'] ?? 1,
                'showNumber' => $spec['showNumber'] ?? 1,
                'showYear' => $spec['showYear'] ?? 1,
                'showTitle' => $spec['showTitle'] ?? 0,
            ]);
            $issueId = Repo::issue()->add($issue);

            if (!empty($spec['published'])) {
                $issue = Repo::issue()->get($issueId);
                $issue->setPublished(1);
                $issue->setDatePublished(Core::getCurrentDate());
                Repo::issue()->edit($issue, []);
                Repo::issue()->updateCurrent($contextId, $issue);
            }

            $results[] = [
                'id' => $issueId,
                'volume' => $spec['volume'] ?? null,
                'number' => $spec['number'] ?? null,
                'year' => $spec['year'] ?? null,
                'published' => !empty($spec['published']),
            ];
        }
        return $results;
    }
}
