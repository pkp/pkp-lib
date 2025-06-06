<?php

/**
 * @file classes/bodyText/BodyTextFile.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BodyTextFile
 *
 * @brief BodyTextFile file class.
 */

namespace PKP\bodyText;

use APP\facades\Repo;
use PKP\bodyText\exceptions\UnableToCreateBodyTextContentException;
use PKP\submissionFile\exceptions\UnableToCreateFileContentException;
use PKP\submissionFile\SubmissionFile;

class BodyTextFile
{
    public ?string $loadingContentError = null;
    public ?string $bodyTextContent = null;
    public bool $isDefaultContent = true;
    public array $props = [];

    public function __construct(
        public int $publicationId,
        public ?int $submissionId = null,
        public ?SubmissionFile $submissionFile = null,
        public array $genres = []
    ) {
        try {
            if ($submissionFile) {
                $this->isDefaultContent = false;

                $this->bodyTextContent = Repo::submissionFile()
                    ->getSubmissionFileContent($submissionFile);
            } else {
                $this->bodyTextContent = '';
            }
        } catch (UnableToCreateFileContentException | UnableToCreateBodyTextContentException $e) {
            $this->loadingContentError = $e->getMessage();
        }

    }
}
