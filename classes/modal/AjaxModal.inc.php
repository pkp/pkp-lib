<?php
/**
 * @file classes/modal/AjaxModal.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxModal
 * @ingroup modal
 *
 * @brief A modal that retrieves its content from via AJAX.
 */


import('lib.pkp.classes.modal.Modal');

class AjaxModal extends Modal {
	/** @var string The URL to be loaded into the modal. */
	var $_url;

	/**
	 * Constructor
	 * @param $url string
	 * @param $title string (optional)
	 * @param $titleIcon string (optional)
	 * @param $canClose boolean (optional)
	 */
	function AjaxModal($url, $title = null, $titleIcon = null, $canClose = true) {
		parent::Modal($title, $titleIcon, $canClose);

		$this->_url = $url;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the URL to be loaded into the modal.
	 * @return string
	 */
	function getUrl() {
		return $this->_url;
	}


	//
	// Overridden public methods
	//
	/**
	 * @see CancellableModal::getJSHandler()
	 */
	function getJSHandler() {
		return '$.pkp.controllers.modal.AjaxModalHandler';
	}

	/**
	 * @see Modal::getLocalizedModalOptions()
	 */
	function getLocalizedModalOptions() {
		return array_merge(parent::getLocalizedModalOptions(), array(
				'url' => $this->getUrl()));
	}
}

?>
