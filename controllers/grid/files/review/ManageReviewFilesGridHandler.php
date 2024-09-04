<?php
/**
 * @file controllers/grid/files/review/ManageReviewFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageReviewFilesGridHandler
 *
 * @ingroup controllers_grid_files_review
 *
 * @brief Handle the editor review file selection grid (selects which files to send to review or to next review round)
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\review\form\ManageReviewFilesForm;
use PKP\controllers\grid\files\SelectableSubmissionFileListCategoryGridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class ManageReviewFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler
{
    /** @var array */
    public $_selectionArgs;

    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewCategoryGridDataProvider($fileStage),
            null,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_VIEW_NOTES
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchCategory', 'fetchRow', 'updateReviewFiles']
        );

        // Set the grid title.
        $this->setTitle('reviewer.submission.reviewFiles');
    }


    //
    // Public handler methods
    //
    /**
     * Save 'manage review files' form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateReviewFiles($args, $request)
    {
        $submission = $this->getSubmission();

        $manageReviewFilesForm = new ManageReviewFilesForm($submission->getId(), $this->getRequestArg('stageId'), $this->getRequestArg('reviewRoundId'));
        $manageReviewFilesForm->readInputData();

        if ($manageReviewFilesForm->validate()) {
            $dataProvider = $this->getDataProvider();
            $manageReviewFilesForm->execute(
                $this->getGridCategoryDataElements($request, $this->getStageId())
            );

            $this->setupTemplate($request);
            $user = $request->getUser();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.updatedReviewFiles')]);

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }


    //
    // Extended methods from CategoryGridHandler.
    //
    /**
     * @copydoc CategoryGridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
        return array_merge(['stageId' => $stageId], parent::getRequestArgs());
    }
}
