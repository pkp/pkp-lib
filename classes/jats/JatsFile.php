<?php

/**
 * @file classes/jats/JatsFile.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JatsFile
 *
 * @ingroup jats
 *
 * @brief JatsFile file class.
 */

namespace PKP\jats;

use APP\core\Application;
use APP\facades\Repo;
use PKP\jats\exceptions\UnableToCreateJATSContentException;
use PKP\submissionFile\exceptions\UnableToCreateFileContentException;
use PKP\submissionFile\SubmissionFile;

class JatsFile
{
    public ?string $loadingContentError = null;
    public ?string $jatsContent = null;
    public bool $isDefaultContent = true;
    public array $props = [];
    public int $revisionCount = 0;

    public function __construct(
        public int $publicationId,
        public ?int $submissionId = null,
        public ?SubmissionFile $submissionFile = null,
        public array $genres = []
    ) {
        try {
            if ($submissionFile) {
                $this->isDefaultContent = false;

                $this->jatsContent = Repo::submissionFile()
                    ->getSubmissionFileContent($submissionFile);

                // Count all JATS files for this publication
                $collector = Repo::submissionFile()
                    ->getCollector()
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_JATS])
                    ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId]);

                if ($submissionId) {
                    $collector = $collector->filterBySubmissionIds([$submissionId]);
                }

                $this->revisionCount = $collector->getCount();
            } else {
                $this->jatsContent = Repo::jats()
                    ->createDefaultJatsContent($publicationId, $submissionId);
            }
        } catch (UnableToCreateFileContentException | UnableToCreateJATSContentException $e) {
            $this->loadingContentError = $e->getMessage();
        }
    }
}
