<?php
/**
 * @file classes/linkAction/request/RemoteActionConfirmationModal.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RemoteActionConfirmationModal
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal with a remote action and ok/cancel buttons.
 */


import('lib.pkp.classes.linkAction.request.ConfirmationModal');

class RemoteActionConfirmationModal extends ConfirmationModal {
	/** @var string A URL to be called when the confirmation button is clicked. */
	var $_remoteAction;

	/**
	 * Constructor
	 * @param $dialogText string The localized text to appear
	 *  in the dialog modal.
	 * @param $title string (optional) The localized modal title.
	 * @param $remoteAction string (optional) A URL to be
	 *  called when the confirmation button is clicked.
	 * @param $titleIcon string (optional) The icon to be used
	 *  in the modal title bar.
	 * @param $okButton string (optional) The localized text to
	 *  appear on the confirmation button.
	 * @param $cancelButton string (optional) The localized text to
	 *  appear on the cancel button.
	 * @param $canClose boolean (optional) Whether the modal will
	 *  have a close button.
	 */
	function RemoteActionConfirmationModal($dialogText, $title = null, $remoteAction = null, $titleIcon = null, $okButton = null, $cancelButton = null, $canClose = true) {
		parent::ConfirmationModal($dialogText, $title, $titleIcon, $okButton, $cancelButton, $canClose);

		$this->_remoteAction = $remoteAction;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the remote action.
	 * @return string
	 */
	function getRemoteAction() {
		return $this->_remoteAction;
	}

	//
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		$parentLocalizedOptions = parent::getLocalizedOptions();
		// override the modalHandler option.
		$parentLocalizedOptions['modalHandler'] = '$.pkp.controllers.modal.RemoteActionConfirmationModalHandler';
		$parentLocalizedOptions['remoteAction'] = $this->getRemoteAction();
		return $parentLocalizedOptions;
	}
}

?>
