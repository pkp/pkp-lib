<?php
/**
 * @file controllers/grid/files/query/QueryNoteFilesCategoryGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNoteFilesCategoryGridDataProvider
 *
 * @ingroup controllers_grid_files_query
 *
 * @brief Provide access to query file data for category grids.
 */

namespace PKP\controllers\grid\files\query;

use APP\core\Application;
use PKP\controllers\grid\files\SubmissionFilesCategoryGridDataProvider;
use PKP\submissionFile\SubmissionFile;

class QueryNoteFilesCategoryGridDataProvider extends SubmissionFilesCategoryGridDataProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(SubmissionFile::SUBMISSION_FILE_QUERY);
    }


    //
    // Overriden public methods from SubmissionFilesCategoryGridDataProvider
    //
    /**
     * @copydoc SubmissionFilesCategoryGridDataProvider::initGridDataProvider()
     *
     * @param null|mixed $initParams
     */
    public function initGridDataProvider($fileStage, $initParams = null)
    {
        $request = Application::get()->getRequest();
        return new QueryNoteFilesGridDataProvider($request->getUserVar('noteId'));
    }
}
