<?php

/**
 * @file controllers/grid/plugins/PluginGalleryGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridRow
 * @ingroup controllers_grid_settings_plugins
 *
 * @brief Plugin gallery grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class PluginGalleryGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function PluginGalleryGridRow() {
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Is this a new row or an existing row?
		$element = $this->getData();
		assert(is_a($element, 'GalleryPlugin'));

		$rowId = $this->getId();

		// Only add row actions if this is an existing row
		$router = $request->getRouter();

	}
}

?>
