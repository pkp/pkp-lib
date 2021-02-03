<?php
/**
 * @file classes/linkAction/request/Modal.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Modal
 * @ingroup linkAction_request
 *
 * @brief Abstract base class for all modal dialogs.
 */


define('MODAL_WIDTH_DEFAULT', '710');
define('MODAL_WIDTH_AUTO', 'auto');

import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class Modal extends LinkActionRequest {
	/** @var string The localized title of the modal. */
	var $_title;

	/** @var string The icon to be displayed in the title bar. */
	var $_titleIcon;

	/** @var boolean Whether the modal has a close icon in the title bar. */
	var $_canClose;

	/** @var string The id of a form which should close the modal when completed */
	var $_closeOnFormSuccessId;

	/** @var array The id of any Vue instances that must be destroyed when modal closed */
	var $_closeCleanVueInstances;

	/**
	 * Constructor
	 * @param $title string (optional) The localized modal title.
	 * @param $titleIcon string (optional) The icon to be used in the modal title bar.
	 * @param $canClose boolean (optional) Whether the modal will have a close button.
	 * @param $width int (optional) Override the default width of 'auto'
	 *  for confirmation modals.  Useful for modals that display
	 *  large blocks of text.
	 * @param $closeOnFormSuccessId string (optional) Close the modal when the
	 *  form with this id fires a formSuccess event.
	 * @param $closeCleanVueInstances array (optional) When the modal is closed
	 *  destroy the registered vue instances with these ids
	 */
	function __construct($title = null, $titleIcon = null, $canClose = true,
			$closeOnFormSuccessId = null, $closeCleanVueInstances = []) {
		parent::__construct();
		$this->_title = $title;
		$this->_titleIcon = $titleIcon;
		$this->_canClose = $canClose;
		$this->_closeOnFormSuccessId = $closeOnFormSuccessId;
		$this->_closeCleanVueInstances = $closeCleanVueInstances;
		// @todo this should be customizable via an option
		$this->_closeButtonText = __('common.closePanel');
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the localized title.
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

	/**
	 * Get the text to be displayed on the close button for screen readers
	 */
	function getCloseButtonText() {
		return $this->_closeButtonText;
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
			'title' => $this->getTitle(),
			'titleIcon' => $this->getTitleIcon(),
			'canClose' => ($this->getCanClose() ? '1' : '0'),
			'closeOnFormSuccessId' => $this->_closeOnFormSuccessId,
			'closeCleanVueInstances' => $this->_closeCleanVueInstances,
			'closeButtonText' => $this->getCloseButtonText(),
		);
	}
}
