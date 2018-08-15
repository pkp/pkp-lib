<?php

/**
 * @file controllers/grid/users/reviewer/AuthorReviewerGridRow.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerGridRow
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Reviewer grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class AuthorReviewerGridRow extends GridRow {

	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);
	}
}

