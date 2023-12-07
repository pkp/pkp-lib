<?php
/**
 * @file classes/components/PublicationSectionJats.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationSectionJats
 *
 * @ingroup classes_components
 *
 * @brief A Panel component for viewing and managing publication's JATS Files
 */

namespace PKP\components;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\submissionFile\SubmissionFile;

class PublicationSectionJats
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
                'fileStage' => SubmissionFile::SUBMISSION_FILE_JATS,
                'downloadDefaultJatsFileName' => Repo::jats()->getDefaultJatsFileName($this->publication->getId()),
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
            'submissions/' . $this->submission->getId() . '/publications/{$publicationId}/jats'
        );
    }
}
