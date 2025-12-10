<?php

/**
 * @file classes/bodyText/BodyTextFile.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BodyTextFile
 *
 * @brief BodyTextFile file class.
 */

namespace PKP\bodyText;

use APP\facades\Repo;
use PKP\submissionFile\exceptions\UnableToCreateFileContentException;
use PKP\submissionFile\SubmissionFile;

class BodyTextFile
{
    public ?string $loadingContentError = null;
    public ?string $bodyTextContent = null;
    public bool $isDefaultContent = true;

    public function __construct(
        public int $publicationId,
        public ?SubmissionFile $submissionFile = null,
    ) {
        try {
            if ($submissionFile) {
                $this->isDefaultContent = false;
                $this->bodyTextContent = Repo::submissionFile()
                    ->getSubmissionFileContent($submissionFile);
            } else {
                $this->bodyTextContent = $this->getDefaultContent();
            }
        } catch (UnableToCreateFileContentException $e) {
            $this->loadingContentError = $e->getMessage();
        }
    }

    /**
     * Returns the default empty body text document structure
     */
    protected function getDefaultContent(): string
    {
        return json_encode([
            'type' => 'doc',
            'content' => [],
        ]);
    }
}
