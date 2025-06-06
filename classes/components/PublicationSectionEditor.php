<?php

/**
 * @file classes/components/PublicationSectionEditor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationSectionEditor
 *
 * @ingroup classes_components
 *
 * @brief A Panel component for editing a publication's body text.
 */

namespace PKP\components;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\submissionFile\SubmissionFile;

class PublicationSectionEditor
{
    public function __construct(
        public string $id,
        public string $title,
        public Submission $submission,
        public Context $context,
        public bool $canEditPublication = false,
        public Publication $publication
    ) {
    }

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        $config = [];

        $config = array_merge(
            $config,
            [
                'title' => $this->title,
                'id' => $this->id,
                'canEditPublication' => $this->canEditPublication,
                'publicationApiUrlFormat' => $this->getPublicationUrlFormat(),
                'fileStage' => SubmissionFile::SUBMISSION_FILE_BODY_TEXT,
            ]
        );

        return $config;
    }

    /**
     * Get an example of the url to a publication's API endpoint,
     * with a placeholder instead of the publication id, eg:
     *
     * http://example.org/api/v1/submissions/1/publications/{$publicationId}
     */
    protected function getPublicationUrlFormat(): string
    {
        return Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $this->context->getPath(),
            'submissions/' . $this->submission->getId() . '/publications/{$publicationId}/bodyText'
        );
    }
}
