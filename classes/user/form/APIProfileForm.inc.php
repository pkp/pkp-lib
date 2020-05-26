<?php

/**
 * @file classes/user/form/APIProfileForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
		$this->setData('apiKeyEnabled', (bool) $user->getData('apiKeyEnabled'));
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
		$secret = Config::getVar('security', 'api_key_secret', '');
		if ($secret === '') {
			$notificationManager = new NotificationManager();
			$notificationManager->createTrivialNotification(
				$user->getId(), NOTIFICATION_TYPE_WARNING, array(
					'contents' => __('user.apiKey.secretRequired'),
			));
		} elseif ($user->getData('apiKey')) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'apiKey' => JWT::encode($user->getData('apiKey'), $secret, 'HS256'),
			));
		}
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$user = $request->getUser();

		$apiKeyEnabled = (bool) $this->getData('apiKeyEnabled');
		$user->setData('apiKeyEnabled', $apiKeyEnabled);

		// remove api key if exists
		if (!$apiKeyEnabled) {
			$user->setData('apiKeyEnabled', null);
		}

		// generate api key
		if ($apiKeyEnabled && !is_null($this->getData('generateApiKey'))) {
			$secret = Config::getVar('security', 'api_key_secret', '');
			if ($secret) {
				$user->setData('apiKey', sha1(time()));
			}
		}

		parent::execute(...$functionArgs);
	}
}
