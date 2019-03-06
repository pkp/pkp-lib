<?php

/**
 * @file classes/user/form/APIProfileForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user's API key settings.
 */

use \Firebase\JWT\JWT;

import('lib.pkp.classes.user.form.BaseProfileForm');

class APIProfileForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $user User
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
	 * @return string JSON-encoded form contents.
	 * @see BaseProfileForm::fetch
	 */
	public function fetch($request, $template = null, $display = false) {
		$user = $request->getUser();
		$apiKey = $user->getSetting('apiKey');
		$secret = Config::getVar('security', 'api_key_secret', '');
		$jwt = '';
		if ($secret !== '') {
			$jwt = JWT::encode(json_encode($apiKey), $secret, 'HS256');
		} else {
			$notificationManager = new NotificationManager();
			$notificationManager->createTrivialNotification(
				$user->getId(), NOTIFICATION_TYPE_WARNING, array(
					'contents' => __('user.apiKey.secretRequired'),
			));
		}
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'apiKeyEnabled' => $user->getSetting('apiKeyEnabled'),
			'apiKey' => $jwt,
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save user's API key settings form.
	 */
	function execute() {
		$request = Application::get()->getRequest();
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
