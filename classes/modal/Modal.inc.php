<?php
/**
 * @defgroup modal
 */

/**
 * @file classes/modal/Modal.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Modal
 * @ingroup modal
 *
 * @brief Abstract base class for all modal dialogs.
 */

class Modal {
	/** @var string A translation key defining the title of the modal. */
	var $_title;

	/** @var string The icon to be displayed in the title bar. */
	var $_titleIcon;

	/** @var boolean Whether the modal has a close icon in the title bar. */
	var $_canClose;

	/**
	 * Constructor
	 * @param $title string (optional)
	 * @param $titleIcon string (optional)
	 * @param $canClose boolean (optional)
	 */
	function Modal($title = null, $titleIcon = null, $canClose = true) {
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
	// Public methods
	//
	/**
	 * Return the JavaScript controller that will
	 * handle this modal.
	 * @return string
	 */
	function getJSHandler() {
		// Must be implemented by sub-classes.
		assert(false);
	}

	/**
	 * Return the options to be passed on to the
	 * modal dialog.
	 * @return array An array describing the dialog
	 *  options.
	 */
	function getLocalizedModalOptions() {
		return array(
			'title' => Locale::translate($this->getTitle()),
			'titleIcon' => $this->getTitleIcon(),
			'canClose' => ($this->getCanClose() ? '1' : '0')
		);
	}
}

?>
