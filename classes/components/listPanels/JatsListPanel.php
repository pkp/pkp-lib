<?php
/**
 * @file classes/components/listPanels/JatsListPanel.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JatsListPanel
 *
 * @ingroup classes_components_list
 *
 * @brief A Panel component for viewing and managing publication's JATS Files
 */

namespace PKP\components\listPanels;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\submissionFile\SubmissionFile;

class JatsListPanel extends ListPanel
{
    public Submission $submission;
    public Publication $publication;
    public Context $context;

    /** Whether the user can edit the current publication */
    public bool $canEditPublication;

    public function __construct(
        string $id,
        string $title,
        Submission $submission,
        Context $context,
        bool $canEditPublication = false,
        Publication $publication
    ) {
        parent::__construct($id, $title);
        $this->submission = $submission;
        $this->context = $context;
        $this->canEditPublication = $canEditPublication;
        $this->publication = $publication;
    }

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        // Remove some props not used in this list panel
        unset($config['description']);
        unset($config['expanded']);
        unset($config['headingLevel']);

        $config = array_merge(
            $config,
            [
                'canEditPublication' => $this->canEditPublication,
                'publicationApiUrlFormat' => $this->getPublicationUrlFormat(),
                'uploadProgressLabel' => __('submission.upload.percentComplete'),
                'fileStage' => SubmissionFile::SUBMISSION_FILE_JATS,
                'i18nConfirmDeleteFileTitle' => __('publication.jats.confirmDeleteFileTitle'),
                'i18nDeleteFileMessage' => __('publication.jats.confirmDeleteFileMessage'),
                'i18nConfirmDeleteFileButton' => __('publication.jats.confirmDeleteFileButton'),
                'i18nLastModifiedAt' => __('publication.jats.lastModified'),
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
