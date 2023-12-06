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

use APP\facades\Repo;
use PKP\submissionFile\SubmissionFile;

class JatsFile
{
    public string $jatsContent;
    public bool $isDefaultContent = true;
    public array $props = [];

    public function __construct(
        public int $publicationId,
        public ?int $submissionId = null, 
        public ?SubmissionFile $submissionFile = null, 
        public array $genres = []
    ) 
    {
        if ($submissionFile) {
            $this->jatsContent = Repo::submissionFile()
                ->getSubmissionFileContent($submissionFile, $genres);
            
            $this->isDefaultContent = false;
        } else {
            $this->jatsContent = Repo::jats()
                ->createDefaultJatsContent($publicationId, $submissionId);
        }
    }
}
