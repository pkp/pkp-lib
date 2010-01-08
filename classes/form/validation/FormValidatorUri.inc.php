<?php

/**
 * @file classes/form/validation/FormValidatorUri.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUri
 * @ingroup form_validation
 * @see FormValidator
 *
 * @brief Form validation check for URIs.
 */

// $Id$


import('form.validation.FormValidatorRegExp');

class FormValidatorUri extends FormValidatorRegExp {
	function getRegexp($allowedSchemes = null) {
		if (is_array($allowedSchemes)) {
			$schemesRegEx = '(?:(' . implode('|', $allowedSchemes) . '):)';
			$regEx = $schemesRegEx . substr(PCRE_URI, 24);
		} else {
			$regEx = PCRE_URI;
		}
		return '&^' . $regEx . '$&i';
	}

	/**
	 * Constructor.
	 * @see FormValidatorRegExp::FormValidatorRegExp()
	 */
	function FormValidatorUri(&$form, $field, $type, $message, $allowedSchemes = null) {
		parent::FormValidatorRegExp($form, $field, $type, $message, FormValidatorUri::getRegexp($allowedSchemes));
	}

	/**
	 * @see FormValidatorRegExp::isValid()
	 */
	function isValid() {
		if(!parent::isValid()) return false;

		$matches = $this->getMatches();

		// Check IPv4 address validity
		if (!empty($matches[4])) {
			$parts = explode('.', $matches[4]);
			foreach ($parts as $part) {
				if ($part > 255) {
					return false;
				}
			}
		}

		return true;
	}
}
?>
