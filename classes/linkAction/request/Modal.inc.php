<?php
/**
 * @file classes/linkAction/request/Modal.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Modal
 * @ingroup linkAction_request
 *
 * @brief Abstract base class for all modal dialogs.
 */


import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class Modal extends LinkActionRequest {
	/** @var string A translation key defining the title of the modal. */
	var $_title;

	/** @var string The icon to be displayed in the title bar. */
	var $_titleIcon;

	/** @var boolean Whether the modal has a close icon in the title bar. */
	var $_canClose;

	/**
	 * Constructor
	 * @param $title string (optional) The modal title.
	 * @param $titleIcon string (optional) The icon to be used in the modal title bar.
	 * @param $canClose boolean (optional) Whether the modal will have a close button.
	 */
	function Modal($title = null, $titleIcon = null, $canClose = true) {
		parent::LinkActionRequest();
		$this->_title = $title;
		$this->_titleIcon = $titleIcon;
		$this->_canClose = $canClose;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the modal title translation key.
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Get the title bar icon.
	 * @return string
	 */
	function getTitleIcon() {
		return $this->_titleIcon;
	}

	/**
	 * Whether the modal has a close icon in the title bar.
	 * @return boolean
	 */
	function getCanClose() {
		return $this->_canClose;
	}


	//
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getJSLinkActionRequest()
	 */
	function getJSLinkActionRequest() {
		return '$.pkp.classes.linkAction.ModalRequest';
	}

	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		return array(
			'title' => Locale::translate($this->getTitle()),
			'titleIcon' => $this->getTitleIcon(),
			'canClose' => ($this->getCanClose() ? '1' : '0')
		);
	}
}

?>
