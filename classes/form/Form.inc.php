<?php

/**
 * @file classes/form/Form.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Form
 * @ingroup core
 *
 * @brief Class defining basic operations for handling HTML forms.
 */

// $Id: Form.inc.php,v 1.7 2009/10/30 16:43:42 asmecher Exp $


import('form.FormError');
import('form.validation.FormValidator');

class Form {

	/** The template file containing the HTML form */
	var $_template;

	/** Associative array containing form data */
	var $_data;

	/** Validation checks for this form */
	var $_checks;

	/** Errors occurring in form validation */
	var $_errors;

	/** Array of field names where an error occurred and the associated error message */
	var $errorsArray;

	/** Array of field names where an error occurred */
	var $errorFields;

	/**
	 * Constructor.
	 * @param $template string the path to the form template file
	 */
	function Form($template, $callHooks = true) {
		if ($callHooks === true && checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::Constructor"
			// Note that class names are always lower case.
			HookRegistry::call(strtolower($trace[1]['class']) . '::Constructor', array(&$this, &$template));
		}

		$this->_template = $template;
		$this->_data = array();
		$this->_checks = array();
		$this->_errors = array();
		$this->errorsArray = array();
		$this->errorFields = array();
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->setCacheability(CACHEABILITY_NO_STORE);
		$templateMgr->register_function('fieldLabel', array(&$this, 'smartyFieldLabel'));
		$templateMgr->register_function('form_language_chooser', array(&$this, 'smartyFormLanguageChooser'));
		$templateMgr->register_function('modal_language_chooser', array(&$this, 'smartyModalLanguageChooser'));
		$templateMgr->register_block('form_locale_iterator', array(&$this, 'formLocaleIterator'));

		$templateMgr->assign($this->_data);
		$templateMgr->assign('isError', !$this->isValid());
		$templateMgr->assign('errors', $this->getErrorsArray());

		$templateMgr->assign('formLocales', Locale::getSupportedFormLocales());

		// Determine the current locale to display fields with
		$formLocale = Request::getUserVar('formLocale');
		if (empty($formLocale) || !in_array($formLocale, array_keys(Locale::getAllLocales()))) {
			$formLocale = Locale::getLocale();
		}
		$templateMgr->assign('formLocale', $formLocale);

		$templateMgr->display($this->_template);
	}

	/**
	 * Get the value of a form field.
	 * @param $key string
	 * @return mixed
	 */
	function getData($key) {
		return isset($this->_data[$key]) ? $this->_data[$key] : null;
	}

	/**
	 * Set the value of a form field.
	 * @param $key
	 * @param $value
	 */
	function setData($key, $value) {

		if (is_string($value)) $value = Core::cleanVar($value);

		$this->_data[$key] = $value;
	}

	/**
	 * Initialize form data for a new form.
	 */
	function initData() {
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
	}

	/**
	 * Validate form data.
	 */
	function validate($callHooks = true) {
		if (!isset($this->errorsArray)) {
			$this->getErrorsArray();
		}

		foreach ($this->_checks as $check) {
			// WARNING: This line is for PHP4 compatibility when
			// instantiating forms without reference. Should not
			// be removed or otherwise used.
			$check->_setForm($this);

			if (!isset($this->errorsArray[$check->getField()]) && !$check->isValid()) {
				if (method_exists($check, 'getErrorFields') && method_exists($check, 'isArray') && call_user_func(array(&$check, 'isArray'))) {
					$errorFields = call_user_func(array(&$check, 'getErrorFields'));
					for ($i=0, $count=count($errorFields); $i < $count; $i++) {
						$this->addError($errorFields[$i], $check->getMessage());
						$this->errorFields[$errorFields[$i]] = 1;
					}
				} else {
					$this->addError($check->getField(), $check->getMessage());
					$this->errorFields[$check->getField()] = 1;
				}
			}
		}

		if ($callHooks === true && checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::validate"
			// Note that class and function names are always lower
			// case.
			$value = null;
			if (HookRegistry::call(strtolower($trace[0]['class'] . '::' . $trace[0]['function']), array(&$this, &$value))) {
				return $value;
			}
		}

		return $this->isValid();
	}

	/**
	 * Execute the form's action.
	 * (Note that it is assumed that the form has already been validated.)
	 */
	function execute() {
	}

	/**
	 * Get the list of field names that need to support multiple locales
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array();
	}

	/**
	 * Determine whether or not the current request results from a resubmit
	 * of locale data resulting from a form language change.
	 * @return boolean
	 */
	function isLocaleResubmit() {
		$formLocale = Request::getUserVar('formLocale');
		return (!empty($formLocale));
	}

	/**
	 * Get the current form locale.
	 * @return string
	 */
	function getFormLocale() {
		$formLocale = Request::getUserVar('formLocale');
		if (empty($formLocale)) $formLocale = Locale::getLocale();
		return $formLocale;
	}

	/**
	 * Adds specified user variables to input data.
	 * @param $vars array the names of the variables to read
	 */
	function readUserVars($vars) {
		foreach ($vars as $k) {
			$this->setData($k, Request::getUserVar($k));
		}
	}

	/**
	 * Adds specified user date variables to input data.
	 * @param $vars array the names of the date variables to read
	 */
	function readUserDateVars($vars) {
		foreach ($vars as $k) {
			$this->setData($k, Request::getUserDateVar($k));
		}
	}

	/**
	 * Add a validation check to the form.
	 * @param $formValidator FormValidator
	 */
	function addCheck($formValidator) {
		$this->_checks[] =& $formValidator;
	}

