<?php
/**
 * @file controllers/grid/files/submissionDocuments/SubmissionDocumentsFilesGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDocumentsFilesGridDataProvider
 *
 * @ingroup controllers_grid_files_submissionDocuments
 *
 * @brief The base data provider for the submission documents library files grid.
 */

namespace PKP\controllers\grid\files\submissionDocuments;

use APP\core\Application;
use APP\submission\Submission;
use PKP\context\LibraryFileDAO;
use PKP\controllers\grid\CategoryGridDataProvider;
use PKP\db\DAORegistry;
use PKP\security\authorization\SubmissionAccessPolicy;

class SubmissionDocumentsFilesGridDataProvider extends CategoryGridDataProvider
{
    /**
     * @copydoc GridDataProvider::getAuthorizationPolicy()
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        return new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId');
    }

    //
    // Getters and Setters
    //

    /**
     * Get the authorized submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();
        return [
            'submissionId' => $submission->getId(),
        ];
    }

    /**
     * @copydoc CategoryGridHandler::loadCategoryData()
     *
     * @param null|mixed $filter
     */
    public function loadCategoryData($request, $fileType, $filter = null)
    {
        // Retrieve all library files for the given submission document category.
        $submission = $this->getSubmission();
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFiles = $libraryFileDao->getBySubmissionId($submission->getId(), $fileType);

        return $libraryFiles->toAssociativeArray();
    }
}
