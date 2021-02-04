<?php
/**
 * @file classes/linkAction/request/AjaxModal.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AjaxModal
 * @ingroup linkAction_request
 *
 * @brief A modal that retrieves its content from via AJAX.
 */


import('lib.pkp.classes.linkAction.request.Modal');

class AjaxModal extends Modal {
	/** @var string The URL to be loaded into the modal. */
	var $_url;

	/**
	 * Constructor
	 * @param $url string The URL of the AJAX resource to load into the modal.
	 * @param $title string (optional) The localized modal title.
	 * @param $titleIcon string (optional) The icon to be used in the modal title bar.
	 * @param $canClose boolean (optional) Whether the modal will have a close button.
	 * @param $closeOnFormSuccessId string (optional) Close the modal when the
	 *  form with this id fires a formSuccess event.
	 * @param $closeCleanVueInstances array (optional) When the modal is closed
	 *  destroy the registered vue instances with these ids
	 */
	function __construct($url, $title = null, $titleIcon = null, $canClose = true,
			$closeOnFormSuccessId = null, $closeCleanVueInstances = []) {
		parent::__construct($title, $titleIcon, $canClose, $closeOnFormSuccessId, $closeCleanVueInstances);

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
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		return array_merge(
			parent::getLocalizedOptions(),
			array(
				'modalHandler' => '$.pkp.controllers.modal.AjaxModalHandler',
				'url' => $this->getUrl(),
			)
		);
	}
}
