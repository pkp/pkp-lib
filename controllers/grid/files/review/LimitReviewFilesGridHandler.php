<?php
/**
 * @file controllers/grid/files/review/LimitReviewFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LimitReviewFilesGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display a selectable list of review files for the round to editors.
 *   Items in this list can be selected or deselected to give a specific subset
 *   to a particular reviewer.
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use PKP\controllers\grid\files\fileList\SelectableFileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\ReviewStageAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class LimitReviewFilesGridHandler extends SelectableFileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewGridDataProvider($fileStage),
            null,
            FilesGridCapabilities::FILE_GRID_VIEW_NOTES
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid information.
        $this->setTitle('editor.submissionReview.restrictFiles');
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        if ($reviewAssignmentId = $request->getUserVar('reviewAssignmentId')) {
            // If a review assignment ID is specified, preload the
            // checkboxes with the currently selected files. To do
            // this, we'll need the review assignment in the context.
            // Add the required policies:

            // 1) Review stage access policy (fetches submission in context)
            $this->addPolicy(new ReviewStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId')));

            // 2) Review assignment
            $this->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId', ['fetchGrid', 'fetchRow']));
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        if ($reviewAssignment) {
            $submissionFile = $gridDataElement['submissionFile'];
            // A review assignment was specified in the request; preset the
            // checkboxes to the currently available set of files.
            $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
            return $reviewFilesDao->check($reviewAssignment->getId(), $submissionFile->getId());
        } else {
            // No review assignment specified; default to all files available.
            return true;
        }
    }
}
