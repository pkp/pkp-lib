<?php
/**
 * @file classes/linkAction/request/WizardModal.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WizardModal
 * @ingroup linkAction_request
 *
 * @brief A modal that contains a wizard retrieved via AJAX.
 */


import('lib.pkp.classes.linkAction.request.AjaxModal');

class WizardModal extends AjaxModal {
	/**
	 * Constructor
	 * @param $url string
	 * @param $title string (optional)
	 * @param $titleIcon string (optional)
	 * @param $canClose boolean (optional)
	 */
	function WizardModal($url, $title = null, $titleIcon = null, $canClose = true) {
		parent::AjaxModal($url, $title, $titleIcon, $canClose);
	}


	//
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		$options = parent::getLocalizedOptions();
		$options['modalHandler'] = '$.pkp.controllers.modal.WizardModalHandler';
		return $options;
	}
}

?>
