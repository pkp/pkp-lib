<?php

/**
 * @file classes/plugins/PKPViewableFilePlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ViewableFilePlugin
 * @ingroup plugins
 *
 * @brief Abstract class for article galley plugins
 */

import('lib.pkp.classes.plugins.GenericPlugin');

abstract class PKPViewableFilePlugin extends GenericPlugin {

	/**
	 * Get the filename of the template. (Default behavior may
	 * be overridden through some combination of this function and the
	 * displayArticleGalley function.)
	 * Returning null from this function results in an empty display.
	 *
	 * @return string
	 */
	function getTemplateFilename() {
		return 'display.tpl';
	}
}

?>
