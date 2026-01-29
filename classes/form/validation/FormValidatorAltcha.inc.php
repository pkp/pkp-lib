<?php

/**
 * @file classes/form/validation/FormValidatorAltcha.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorAltcha
 * @ingroup form_validation
 *
 * @brief Form validation check Altcha values.
 */

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Algorithm;
use AltchaOrg\Altcha\ChallengeOptions;

import('lib.pkp.classes.form.validation.FormValidator');

define('ALTCHA_RESPONSE_FIELD', 'altcha');

class FormValidatorAltcha extends FormValidator {
	/** @var string The initiating IP address of the user */
	var $_userIp;

	/**
	 * Constructor.
	 * @param $form Form
	 * @param $userIp string IP address of user request
	 * @param $message string Key of message to display on mismatch
	 */
	function __construct(&$form, $userIp, $message) {
		parent::__construct($form, ALTCHA_RESPONSE_FIELD, FORM_VALIDATOR_REQUIRED_VALUE, $message);
		$this->_userIp = $userIp;
	}

	/**
	 * @see FormValidator::isValid()
	 * Determine whether or not the form meets this ALTCHA constraint.
	 * @return boolean
	 */
	function isValid() {
		$form =& $this->getForm();
		$hmacKey = Config::getVar('captcha', 'altcha_hmackey');

		if (empty($hmacKey)) {
			return false;
		}

		$response = $form->getData(ALTCHA_RESPONSE_FIELD);
		if (empty($response)) {
			$this->_message = 'common.captcha.error.missing-input-response';
			return false;
		}

		if (!empty($this->_userIp) && !filter_var($this->_userIp, FILTER_VALIDATE_IP)) {
			return false;
		}

		if (!Altcha::verifySolution($response, $hmacKey)) {
			$this->_message = 'common.captcha.error.missing-input-response';
			return false;
		}

		return true;
	}

	/**
	 * Validates the ALTCHA response
	 * @param $response string The ALTCHA response
	 * @param $ip string The user IP address (optional)
	 * @return boolean
	 */
	static function validateResponse($response, $ip = null) {
		if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		if (empty($response)) {
			return false;
		}

		$hmacKey = Config::getVar('captcha', 'altcha_hmackey');
		if (empty($hmacKey)) {
			return false;
		}

		return Altcha::verifySolution($response, $hmacKey);
	}

	/**
	 * Add Altcha JavaScript to the template
	 * @param $templateMgr TemplateManager
	 */
	static function addAltchaJavascript($templateMgr) {
		$request = Application::get()->getRequest();
		$altchaPath = $request->getBaseUrl() . '/lib/pkp/js/lib/altcha/altcha.js';

		$altchaHeader = '<script async defer src="' . $altchaPath . '" type="module"></script>';
		$templateMgr->addHeader('altcha', $altchaHeader);
	}

	/**
	 * Insert the Altcha challenge data into the template
	 * @param $templateMgr TemplateManager
	 */
	static function insertFormChallenge($templateMgr) {
		$hmacKey = Config::getVar('captcha', 'altcha_hmackey');
		if (empty($hmacKey)) {
			return;
		}

		// Default maxNumber value for a 3 to 5 seconds average solving time
		$maxNumber = (int) (Config::getVar('captcha', 'altcha_encrypt_number') ?: 10000);

		$options = new ChallengeOptions([
			'algorithm' => Algorithm::SHA256,
			'maxNumber' => $maxNumber,
			'hmacKey' => $hmacKey,
		]);

		$challenge = Altcha::createChallenge($options);

		$templateMgr->assign('altchaEnabled', true);
		$templateMgr->assign('altchaChallenge', [
			'algorithm' => $challenge->algorithm,
			'challenge' => $challenge->challenge,
			'maxnumber' => $challenge->maxnumber,
			'salt' => $challenge->salt,
			'signature' => $challenge->signature,
		]);
	}
}
