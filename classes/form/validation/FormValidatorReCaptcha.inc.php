<?php

/**
 * @file classes/form/validation/FormValidatorReCaptcha.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorReCaptcha
 * @ingroup form_validation
 *
 * @brief Form validation check reCaptcha values.
 */

define('RECAPTCHA_RESPONSE_FIELD', 'g-recaptcha-response');
define('RECAPTCHA_HOST', 'https://www.google.com');
define("RECAPTCHA_PATH", "/recaptcha/api/siteverify");

class FormValidatorReCaptcha extends FormValidator {
	/** @var string The initiating IP address of the user */
	var $_userIp;
	/** @var string The hostname to expect in the validation response */
	var $_hostname;

	/**
	 * Constructor.
	 * @param $form object
	 * @param $userIp string IP address of user request
	 * @param $message string Key of message to display on mismatch
	 * @param $hostname string Hostname to expect in validation response
	 */
	function __construct(&$form, $userIp, $message, $hostname = '') {
		parent::__construct($form, RECAPTCHA_RESPONSE_FIELD, FORM_VALIDATOR_REQUIRED_VALUE, $message);
		$this->_userIp = $userIp;
		$this->_hostname = $hostname;
	}


	//
	// Public methods
	//
	/**
	 * @see FormValidator::isValid()
	 * Determine whether or not the form meets this ReCaptcha constraint.
	 * @return boolean
	 */
	function isValid() {

		$privateKey = Config::getVar('captcha', 'recaptcha_private_key');
		if (is_null($privateKey) || empty($privateKey)) {
			return false;
		}

		if (is_null($this->_userIp) || empty($this->_userIp)) {
			return false;
		}

		$form =& $this->getForm();

		// Request response from recaptcha api
		$requestOptions = array(
			'http' => array(
				'header' => "Content-Type: application/x-www-form-urlencoded;\r\n",
				'method' => 'POST',
				'content' => http_build_query(array(
					'secret' => $privateKey,
					'response' => $form->getData(RECAPTCHA_RESPONSE_FIELD),
					'remoteip' => $this->_userIp,
				)),
			),
		);

		$proxySettings = array(
			'host' => Config::getVar('proxy', 'http_host'),
			'port' => Config::getVar('proxy', 'http_port'),
			'user' => Config::getVar('proxy', 'proxy_username'),
			'pass' => Config::getVar('proxy', 'proxy_password'),
		);
		if (!empty($proxySettings['host'])) {
			$requestOptions['http']['proxy'] = $proxySettings['host'] . ((!empty($proxySettings['port'])) ? ':'.$proxySettings['port'] : '');
			$requestOptions['http']['request_fulluri'] = true;
			if (!empty($proxySettings['user'])) {
				$requestOptions['http']['header'] .= 'Proxy-Authorization: Basic ' . base64_encode($proxySettings['user'].':'.$proxySettings['pass']);
			}
		}

		$requestContext = stream_context_create($requestOptions);
		$response = file_get_contents(RECAPTCHA_HOST . RECAPTCHA_PATH, false, $requestContext);
		if ($response === false) {
			return false;
		}

		$response = json_decode($response, true);

		// Unrecognizable response from Google server
		if (isset($response['success']) && $response['success'] === true) {
			if (Config::getVar('captcha', 'recaptcha_enforce_hostname') && $response['hostname'] !== $this->_hostname) {
				$this->_message = 'common.captcha.error.invalid-input-response';
				return false;
			}
			return true;
		} else {
			if (isset($response['error-codes']) && is_array($response['error-codes'])) {
				$this->_message = 'common.captcha.error.' . $response['error-codes'][0];
			}
			return false;
		}

	}
}



