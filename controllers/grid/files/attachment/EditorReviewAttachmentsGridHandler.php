<?php

/**
 * @file controllers/grid/files/attachment/EditorReviewAttachmentsGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorReviewAttachmentsGridHandler
 *
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Editor's view of the Review Attachments Grid.
 */

namespace PKP\controllers\grid\files\attachment;

use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\Role;

class EditorReviewAttachmentsGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewerReviewAttachmentGridDataProvider(),
            null,
            FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            [
                'fetchGrid', 'fetchRow'
            ]
        );
    }
}
