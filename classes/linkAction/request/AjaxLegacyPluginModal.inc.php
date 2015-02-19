<?php
/**
 * @file classes/linkAction/request/AjaxLegacyPluginModal.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxLegacyPluginModal
 * @ingroup linkAction_request
 *
 * @brief An ajax modal to be used in plugins management. This is part of a
 * temporary solution, while we don't modernize the UI of the plugins. The
 * functionalities implemented here are not necessary anywhere else.
 * DON'T USE this handler if you are not showing legacy plugins management content.
 * FIXME After modernizing the UI of the plugins, remove this class.
 */


import('lib.pkp.classes.linkAction.request.AjaxModal');

class AjaxLegacyPluginModal extends AjaxModal {
	/**
	 * Constructor
	 * @param $url string The URL of the AJAX resource to load into the modal.
	 * @param $title string (optional) The localized modal title.
	 * @param $titleIcon string (optional) The icon to be used in the modal title bar.
	 * @param $canClose boolean (optional) Whether the modal will have a close button.
	 */
	function AjaxLegacyPluginModal($url, $title = null, $titleIcon = null, $canClose = true) {
		parent::AjaxModal($url, $title, $titleIcon, $canClose);
	}


	//
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		return array_merge(parent::getLocalizedOptions(), array(
				'modalHandler' => '$.pkp.controllers.modal.AjaxLegacyPluginModalHandler',
				'url' => $this->getUrl()));
	}
}

?>
