<?php
/**
 * @file controllers/grid/files/fileList/FileGenreGridColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileGenreGridColumn
 * @ingroup controllers_grid_files_fileList
 *
 * @brief Implements a file name column.
 */

namespace PKP\controllers\grid\files\fileList;

use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\db\DAORegistry;

class FileGenreGridColumn extends GridColumn
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $cellProvider = new ColumnBasedGridCellProvider();
        parent::__construct('type', 'common.component', null, null, $cellProvider);
    }


    //
    // Public methods
    //
    /**
     * Method expected by ColumnBasedGridCellProvider
     * to render a cell in this column.
     *
     * @see ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRow($row)
    {
        // Retrieve the submission file.
        $submissionFileData = & $row->getData();
        assert(isset($submissionFileData['submissionFile']));
        $submissionFile = & $submissionFileData['submissionFile']; /** @var SubmissionFile $submissionFile */
        assert(is_a($submissionFile, 'SubmissionFile'));

        // Retrieve the genre label for the submission file.
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genre = $genreDao->getById($submissionFile->getGenreId());

        // If no label exists (e.g. for review attachments)
        if (!$genre) {
            return ['label' => null];
        }

        // Otherwise, the label exists.
        return ['label' => $genre->getLocalizedName()];
    }
}
