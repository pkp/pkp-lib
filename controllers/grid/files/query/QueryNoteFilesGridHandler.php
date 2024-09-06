<?php

/**
 * @file controllers/grid/files/query/QueryNoteFilesGridHandler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNoteFilesGridHandler
 *
 * @ingroup controllers_grid_files_query
 *
 * @brief Handle query files that are associated with a query
 * The participants of a query have access to the files in this grid.
 */

namespace PKP\controllers\grid\files\query;

use APP\core\Application;
use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\query\form\ManageQueryNoteFilesForm;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\security\authorization\QueryAccessPolicy;
use PKP\security\Role;

class QueryNoteFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // import app-specific grid data provider for access policies.
        $request = Application::get()->getRequest();
        $stageId = $request->getUservar('stageId'); // authorized in authorize() method.
        parent::__construct(
            new QueryNoteFilesGridDataProvider($request->getUserVar('noteId')),
            $stageId,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'selectFiles']
        );

        // Set grid title.
        $this->setTitle('submission.queries.attachedFiles');
    }

    /**
     * @copydoc SubmissionFilesGridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy
        $this->_stageId = (int)$stageId;

        // Get the stage access policy
        $queryAccessPolicy = new QueryAccessPolicy($request, $args, $roleAssignments, $stageId);
        $this->addPolicy($queryAccessPolicy);
        $result = parent::authorize($request, $args, $roleAssignments);

        if (0 != count(array_intersect(
            $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES),
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]
        ))) {
            $this->getCapabilities()->setCanManage(true);
        }

        return $result;
    }


    //
    // Public handler methods
    //
    /**
     * Show the form to allow the user to select files from previous stages
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function selectFiles($args, $request)
    {
        $submission = $this->getSubmission();
        $query = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_QUERY);

        $manageQueryNoteFilesForm = new ManageQueryNoteFilesForm($submission->getId(), $query->id, $request->getUserVar('noteId'), $this->getRequestArgs());
        $manageQueryNoteFilesForm->initData();
        return new JSONMessage(true, $manageQueryNoteFilesForm->fetch($request));
    }
}
