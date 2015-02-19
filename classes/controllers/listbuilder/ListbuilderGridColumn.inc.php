<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderGridColumn.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderGridColumn
 * @ingroup controllers_listbuilder
 *
 * @brief Represents a column within a listbuilder.
 */


import('lib.pkp.classes.controllers.grid.GridColumn');

class ListbuilderGridColumn extends GridColumn {
	/**
	 * Constructor
	 */
	function ListbuilderGridColumn($listbuilder, $id = '', $title = null, $titleTranslated = null,
			$template = null, $cellProvider = null, $flags = array()) {

		// Set this here so that callers using later optional parameters don't need to
		// duplicate it.
		if ($template === null) $template = 'controllers/listbuilder/listbuilderGridCell.tpl';

		// Make the listbuilder's source type available to the cell template as a flag
		$flags['sourceType'] = $listbuilder->getSourceType();
		parent::GridColumn($id, $title, $titleTranslated, $template, $cellProvider, $flags);
	}
}

?>
