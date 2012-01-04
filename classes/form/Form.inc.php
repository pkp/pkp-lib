<?php

/**
 * @defgroup form
 */

/**
 * @file classes/form/Form.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Form
 * @ingroup core
 *
 * @brief Class defining basic operations for handling HTML forms.
 */

import('lib.pkp.classes.form.FormError');

// Import all form validators for convenient use in sub-classes
import('lib.pkp.classes.form.validation.FormValidatorAlphaNum');
import('lib.pkp.classes.form.validation.FormValidatorArray');
import('lib.pkp.classes.form.validation.FormValidatorArrayCustom');
import('lib.pkp.classes.form.validation.FormValidatorControlledVocab');
import('lib.pkp.classes.form.validation.FormValidatorCustom');
import('lib.pkp.classes.form.validation.FormValidatorCaptcha');
import('lib.pkp.classes.form.validation.FormValidatorEmail');
import('lib.pkp.classes.form.validation.FormValidatorInSet');
import('lib.pkp.classes.form.validation.FormValidatorLength');
import('lib.pkp.classes.form.validation.FormValidatorLocale');
import('lib.pkp.classes.form.validation.FormValidatorLocaleEmail');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('lib.pkp.classes.form.validation.FormValidatorRegExp');
import('lib.pkp.classes.form.validation.FormValidatorUri');
import('lib.pkp.classes.form.validation.FormValidatorUrl');

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

	/** Array of errors for the form section currently being processed */
	var $formSectionErrors;

	/** Styles organized by parameter name */
	var $fbvStyles;

	/** Client-side validation rules **/
	var $cssValidation;


	/**
	 * Constructor.
	 * @param $template string the path to the form template file
	 */
	function Form($template = null, $callHooks = true) {
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
		$this->formSectionErrors = array();
		$this->fbvStyles = array(
			'size' => array('SMALL' => 'SMALL', 'MEDIUM' => 'MEDIUM', 'LARGE' => 'LARGE'),
			'float' => array('RIGHT' => 'RIGHT', 'LEFT' => 'LEFT'),
			'align' => array('RIGHT' => 'RIGHT', 'LEFT' => 'LEFT'),
			'measure' => array('1OF1' => '1OF1', '1OF2' => '1OF2', '1OF3' => '1OF3', '2OF3' => '2OF3', '1OF4' => '1OF4', '3OF4' => '3OF4',
							'1OF5' => '1OF5', '2OF5' => '2OF5', '3OF5' => '3OF5', '4OF5' => '4OF5', '1OF10' => '1OF10', '8OF10' => '8OF10'),
			'layout' => array('THREE_COLUMNS' => 'THREE_COLUMNS', 'TWO_COLUMNS' => 'TWO_COLUMNS', 'ONE_COLUMN' => 'ONE_COLUMN')
		);
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	/**
	 * Get the template
	 * @return string
	 */
	function getTemplate() {
		return $this->_template;
	}


	//
	// Public Methods
	//
	/**
	 * Display the form.
	 * @param $request PKPRequest
	 * @param $template string the template to be rendered, mandatory
	 *  if no template has been specified on class instantiation.
	 */
	function display($request = null, $template = null) {
		$this->fetch($request, $template, true);
	}

	/**
	 * Returns a string of the rendered form
	 * @param $request PKPRequest
	 * @param $template string the template to be rendered, mandatory
	 *  if no template has been specified on class instantiation.
	 * @param $display boolean
	 * @return string the rendered form
	 */
	function fetch(&$request, $template = null, $display = false) {
		// Set custom template.
		if (!is_null($template)) $this->_template = $template;

		if (checkPhpVersion('4.3.0')) {
			$returner = null;
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::display"
			// Note that class names are always lower case.
			if (HookRegistry::call(strtolower($trace[1]['class']) . '::' . $trace[0]['function'], array(&$this, &$returner))) {
				return $returner;
			}
		}

		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->setCacheability(CACHEABILITY_NO_STORE);
		$templateMgr->register_function('fieldLabel', array(&$this, 'smartyFieldLabel'));
		$templateMgr->register_function('form_language_chooser', array(&$this, 'smartyFormLanguageChooser'));
		$templateMgr->register_function('modal_language_chooser', array(&$this, 'smartyModalLanguageChooser'));
		$templateMgr->register_block('form_locale_iterator', array(&$this, 'formLocaleIterator'));

		// modifier vocabulary for creating forms
		$templateMgr->register_block('fbvFormSection', array(&$this, 'smartyFBVFormSection'));
		$templateMgr->register_block('fbvCustomElement', array(&$this, 'smartyFBVCustomElement'));
		$templateMgr->register_block('fbvFormArea', array(&$this, 'smartyFBVFormArea'));
		$templateMgr->register_function('fbvButton', array(&$this, 'smartyFBVButton'));
		$templateMgr->register_function('fbvLink', array(&$this, 'smartyFBVLink'));
		$templateMgr->register_function('fbvTextInput', array(&$this, 'smartyFBVTextInput'));
		$templateMgr->register_function('fbvTextarea', array(&$this, 'smartyFBVTextArea'));
		$templateMgr->register_function('fbvSelect', array(&$this, 'smartyFBVSelect'));
		$templateMgr->register_function('fbvElement', array(&$this, 'smartyFBVElement'));
		$templateMgr->register_function('fbvElementMultilingual', array(&$this, 'smartyFBVElementMultilingual'));
		$templateMgr->register_function('fbvCheckbox', array(&$this, 'smartyFBVCheckbox'));
		$templateMgr->register_function('fbvRadioButton', array(&$this, 'smartyFBVRadioButton'));
		$templateMgr->register_function('fbvFileInput', array(&$this, 'smartyFBVFileInput'));
		$templateMgr->register_function('fbvKeywordInput', array(&$this, 'smartyFBVKeywordInput'));

		$templateMgr->assign('fbvStyles', $this->fbvStyles);

		$templateMgr->assign($this->_data);
		$templateMgr->assign('isError', !$this->isValid());
		$templateMgr->assign('errors', $this->getErrorsArray());

		$templateMgr->assign('formLocales', AppLocale::getSupportedFormLocales());

		// Determine the current locale to display fields with
		$formLocale = Request::getUserVar('formLocale');
		if (empty($formLocale)) $formLocale = AppLocale::getLocale();
		if (!in_array($formLocale, array_keys(AppLocale::getSupportedFormLocales()))) {
			$formLocale = AppLocale::getPrimaryLocale();
		}
		$templateMgr->assign('formLocale', $formLocale);

		// N.B: We have to call $templateMgr->display instead of ->fetch($display)
		// in order for the TemplateManager::display hook to be called
		return $templateMgr->display($this->_template, null, null, $display);
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
		if (checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::initData"
			// Note that class and function names are always lower
			// case.
			HookRegistry::call(strtolower($trace[1]['class'] . '::' . $trace[0]['function']), array(&$this));
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 * Can be overridden from subclasses.
	 */
	function readInputData() {
		// Default implementation does nothing.
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
			// See http://pkp.sfu.ca/wiki/index.php/Information_for_Developers#Use_of_.24this_in_the_constructor
			// for an explanation why we have to replace the reference to $this here.
			$check->setForm($this);

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
		if (checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::execute"
			// Note that class and function names are always lower
			// case.
			$value = null;
			HookRegistry::call(strtolower($trace[1]['class'] . '::' . $trace[0]['function']), array(&$this, &$vars));
		}
	}

	/**
	 * Get the list of field names that need to support multiple locales
	 * @return array
	 */
	function getLocaleFieldNames() {
		$returner = array();
		if (checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::getLocaleFieldNames"
			// Note that class and function names are always lower
			// case.
			$value = null;
			HookRegistry::call(strtolower($trace[1]['class'] . '::' . $trace[0]['function']), array(&$this, &$returner));
		}

		return $returner;
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
		if (empty($formLocale)) $formLocale = AppLocale::getLocale();
		return $formLocale;
	}

	/**
	 * Adds specified user variables to input data.
	 * @param $vars array the names of the variables to read
	 */
	function readUserVars($vars) {
		if (checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::readUserVars"
			// Note that class and function names are always lower
			// case.
			$value = null;
			HookRegistry::call(strtolower($trace[1]['class'] . '::' . $trace[0]['function']), array(&$this, &$vars));
		}

		foreach ($vars as $k) {
			$this->setData($k, Request::getUserVar($k));
		}
	}

	/**
	 * Adds specified user date variables to input data.
	 * @param $vars array the names of the date variables to read
	 */
	function readUserDateVars($vars) {
		if (checkPhpVersion('4.3.0')) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::readUserDateVars"
			// Note that class and function names are always lower
			// case.
			$value = null;
			HookRegistry::call(strtolower($trace[1]['class'] . '::' . $trace[0]['function']), array(&$this, &$vars));
		}

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
		$returner = '';
		if (isset($params) && !empty($params)) {
			if (isset($params['key'])) {
				$params['label'] = __($params['key'], $params);
			}

			if (isset($this->errorFields[$params['name']])) {
				$class = ' class="error"';
			} else {
				$class = '';
			}
			$returner = '<label' . (isset($params['suppressId']) ? '' : ' for="' . $params['name'] . '"') . $class . '>' . $params['label'] . (isset($params['required']) && !empty($params['required']) ? '*' : '') . '</label>';
		}
		return $returner;
	}

	/**
	 * Add hidden form parameters for the localized fields for this form
	 * and display the language chooser field
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFormLanguageChooser($params, &$smarty) {
		$returner = '';

		// Print back all non-current language field values so that they
		// are not lost.
		$formLocale = $smarty->get_template_vars('formLocale');
		foreach ($this->getLocaleFieldNames() as $field) {
			$values = $this->getData($field);
			if (!is_array($values)) continue;
			foreach ($values as $locale => $value) {
				if ($locale != $formLocale) $returner .= $this->_decomposeArray($field, $value, array($locale));
			}
		}

		// Display the language selector widget.
		$formLocale = $smarty->get_template_vars('formLocale');
		$returner .= '<div id="languageSelector"><select size="1" name="formLocale" id="formLocale" onchange="changeFormAction(\'' . htmlentities($params['form'], ENT_COMPAT, LOCALE_ENCODING) . '\', \'' . htmlentities($params['url'], ENT_QUOTES, LOCALE_ENCODING) . '\')" class="selectMenu">';
		foreach (AppLocale::getSupportedFormLocales() as $locale => $name) {
			$returner .= '<option ' . ($locale == $formLocale?'selected="selected" ':'') . 'value="' . htmlentities($locale, ENT_COMPAT, LOCALE_ENCODING) . '">' . htmlentities($name, ENT_COMPAT, LOCALE_ENCODING) . '</option>';
		}
		$returner .= '</select></div>';
		return $returner;
	}

	/** form builder vocabulary - FBV */

	/**
	 * Get the list of all classes to append to the element (including any custom class set directly in the element)
	 * @param $params array
	 * @return string
	 */
	function getAllStyles($params) {
		$class = isset($params['class']) ? $params['class'] : '';

		// Get size (height)
		if ($size = $params['size']) {
			$size = $params['size'];
			$class .= ' ' . $this->getStyleInfoByIdentifier('size', $size);
		}

		// Get measure (width)
		if ($measure = $params['measure']) {
			$measure = $params['measure'];
			$class .= ' ' . $this->getStyleInfoByIdentifier('measure', $measure);
		}

		// Get float information (for sections)
		if ($float = $params['float']) {
			$float = $params['float'];
			$class .= ' ' . $this->getStyleInfoByIdentifier('float', $float);
		}

		// Get alignment information (for elements)
		if ($align = $params['align']) {
			$align = $params['align'];
			$class .= ' ' . $this->getStyleInfoByIdentifier('align', $align);
		}

		// Get layout information (number of columns)
		if ($layout = $params['layout']) {
			$layout = $params['layout'];
			$class .= ' ' . $this->getStyleInfoByIdentifier('layout', $float);
		}

		return $class;
	}

	/**
	 * Retrieve style info associated with style constants.
	 * @param $category string
	 * @param $value string
	 */
	function getStyleInfoByIdentifier($category, $value) {
		$returner = null;
		switch ($category) {
			case 'size':
				switch($value) {
					case 'SMALL': $returner = 'small'; break;
					case 'MEDIUM': $returner = 'medium'; break;
					case 'LARGE': $returner = 'large'; break;
				}
				break;
			case 'float':
				switch($value) {
					case 'LEFT': $returner = 'full leftHalf'; break;
					case 'RIGHT': $returner = 'full rightHalf'; break;
				}
				break;
			case 'align':
				switch($value) {
					case 'LEFT': $returner = 'align_left'; break;
					case 'RIGHT': $returner = 'align_right'; break;
				}
				break;
			case 'layout':
				switch($value) {
					case 'THREE_COLUMNS': $returner = 'full threeColumns'; break;
					case 'TWO_COLUMNS': $returner = 'full twoColumns'; break;
					case 'ONE_COLUMN': $returner = 'full'; break;
				}
				break;
			case 'measure':
				switch($value) {
					case '1OF1': $returner = 'size1of1'; break;
					case '1OF2': $returner = 'size1of2'; break;
					case '1OF3': $returner = 'size1of3'; break;
					case '2OF3': $returner = 'size2of3'; break;
					case '1OF4': $returner = 'size1of4'; break;
					case '3OF4': $returner = 'size3of4'; break;
					case '1OF5': $returner = 'size3of5'; break;
					case '2OF5': $returner = 'size2of5'; break;
					case '3OF5': $returner = 'size3of5'; break;
					case '4OF5': $returner = 'size4of5'; break;
					case '1OF10': $returner = 'size1of10'; break;
					case '8OF10': $returner = 'size8of10'; break;
				}
				break;
		}

		if (!$returner) {
			$templateMgr =& TemplateManager::getManager();
			$templateMgr->trigger_error('FBV: invalid style value ['.$category.', '.$value.']');
		}

		return $returner;
	}

	/**
	 * A form area that contains form sections.
	 * parameters: id
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVFormArea($params, $content, &$smarty, &$repeat) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: form area \'id\' not set.');
		}

 		if (!$repeat) {
			$smarty->assign('FBV_id', $params['id']);
			$smarty->assign('FBV_content', $content);
			return $smarty->fetch('form/formArea.tpl');
		}
		return '';
	}

	/**
	 * A form section that contains controls in a variety of layout possibilities.
	 * parameters: title, float (optional), layout (optional), group (optional), required (optional), for (optinal)
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVFormSection($params, $content, &$smarty, &$repeat) {

		if (!$repeat) {
			$smarty->assign('FBV_group', isset($params['group']) ? $params['group'] : false);
			$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
			$smarty->assign('FBV_labelFor', empty($params['for']) ? null : $params['for']);

			$smarty->assign('FBV_title', $params['title']);
			$smarty->assign('FBV_content', $content);

			$class = $this->getAllStyles($params);

			if (!empty($this->formSectionErrors)) {
				$class = $class . (empty($class) ? '' : ' ') . 'error';
			}

			$smarty->assign('FBV_sectionErrors', $this->formSectionErrors);
			$smarty->assign('FBV_class', $class);

			$smarty->assign('FBV_layoutColumns', empty($layoutInfo) ? false : true);
			$this->formSectionErrors = array();

			return $smarty->fetch('form/formSection.tpl');

		} else {
			$this->formSectionErrors = array();
		}
		return '';
	}

	function smartyFBVElementMultilingual($params, &$smarty, $content = null) {
		if ( !isset($params['value']) || !is_array($params['value'])) {
			$smarty->trigger_error('FBV: value parameter must be an array for multilingual elements');
		}
		if ( !isset($params['name']) ) {
			$smarty->trigger_error('FBV: parameter must be set');
		}

		$required = isset($params['required'])?$params['required']:false;

		$returner = '';
		$values = $params['value'];
		$name = $params['name'];

		foreach (AppLocale::getSupportedLocales() as $locale => $localeName) {
			// if the field is required, only set the main locale as required and others optional
			if ( $locale == AppLocale::getPrimaryLocale() ) {
				$params['required'] = $required;
			} else {
				$params['required'] = false;
			}
			$params['name'] = $name . "[$locale]";
			$params['value'] = $values[$locale];
			$returner .= $localeName . ' ' . $this->smartyFBVElement($params, $smarty, $content) . '<br />';
		}
		return $returner;
	}

	/**
	 * Form element.
	 * parameters: type, id, label (optional), required (optional), measure, any other attributes specific to 'type'
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVElement($params, &$smarty, $content = null) {
		if (isset($params['type'])) {
			switch (strtolower($params['type'])) {
				case 'text':
					$content = $this->smartyFBVTextInput($params, $smarty);
					break;
				case 'textarea':
					$content = $this->smartyFBVTextArea($params, $smarty);
					break;
				case 'checkbox':
					$content = $this->smartyFBVCheckbox($params, $smarty);
					unset($params['label']);
					break;
				case 'radio':
					$content = $this->smartyFBVRadioButton($params, $smarty);
					unset($params['label']);
					break;
				case 'select':
					$content = $this->smartyFBVSelect($params, $smarty);
					break;
				case 'custom':
					break;
				default: $content = null;
			}

			if (!$content) return '';

			unset($params['type']);

			$parent = $smarty->_tag_stack[count($smarty->_tag_stack)-1];
			$group = false;

			if ($parent) {
				if (isset($this->errorFields[$params['id']])) {
					array_push($this->formSectionErrors, $this->errorsArray[$params['id']]);
				}

				if (isset($parent[1]['group']) && $parent[1]['group']) {
					$group = true;
				}
			}

			$smarty->assign('FBV_class', $this->getAllStyles($params));
			$smarty->assign('FBV_content', $content);
			$smarty->assign('FBV_group', $group);
			$smarty->assign('FBV_id', isset($params['id']) ? $params['id'] : null);
			$smarty->assign('FBV_label', empty($params['label']) ? null : $params['label']);
			$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
			$smarty->assign('FBV_measureInfo', empty($params['measure']) ? null : $this->getStyleInfoByIdentifier('measure', $params['measure']));

			return $smarty->fetch('form/element.tpl');
		}
		return '';
	}

	/**
	 * Custom form element. User form code is placed between customElement tags.
	 * parameters: id, label (optional), required (optional)
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVCustomElement($params, $content, &$smarty, &$repeat) {
		if (!$repeat) {
			$params['type'] = 'custom';
			return $this->smartyFBVElement($params, $smarty, $content);
		}
		return '';
	}

	/**
	 * Form button.
	 * parameters: label (or value), disabled (optional), type (optional), all other attributes associated with this control (except class)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVButton($params, &$smarty) {
		$buttonParams = '';

		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// the type of this button. the default value is 'button'
		$params['type'] = isset($params['type']) ? strtolower($params['type']) : 'button';
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $buttonParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_id', isset($params['id']) ? $params['id'] : null);
		$smarty->assign('FBV_buttonParams', $buttonParams);

		return $smarty->fetch('form/button.tpl');
	}

	/**
	 * Form button.
	 * parameters: label (or value), disabled (optional), type (optional), all other attributes associated with this control (except class)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVLink($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: link form element \'id\' not set.');
		}

		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// the type of this button. the default value is 'button'
		$params['type'] = isset($params['type']) ? strtolower($params['type']) : 'button';
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $buttonParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_id', isset($params['id']) ? $params['id'] : null);
		$smarty->assign('FBV_buttonParams', $buttonParams);

		return $smarty->fetch('form/link.tpl');
	}

	/**
	 * Form text input.
	 * parameters: size, disabled (optional), name (optional - assigned value of 'id' by default), all other attributes associated with this control (except class and type)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVTextInput($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: text input form element \'id\' not set.');
		}

		$textInputParams = '';

		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params = $this->addClientSideValidation($params);
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory
		$smarty->assign('FBV_isPassword', isset($params['password']) ? true : false);

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'label': break;
				case 'type': break;
				case 'validation': $smarty->assign('FBV_validation', $params['validation']); break;
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $textInputParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING). '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_textInputParams', $textInputParams);

		return $smarty->fetch('form/textInput.tpl');
	}

	/**
	 * Form text area.
	 * parameters: value, id, name (optional - assigned value of 'id' by default), disabled (optional), all other attributes associated with this control (except class)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVTextArea($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: text area form element \'id\' not set.');
		}

		$params = $this->addClientSideValidation($params);

		$textAreaParams = '';
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'value': $smarty->assign('FBV_value', $value); break;
				case 'label': break;
				case 'type': break;
				case 'class': break; //ignore class attributes
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $textAreaParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_textAreaParams', $textAreaParams);

		return $smarty->fetch('form/textarea.tpl');
	}

	/**
	 * Form select control.
	 * parameters: from [array], selected [array index], defaultLabel (optional), defaultValue (optional), disabled (optional),
	 * 	translate (optional), name (optional - value of 'id' by default), all other attributes associated with this control (except class)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVSelect($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: select form element \'id\' not set.');
		}

		$selectParams = '';
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		if (!$params['defaultValue'] || !$params['defaultLabel']) {
			if (isset($params['defaultValue'])) unset($params['defaultValue']);
			if (isset($params['defaultLabel'])) unset($params['defaultLabel']);
			$smarty->assign('FBV_defaultValue', null);
			$smarty->assign('FBV_defaultLabel', null);
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'from': $smarty->assign('FBV_from', $value); break;
				case 'selected': $smarty->assign('FBV_selected', $value); break;
				case 'translate': $smarty->assign('FBV_translate', $value); break;
				case 'defaultValue': $smarty->assign('FBV_defaultValue', $value); break;
				case 'defaultLabel': $smarty->assign('FBV_defaultLabel', $value); break;
				case 'class': break; //ignore class attributes
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $selectParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_selectParams', $selectParams);

		return $smarty->fetch('form/select.tpl');
	}

	/**
	 * Checkbox input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default), all other attributes associated with this control (except class and type)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVCheckbox($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: checkbox form element \'id\' not set.');
		}

		$params = $this->addClientSideValidation($params);

		$checkboxParams = '';
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['required'] = isset($params['required']) ? $params['required'] : false;
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'validation': $smarty->assign('FBV_validation', $params['validation']); break;
				case 'required': $smarty->assign('FBV_required', $params['required']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $checkboxParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_checkboxParams', $checkboxParams);

		return $smarty->fetch('form/checkbox.tpl');
	}

	/**
	 * Radio input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default), all other attributes associated with this control (except class and type)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVRadioButton($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: radio input form element \'id\' not set.');
		}

		$radioParams = '';
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $radioParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_radioParams', $radioParams);

		return $smarty->fetch('form/radioButton.tpl');
	}

	/**
	 * File upload input.
	 * parameters: submit (optional - name of submit button to include), disabled (optional), name (optional - value of 'id' by default), all other attributes associated with this control (except class and type)
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVFileInput($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: file input form element \'id\' not set.');
		}

		$radioParams = '';
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['submit'] = isset($params['submit']) ? $params['submit'] : false;

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'submit': $smarty->assign('FBV_submit', $params['submit']); break;
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $radioParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_radioParams', $radioParams);

		return $smarty->fetch('form/fileInput.tpl');
	}

	/**
	 * Keyword input.
	 * parameters: available - all available keywords (for autosuggest); current - user's current keywords
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVKeywordInput($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: file input form element \'id\' not set.');
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break; //ignore class attributes
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'available': $smarty->assign('FBV_availableKeywords', $params['available']); break;
				case 'current': $smarty->assign('FBV_currentKeywords', $params['current']); break;
				default: $keywordParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_class', $this->getAllStyles($params));
		$smarty->assign('FBV_keywordParams', $keywordParams);

		return $smarty->fetch('form/keywordInput.tpl');
	}

	/**
	 * Assign the appropriate class name to the element for client-side validation
	 * @param $params array
	 * return array
	 */
	function addClientSideValidation($params) {
		// Assign the appropriate class name to the element for client-side validation
		$fieldId = $params['id'];
		if (isset($this->cssValidation[$fieldId])) {
			$params['validation'] = implode(' ', $this->cssValidation[$fieldId]);
		}

		return $params;
	}


	//
	// Private helper methods
	//
	/**
	 * FIXME: document
	 * @param $name
	 * @param $value
	 * @param $stack
	 */
	function _decomposeArray($name, $value, $stack) {
		$returner = '';
		if (is_array($value)) {
			foreach ($value as $key => $subValue) {
				$newStack = $stack;
				$newStack[] = $key;
				$returner .= $this->_decomposeArray($name, $subValue, $newStack);
			}
		} else {
			$name = htmlentities($name, ENT_COMPAT, LOCALE_ENCODING);
			$value = htmlentities($value, ENT_COMPAT, LOCALE_ENCODING);
			$returner .= '<input type="hidden" name="' . $name;
			while (($item = array_shift($stack)) !== null) {
				$item = htmlentities($item, ENT_COMPAT, LOCALE_ENCODING);
				$returner .= '[' . $item . ']';
			}
			$returner .= '" value="' . $value . "\" />\n";
		}
		return $returner;
	}
}

?>
