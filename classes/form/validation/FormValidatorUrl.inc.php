<?php

/**
 * @file classes/form/validation/FormValidatorUrl.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUrl
 * @ingroup form_validation
 * @see FormValidator
 *
 * @brief Form validation check for URLs.
 */

// $Id$


import('form.validation.FormValidatorRegExp');

class FormValidatorUrl extends FormValidatorRegExp {
	function getRegexp() {
		return '/^' . PCRE_URL . '/i';
	}

	/**
	 * Constructor.
	 * @see FormValidatorRegExp::FormValidatorRegExp()
	 */
	function FormValidatorUrl(&$form, $field, $type, $message) {
		parent::FormValidatorRegExp($form, $field, $type, $message, FormValidatorUrl::getRegexp());
	}
}

?>
