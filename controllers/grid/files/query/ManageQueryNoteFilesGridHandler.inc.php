<?php

/**
 * @file controllers/grid/files/query/ManageQueryNoteFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageQueryNoteFilesGridHandler
 * @ingroup controllers_grid_files_query
 *
 * @brief Handle the query file selection grid
 */

import('lib.pkp.controllers.grid.files.SelectableSubmissionFileListCategoryGridHandler');

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\core\JSONMessage;
use PKP\security\Role;

class ManageQueryNoteFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.query.QueryNoteFilesCategoryGridDataProvider');
        $request = Application::get()->getRequest();
        $stageId = $request->getUservar('stageId'); // authorized by data provider.
        parent::__construct(
            new QueryNoteFilesCategoryGridDataProvider(),
            $stageId,
            FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
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
                'updateQueryNoteFiles'
            ]
        );

        // Set the grid title.
        $this->setTitle('submission.queryNoteFiles');
    }


    //
    // Override methods from SelectableSubmissionFileListCategoryGridHandler
    //
    /**
     * @copydoc GridHandler::isDataElementInCategorySelected()
     */
    public function isDataElementInCategorySelected($categoryDataId, &$gridDataElement)
    {
        $submissionFile = $gridDataElement['submissionFile'];

        // Check for special cases when the file needs to be unselected.
        $dataProvider = $this->getDataProvider();
        if ($dataProvider->getFileStage() != $submissionFile->getFileStage()) {
            return false;
        }

        // Passed the checks above. If it's part of the current query, mark selected.
        $query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
        $headNote = $query->getHeadNote();
        return ($submissionFile->getData('assocType') == ASSOC_TYPE_NOTE && $submissionFile->getData('assocId') == $headNote->getId());
    }

    //
    // Public handler methods
    //
    /**
     * Save 'manage query files' form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateQueryNoteFiles($args, $request)
    {
        $submission = $this->getSubmission();
        $query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);

        import('lib.pkp.controllers.grid.files.query.form.ManageQueryNoteFilesForm');
        $manageQueryNoteFilesForm = new ManageQueryNoteFilesForm($submission->getId(), $query->getId(), $request->getUserVar('noteId'));
        $manageQueryNoteFilesForm->readInputData();

        if ($manageQueryNoteFilesForm->validate()) {
            $manageQueryNoteFilesForm->execute(
                $this->getGridCategoryDataElements($request, $this->getStageId())
            );

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }
}
