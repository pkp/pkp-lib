<?php

/**
 * @file controllers/grid/files/dependent/DependentFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DependentFilesGridHandler
 *
 * @ingroup controllers_grid_files_dependent
 *
 * @brief Handle dependent files that are associated with a submissions's display
 *  (galleys or production formats, for example).
 * The submission author and all context/editor roles have access to this grid.
 */

namespace PKP\controllers\grid\files\dependent;

use APP\core\Application;
use APP\submission\Submission;
use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;

class DependentFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // import app-specific grid data provider for access policies.
        $request = Application::get()->getRequest();
        $submissionFileId = $request->getUserVar('submissionFileId'); // authorized in authorize() method.

        parent::__construct(
            new DependentFilesGridDataProvider($submissionFileId),
            $request->getUserVar('stageId')
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow']
        );

        $this->setTitle('submission.submit.dependentFiles');
    }

    /**
     * Get the authorized publication.
     *
     * @return \Publication
     */
    public function getPublication()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
    }


    /**
     * @copydoc SubmissionFilesGridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY, (int) $args['submissionFileId']));

        $publicationId = $request->getUserVar('publicationId'); // authorized in authorize() method.
        if ($publicationId) {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        return array_merge(
            parent::getRequestArgs(),
            ['submissionFileId' => $submissionFile->getId()]
        );
    }

    public function initialize($request, $args = null)
    {
        $capabilities = FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT;

        $publication = $this->getPublication();

        if ($publication) {
            if ($publication->getData('status') == Submission::STATUS_PUBLISHED) {
                $capabilities = FilesGridCapabilities::FILE_GRID_VIEW_NOTES;
            }
        }

        $this->setCapabilities(new FilesGridCapabilities($capabilities));

        parent::initialize($request, $args);
    }
}
