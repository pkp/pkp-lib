<?php

/**
 * @file classes/form/validation/FormValidatorUrl.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUrl
 * @ingroup form_validation
 * @see FormValidator
 *
 * @brief Form validation check for URLs.
 */

// $Id$


import('form.validation.FormValidatorUri');

class FormValidatorUrl extends FormValidatorUri {
	function getRegexp() {
		return parent::getRegexp(array('http', 'https', 'ftp'));
	}

	/**
	 * Constructor.
	 * @see FormValidatorUri::FormValidatorUri()
	 */
	function FormValidatorUrl(&$form, $field, $type, $message) {
		parent::FormValidatorUri($form, $field, $type, $message, array('http', 'https', 'ftp'));
	}
}

?>
