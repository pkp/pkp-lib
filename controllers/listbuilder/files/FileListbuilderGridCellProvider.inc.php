<?php

/**
 * @file controllers/listbuilder/files/FileListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileListbuilderGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Base class for a cell provider that can retrieve labels from arrays
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class FileListbuilderGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function FileListbuilderGridCellProvider() {
		parent::GridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * This implementation assumes a simple data element array that
	 * has column ids as keys.
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$file = $row->getData();
		$columnId = $column->getId();
		assert(is_a($file, 'SubmissionFile') && !empty($columnId));
		switch ( $columnId ) {
			case 'name':
				return array('labelKey' => $file->getFileId(), 'label' => $file->getFileLabel());
		}
		// we got an unexpected column
		assert(false);
	}
}

?>