	/**
	 * Add an error to the form.
	 * Errors are typically assigned as the form is validated.
	 * @param $field string the name of the field where the error occurred
	 */
	function addError($field, $message) {
		$this->_errors[] = new FormError($field, $message);
	}

	/**
	 * Add an error field for highlighting on form
	 * @param $field string the name of the field where the error occurred
	 */
	function addErrorField($field) {
		$this->errorFields[$field] = 1;
	}

	/**
	 * Check if form passes all validation checks.
	 * @return boolean
	 */
	function isValid() {
		return empty($this->_errors);
	}

	/**
	 * Return set of errors that occurred in form validation.
	 * If multiple errors occurred processing a single field, only the first error is included.
	 * @return array erroneous fields and associated error messages
	 */
	function getErrorsArray() {
		$this->errorsArray = array();
		foreach ($this->_errors as $error) {
			if (!isset($this->errorsArray[$error->getField()])) {
				$this->errorsArray[$error->getField()] = $error->getMessage();
			}
		}
		return $this->errorsArray;
	}

	/**
	 * Custom Smarty function for labelling/highlighting of form fields.
	 * @param $params array can contain 'name' (field name/ID), 'required' (required field), 'key' (localization key), 'label' (non-localized label string), 'suppressId' (boolean)
	 * @param $smarty Smarty
	 */
	function smartyFieldLabel($params, &$smarty) {
		if (isset($params) && !empty($params)) {
			if (isset($params['key'])) {
				$params['label'] = Locale::translate($params['key'], $params);
			}

			if (isset($this->errorFields[$params['name']])) {
				$class = ' class="error"';
			} else {
				$class = '';
			}
			echo '<label' . (isset($params['suppressId']) ? '' : ' for="' . $params['name'] . '"'), $class, '>', $params['label'], (isset($params['required']) && !empty($params['required']) ? '*' : ''), '</label>';
		}
	}

	function _decomposeArray($name, $value, $stack) {
		if (is_array($value)) {
			foreach ($value as $key => $subValue) {
				$newStack = $stack;
				$newStack[] = $key;
				$this->_decomposeArray($name, $subValue, $newStack);
			}
		} else {
			$name = htmlentities($name, ENT_COMPAT, LOCALE_ENCODING);
			$value = htmlentities($value, ENT_COMPAT, LOCALE_ENCODING);
			echo '<input type="hidden" name="' . $name;
			while (($item = array_shift($stack)) !== null) {
				$item = htmlentities($item, ENT_COMPAT, LOCALE_ENCODING);
				echo '[' . $item . ']';
			}
			echo '" value="' . $value . "\" />\n";
		}
	}

	/**
	 * Add hidden form parameters for the localized fields for this form
	 * and display the language chooser field
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFormLanguageChooser($params, &$smarty) {
		// Echo back all non-current language field values so that they
		// are not lost.
		$formLocale = $smarty->get_template_vars('formLocale');
		foreach ($this->getLocaleFieldNames() as $field) {
			$values = $this->getData($field);
			if (!is_array($values)) continue;
			foreach ($values as $locale => $value) {
				if ($locale != $formLocale) $this->_decomposeArray($field, $value, array($locale));
			}
		}

		// Display the language selector widget.
		$formLocale = $smarty->get_template_vars('formLocale');
		echo '<div id="languageSelector"><select size="1" name="formLocale" id="formLocale" onchange="changeFormAction(\'' . htmlentities($params['form'], ENT_COMPAT, LOCALE_ENCODING) . '\', \'' . htmlentities($params['url'], ENT_QUOTES, LOCALE_ENCODING) . '\')" class="selectMenu">';
		foreach (Locale::getSupportedLocales() as $locale => $name) {
			echo '<option ' . ($locale == $formLocale?'selected="selected" ':'') . 'value="' . htmlentities($locale, ENT_COMPAT, LOCALE_ENCODING) . '">' . htmlentities($name, ENT_COMPAT, LOCALE_ENCODING) . '</option>';
		}
		echo '</select></div>';
	}
	
	
    /**
	 * Add hidden form parameters for the localized fields for modal dialogs
	 * and display the language chooser field
	 * @params $params array associative array
	 * @params $smarty Smarty
	 * @return string Call to modal function with specified parameters
	 */
    function smartyModalLanguageChooser($params, &$smarty) {
		// Display the language selector widget.
		$formLocale = $smarty->get_template_vars('formLocale');
		echo "<input type='hidden' id='currentLocale' value=$formLocale>";
		echo '<div id="languageSelector"><select size="1" name="formLocale" id="formLocale" onChange="changeModalFormLocale()" class="selectMenu">';
		foreach (Locale::getSupportedLocales() as $locale => $name) {
			echo '<option ' . ($locale == $formLocale?'selected="selected" ':'') . 'value="' . htmlentities($locale, ENT_COMPAT, LOCALE_ENCODING) . '">' . htmlentities($name, ENT_COMPAT, LOCALE_ENCODING) . '</option>';
		}
		echo '</select></div>';
    }
 
	
	/**
	 * Iterator function for locales (prints a form field once for each locale).
	 */
	function formLocaleIterator($params, $content, &$smarty, &$repeat) {
		$elementType = $params['type'];
		$currentLocale = Locale::getPrimaryLocale();
		
		if(!$repeat) {
			foreach (Locale::getSupportedLocales() as $locale => $name) {
				$currentContent = $content;
				if ($locale != $currentLocale) {
					$currentContent = str_replace($currentLocale, $locale, $currentContent);
					$currentContent = preg_replace('/rule_required/', '', $currentContent);
					$style = 'display:none;';
				} 
				echo "<div class='$locale' style='$style'>$currentContent</div>";
			}
		}
	}
}

?>
