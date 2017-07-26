<?php

/**
 * @file classes/user/form/APIProfileForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user's API key settings.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');

class APIProfileForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $user PKPUser
	 */
	public function __construct($user) {
		parent::__construct('user/apiProfileForm.tpl', $user);
	}

	/**
	 * @copydoc Form::initData()
	 */
	public function initData() {
		$user = $this->getUser();
		$this->setData('apiKeyEnabled', $user->getSetting('apiKeyEnabled'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		parent::readInputData();
	
		$this->readUserVars(array(
				'apiKeyEnabled', 'generateApiKey',
		));
	}

	/**
	 * Fetch the form to edit user's API key settings.
	 * @param $request PKPRequest
	 * @return string JSON-encoded form contents.
	 */
	public function fetch($request) {
		$user = $request->getUser();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'apiKeyEnabled' => $user->getSetting('apiKeyEnabled'),
			'apiKey' => $user->getSetting('apiKey'),
		));
		return parent::fetch($request);
	}

	/**
	 * Save user's API key settings form.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$user = $request->getUser();

		$apiKeyEnabled = (bool) $this->getData('apiKeyEnabled');
		$user->updateSetting('apiKeyEnabled', $apiKeyEnabled);

		// remove api key if exists
		if (!$apiKeyEnabled) {
			$user->updateSetting('apiKey', NULL);
		}

		// generate api key
		if ($apiKeyEnabled && !is_null($this->getData('generateApiKey'))) {
			$apiKey = sha1(time());
			$user->updateSetting('apiKey', $apiKey);
		}
	}
}