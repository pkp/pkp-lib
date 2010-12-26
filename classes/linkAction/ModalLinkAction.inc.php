<?php
/**
 * @file classes/linkAction/ModalLinkAction.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModalLinkAction
 * @ingroup linkAction
 *
 * @brief Class defining an action that opens a modal.
 */


import('lib.pkp.classes.linkAction.LinkAction');

class ModalLinkAction extends LinkAction {
	/** @var Modal The modal to be triggered by this action. */
	var $_modal;

	/**
	 * Constructor
	 * @param $id string
	 * @param $modal Modal
	 * @param $title string (optional)
	 * @param $image string (optional)
	 */
	function ModalLinkAction($id, $modal, $title = null, $image = null) {
		parent::LinkAction($id, $title, $image);
		$this->_modal = $modal;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the modal object.
	 * @return Modal
	 */
	function getModal() {
		return $this->_modal;
	}


	//
	// Overridden protected methods from LinkAction
	//
	/**
	 * @see LinkAction::getTemplate()
	 */
	function getTemplate() {
		return 'linkAction/modalLinkAction.tpl';
	}
}

?>
