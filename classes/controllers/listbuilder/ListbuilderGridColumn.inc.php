<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderGridColumn.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderGridColumn
 * @ingroup controllers_listbuilder
 *
 * @brief Represents a column within a listbuilder.
 */


import('classes.controllers.grid.GridColumn');

class ListbuilderGridColumn extends GridColumn {
	/**
	 * Constructor
	 */
	function ListbuilderGridColumn($id = '', $title = null, $titleTranslated = null,
			$template = 'controllers/listbuilder/listbuilderGridCell.tpl', $cellProvider = null, $flags = array()) {

		parent::GridColumn($id, $title, $titleTranslated, $template, $cellProvider, $flags);
	}
}

?>
