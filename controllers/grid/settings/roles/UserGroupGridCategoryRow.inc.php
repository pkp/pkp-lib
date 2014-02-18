<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridCategoryRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridCategoryRow
 * @ingroup controllers_grid_settings
 *
 * @brief UserGroup grid category row definition
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

class UserGroupGridCategoryRow extends GridCategoryRow {

	/**
	 * Constructor
	 */
	function UserGroupGridCategoryRow() {
		parent::GridCategoryRow();
	}

	//
	// Overridden methods from GridCategoryRow
	//

	/**
	 * Category rows only have one cell and one label.  This is it.
	 * return string
	 */
	function getCategoryLabel() {
		$data =& $this->getData();
		return __($data['name']);
	}
}
?>
