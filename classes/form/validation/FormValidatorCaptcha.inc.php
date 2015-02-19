<?php

/**
 * @file classes/form/validation/FormValidatorCaptcha.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorCaptcha
 * @ingroup form_validation
 *
 * @brief Form validation check captcha values.
 */

class FormValidatorCaptcha extends FormValidator {
	/** @var string */
	var $_captchaIdField;

	/**
	 * Constructor.
	 * @param $form object
	 * @param $field string Name of captcha value submitted by user
	 * @param $captchaIdField string Name of captcha ID field
	 * @param $message string Key of message to display on mismatch
	 */
	function FormValidatorCaptcha(&$form, $field, $captchaIdField, $message) {
		parent::FormValidator($form, $field, FORM_VALIDATOR_REQUIRED_VALUE, $message);
		$this->_captchaIdField = $captchaIdField;
	}


	//
	// Public methods
	//
	/**
	 * @see FormValidator::isValid()
	 * Determine whether or not the form meets this Captcha constraint.
	 * @return boolean
	 */
	function isValid() {
		$captchaDao =& DAORegistry::getDAO('CaptchaDAO');
		$form =& $this->getForm();
		$captchaId = $form->getData($this->_captchaIdField);
		$captchaValue = $this->getFieldValue();
		$captcha =& $captchaDao->getCaptcha($captchaId);
		if ($captcha && $captcha->getValue() === $captchaValue) {
			$captchaDao->deleteObject($captcha);
			return true;
		}
		return false;
	}
}

?>
