<?php
/**
 * @file controllers/grid/files/fileList/SelectableFileListGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableFileListGridHandler
 * @ingroup controllers_grid_files_fileList
 *
 * @brief Base grid for selectable file lists. The grid use the SelectableItemFeature
 * to show a check box for each row so that the user can make a selection
 * among grid entries.
 */

namespace PKP\controllers\grid\files\fileList;

use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;

class SelectableFileListGridHandler extends FileListGridHandler
{
    //
    // Overriden methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature()];
    }


    //
    // Implemented methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        $file = $gridDataElement['submissionFile'];
        return $file->getViewable();
    }

    /**
     * @copydoc GridHandler::getSelectName()
     */
    public function getSelectName()
    {
        return 'selectedFiles';
    }
}
