<?php

/**
 * @file api/v1/_submissions/BackendSubmissionsController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BackendSubmissionsController
 *
 * @ingroup api_v1__submissions
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace APP\API\v1\_submissions;

use APP\submission\Collector;

class BackendSubmissionsController extends \PKP\API\v1\_submissions\PKPBackendSubmissionsController
{
    /** @copydoc PKPSubmissionHandler::getSubmissionCollector() */
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $collector = parent::getSubmissionCollector($queryParams);

        if (isset($queryParams['sectionIds'])) {
            $collector->filterBySectionIds(
                array_map(intval(...), paramToArray($queryParams['sectionIds']))
            );
        }

        return $collector;
    }
}
