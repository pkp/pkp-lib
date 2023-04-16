<?php

/**
 * @file controllers/grid/files/copyedit/ManageCopyeditFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageCopyeditFilesGridHandler
 *
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Handle the copyedited file selection grid
 */

namespace PKP\controllers\grid\files\copyedit;

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\controllers\grid\files\copyedit\form\ManageCopyeditFilesForm;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\SelectableSubmissionFileListCategoryGridHandler;
use PKP\controllers\grid\files\SubmissionFilesCategoryGridDataProvider;
use PKP\core\JSONMessage;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class ManageCopyeditFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            new SubmissionFilesCategoryGridDataProvider(SubmissionFile::SUBMISSION_FILE_COPYEDIT),
            WORKFLOW_STAGE_ID_EDITING,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT
            ],
            [
                'fetchGrid', 'fetchCategory', 'fetchRow',
                'addFile',
                'downloadFile',
                'deleteFile',
                'updateCopyeditFiles'
            ]
        );

        // Set the grid title.
        $this->setTitle('submission.copyedited');
    }


    //
    // Public handler methods
    //
    /**
     * Save 'manage copyedited files' form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateCopyeditFiles($args, $request)
    {
        $submission = $this->getSubmission();

        $manageCopyeditFilesForm = new ManageCopyeditFilesForm($submission->getId());
        $manageCopyeditFilesForm->readInputData();

        if ($manageCopyeditFilesForm->validate()) {
            $manageCopyeditFilesForm->execute(
                $this->getGridCategoryDataElements($request, $this->getStageId())
            );

            if ($submission->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
                $submission->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->updateNotification(
                    $request,
                    [
                        PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                        PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                    ],
                    null,
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submission->getId()
                );
            }

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }
}
