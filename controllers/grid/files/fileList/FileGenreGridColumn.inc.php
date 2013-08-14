<?php
/**
 * @file controllers/grid/files/fileList/FileGenreGridColumn.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileGenreGridColumn
 * @ingroup controllers_grid_files_fileList
 *
 * @brief Implements a file name column.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');

class FileGenreGridColumn extends GridColumn {

	/**
	 * Constructor
	 */
	function FileGenreGridColumn() {
		import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');
		$cellProvider = new ColumnBasedGridCellProvider();
		parent::GridColumn('type', 'common.element', null, 'controllers/grid/gridCell.tpl', $cellProvider);
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
	function getTemplateVarsFromRow($row) {
		// Retrieve the submission file.
		$submissionFileData =& $row->getData();
		assert(isset($submissionFileData['submissionFile']));
		$submissionFile =& $submissionFileData['submissionFile']; /* @var $submissionFile SubmissionFile */
		assert(is_a($submissionFile, 'SubmissionFile'));

		// Retrieve the genre label for the submission file.
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genre = $genreDao->getById($submissionFile->getGenreId());
		if (!is_a($genre, 'Genre')) { return array('label' => null); }
		return array('label' => $genre->getLocalizedName());
	}
}

?>
