<?php
/**
 * @file classes/modal/TabbedModal.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TabbedModal
 * @ingroup modal
 *
 * @brief A modal that contains a tabbed modal retrieved via AJAX.
 */


import('lib.pkp.classes.modal.AjaxModal');

class TabbedModal extends AjaxModal {
	/**
	 * Constructor
	 * @param $url string
	 * @param $title string (optional)
	 * @param $titleIcon string (optional)
	 * @param $canClose boolean (optional)
	 */
	function TabbedModal($url, $title = null, $titleIcon = null, $canClose = true) {
		parent::AjaxModal($url, $title, $titleIcon, $canClose);
	}


	//
	// Overridden public methods
	//
	/**
	 * @see CancellableModal::getJSHandler()
	 */
	function getJSHandler() {
		return '$.pkp.controllers.modal.TabbedModalHandler';
	}
}

?>
