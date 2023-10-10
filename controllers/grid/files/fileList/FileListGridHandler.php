<?php
/**
 * @defgroup controllers_grid_files_fileList File List Grid
 */

/**
 * @file controllers/grid/files/fileList/FileListGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileListGridHandler
 *
 * @ingroup controllers_grid_files_fileList
 *
 * @brief Base grid for simple file lists. This grid shows the file type in
 *  addition to the file name.
 */

namespace PKP\controllers\grid\files\fileList;

use PKP\controllers\grid\files\SubmissionFilesGridHandler;

class FileListGridHandler extends SubmissionFilesGridHandler
{
    //
    // Extended methods from SubmissionFilesGridHandler.
    //
    /**
     * @copydoc SubmissionFilesGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Add the "manage files" action if required.
        $capabilities = $this->getCapabilities();
        if ($capabilities->canManage()) {
            $dataProvider = $this->getDataProvider();
            $this->addAction($dataProvider->getSelectAction($request));
        }

        // The file list grid layout has an additional file genre column.
        $this->addColumn(new FileGenreGridColumn());
    }
}
