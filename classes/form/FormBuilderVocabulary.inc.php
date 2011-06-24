<?php

/**
 * @defgroup FormBuilderVocabulary
 */

/**
 * @file classes/form/FormBuilderVocabulary.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Fbv
 * @ingroup core
 *
 * @brief Class defining Form Builder Vocabulary methods.
 *
 * Form Builder Vocabulary - FBV
 * Generates form code in templates using {fbvX} calls.
 * Group form areas with the {fbvFormArea} call.  These sections mark off groups of semantically
 *  related form sections.
 *  Parameters:
 *   id: The form area ID
 *   class (optional): Any additional classes
 *   title (optional): Title of the area
 * Group form sections with the {fbvFormSection} call.  These sections organize directly related form elements.
 *  Parameters:
 *   id: The section ID
 *   class (optional): Any additional classes
 *   title (optional): Title of the area
 * Form elements are created with {fbvElement type="type"} plus any additional parameters.
 * Each specific element type may have other additional attributes (see their method comments)
 *  Parameters:
 *   type: The form element type (one of the cases in the smartyFBVElement method)
 *   id: The element ID
 *   class (optional): Any additional classes
 *   required (optional) whether the section should have a 'required' label (adds span.required)
 *   for (optional): What the section's label is for
 *   inline: Adds .inline to the element's parent container and causes it to display inline with other elements
 *   size: One of $fbvStyles.size.SMALL (adds .quarter to element's parent container) or $fbvStyles.size.MEDIUM (adds
 *    .half to element's parentcontainer)
 *   required: Adds an asterisk and a .required class to the element's label
 */

class FormBuilderVocabulary {
	/** Form associated with this object, if any.  Will inform smarty which forms to label as required **/
	var $_form;

	/** Styles organized by parameter name */
	var $_fbvStyles;

	/**
	 * Constructor.
	 * @param $form object Form associated with this object
	 */
	function FormBuilderVocabulary($form = null) {
		$this->_fbvStyles = array(
			'size' => array('SMALL' => 'SMALL', 'MEDIUM' => 'MEDIUM'),
		);
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the form
	 * @param $form object
	 */
	function setForm(&$form) {
		if (isset($form)) assert(is_a($form, 'Form'));
		$this->_form =& $form;
	}

	/**
	 * Get the form
	 * @return Object
	 */
	function getForm() {
		return $this->_form;
	}

	/**
	 * Get the form style constants
	 * @return array
	 */
	function getStyles() {
		return $this->_fbvStyles;
	}


	//
	// Public Methods
	//
	/**
	 * A form area that contains form sections.
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
			$smarty->assign('FBV_class', $params['class']);
			$smarty->assign('FBV_id', $params['id']);
			$smarty->assign('FBV_content', $content);
			$smarty->assign('FBV_title', $params['title']);
			return $smarty->fetch('form/formArea.tpl');
		}
		return '';
	}

	/**
	 * A form section that contains controls in a variety of layout possibilities.
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVFormSection($params, $content, &$smarty, &$repeat) {
		$form =& $this->getForm();
		if (!$repeat) {
			$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
			$smarty->assign('FBV_labelFor', empty($params['for']) ? null : $params['for']);

			$smarty->assign('FBV_title', $params['title']);
			$smarty->assign('FBV_label', isset($params['label']) ? $params['label'] : null);
			$smarty->assign('FBV_layoutInfo', $this->_getLayoutInfo($params));
			$smarty->assign('FBV_description', isset($params['description']) ? $params['description'] : null);
			$smarty->assign('FBV_content', $content);

			$class = $params['class'];

			// Check if we are using the Form class and if there are any errors
			if (isset($form) && !empty($form->formSectionErrors)) {
				$class = $class . (empty($class) ? '' : ' ') . 'error';
				$smarty->assign('FBV_sectionErrors', $form->formSectionErrors);
				$form->formSectionErrors = array();
			}

			// If we are displaying checkboxes or radio options, we'll need to use a
			//  list to organize our elements -- Otherwise we use divs and spans
			if (isset($params['list']) && $params['list'] != false) {
				$smarty->assign('FBV_listSection', true);
			} else {
				// Double check that we don't have lists in the content.
				//  This is a kludge but the only way to make sure we've
				//  set the list parameter when we're using lists
				if (substr(trim($content), 0, 4) == "<li>") {
					 $smarty->trigger_error('FBV: list attribute not set on form section containing lists');
				}

				$smarty->assign('FBV_listSection', false);
			}

			$smarty->assign('FBV_class', $class);
			$smarty->assign('FBV_layoutColumns', empty($params['layout']) ? false : true);

			return $smarty->fetch('form/formSection.tpl');
		} else {
			if (isset($form)) $form->formSectionErrors = array();
		}
		return '';
	}

	/**
	 * Base form element.
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVElement($params, &$smarty, $content = null) {
		if (!isset($params['type'])) $smarty->trigger_error('FBV: Element type not set');
		if (!isset($params['id'])) $smarty->trigger_error('FBV: Element ID not set');

		// Set up the element template
		$smarty->assign('FBV_id', $params['id']);
		$smarty->assign('FBV_class', empty($params['class']) ? null : $params['class']);
		$smarty->assign('FBV_layoutInfo', $this->_getLayoutInfo($params));
		$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
		$smarty->assign('FBV_label', empty($params['label']) ? null : $params['label']);
		$smarty->assign('FBV_for', empty($params['for']) ? null : $params['for']);

		// Find fields that the form class has marked as required and add the 'required' class to them
		$params = $this->_addClientSideValidation($params);
		$smarty->assign('FBV_validation', $params['validation']);

		// Set up the specific field's template
		switch (strtolower($params['type'])) {
			case 'autocomplete':
				$content = $this->_smartyFBVAutocompleteInput($params, $smarty);
				break;
			case 'button':
			case 'submit':
				$content = $this->_smartyFBVButton($params, $smarty);
				break;
			case 'checkbox':
				$content = $this->_smartyFBVCheckbox($params, $smarty);
				unset($params['label']);
				break;
			case 'file':
				$content = $this->_smartyFBVFileInput($params, $smarty);
				break;
			case 'hidden':
				$content = $this->_smartyFBVHiddenInput($params, $smarty);
				break;
			case 'keyword':
				$content = $this->_smartyFBVKeywordInput($params, $smarty);
				break;
			case 'link':
				$content = $this->_smartyFBVLink($params, $smarty);
				break;
			case 'radio':
				$content = $this->_smartyFBVRadioButton($params, $smarty);
				unset($params['label']);
				break;
			case 'rangeslider':
				$content = $this->_smartyFBVRangeSlider($params, $smarty);
				break;
			case 'select':
				$content = $this->_smartyFBVSelect($params, $smarty);
				break;
			case 'text':
				$content = $this->_smartyFBVTextInput($params, $smarty);
				break;
			case 'textarea':
				$content = $this->_smartyFBVTextArea($params, $smarty);
				break;
			default:
				$smarty->trigger_error('FBV: Invalid element type "' . $params['type'] . '"');
		}

		unset($params['type']);

		$parent = $smarty->_tag_stack[count($smarty->_tag_stack)-1];
		$group = false;

		if ($parent) {
			$form =& $this->getForm();
			if (isset($form) && isset($form->errorFields[$params['id']])) {
				array_push($form->formSectionErrors, $form->errorsArray[$params['id']]);
			}

			if (isset($parent[1]['group']) && $parent[1]['group']) {
				$group = true;
			}
		}

		return $content;
	}

	//
	// Private methods
	//

	/**
	 * Form button.
	 * parameters: label (or value), disabled (optional), type (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVButton($params, &$smarty) {
		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// the type of this button. the default value is 'button' (but could be 'submit')
		$params['type'] = isset($params['type']) ? strtolower($params['type']) : 'button';
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$buttonParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'class': break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $buttonParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_buttonParams', $buttonParams);

		return $smarty->fetch('form/button.tpl');
	}

	/**
	 * Text link.
	 * parameters: label (or value), disabled (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVLink($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: link form element \'id\' not set.');
		}

		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// Set the URL if there is one (defaults to '#' e.g. when the link should activate javascript)
		if (isset($params['href'])) {
			$smarty->assign('FBV_href', $params['href']);
		} else {
			$smarty->assign('FBV_href', '#');
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
			}
		}

		return $smarty->fetch('form/link.tpl');
	}

	/**
	 * Form Autocomplete text input. (actually two inputs, label and value)
	 * parameters: disabled (optional), name (optional - assigned value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVAutocompleteInput($params, &$smarty) {
		if ( !isset($params['autocompleteUrl']) ) {
			$smarty->trigger_error('FBV: url for autocompletion not specified.');
		}

		// This id will be used for the hidden input that should be read by the Form.
		$autocompleteId = $params['id'];

		// We then override the id parameter to differentiate it from the hidden element
		//  and make sure that the text input is not read by the Form class.
		$params['id'] = $autocompleteId . '_input';
		$smarty->assign('FBV_textInput', $this->_smartyFBVTextInput($params, $smarty));

		$smarty->assign('FBV_id', $autocompleteId);
		$smarty->assign('FBV_autocompleteUrl', $params['autocompleteUrl']);
		return $smarty->fetch('form/autocompleteInput.tpl');
	}

	/**
	 * Range slider input.
	 * parameters: min, max
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVRangeSlider($params, &$smarty) {
		// Make sure our required fields are included
		if (!isset($params['min']) || !isset($params['max'])) {
			$smarty->trigger_error('FBV: Min and/or max value for range slider not specified.');
		}

		// Assign the min and max values to the handler
		$smarty->assign('FBV_min', $params['min']);
		$smarty->assign('FBV_max', $params['max']);

		if (isset($params['label'])) {
			$smarty->assign('FBV_label_content', $this->_smartyFBVSubLabel($params, &$smarty));
		}

		return $smarty->fetch('form/rangeSlider.tpl');
	}

	/**
	 * Form text input.
	 * parameters: disabled (optional), name (optional - assigned value of 'id' by default), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVTextInput($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['multilingual'] = isset($params['multilingual']) ? $params['multilingual'] : false;
		$params['value'] = isset($params['value']) ? $params['value'] : '';
		$smarty->assign('FBV_isPassword', isset($params['password']) ? true : false);

		$textInputParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'label': $smarty->assign('FBV_label_content', $this->_smartyFBVSubLabel($params, &$smarty)); break;
				case 'type': break;
				case 'class': break;
				case 'size': break;
				case 'autocompleteUrl': break;
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				case 'multilingual': $smarty->assign('FBV_multilingual', $params['multilingual']); break;
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'value': $smarty->assign('FBV_value', $params['value']); break;
				default: $textInputParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING). '" ';
			}
		}

		$smarty->assign('FBV_textInputParams', $textInputParams);

		return $smarty->fetch('form/textInput.tpl');
	}

	/**
	 * Form text area.
	 * parameters: value, id, name (optional - assigned value of 'id' by default), disabled (optional), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVTextArea($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['rich'] = isset($params['rich']) ? $params['rich'] : false;
		$params['multilingual'] = isset($params['multilingual']) ? $params['multilingual'] : false;
		$params['value'] = isset($params['value']) ? $params['value'] : '';

		$textAreaParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'value': $smarty->assign('FBV_value', $value); break;
				case 'label': $smarty->assign('FBV_label_content', $this->_smartyFBVSubLabel($params, &$smarty)); break;
				case 'type': break;
				case 'size': break;
				case 'rich': break;
				case 'class': break;
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				case 'multilingual': $smarty->assign('FBV_multilingual', $params['multilingual']); break;
				default: $textAreaParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_textAreaParams', $textAreaParams);

		return $smarty->fetch('form/textarea.tpl');
	}

	/**
	 * Hidden input element.
	 * parameters: value, id, name (optional - assigned value of 'id' by default), disabled (optional), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVHiddenInput($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['value'] = isset($params['value']) ? $params['value'] : '';

		$hiddenInputParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'name': $smarty->assign('FBV_name', $value); break;
				case 'id': $smarty->assign('FBV_id', $value); break;
				case 'value': $smarty->assign('FBV_value', $value); break;
				case 'label': break;
				case 'type': break;
				case 'class': break; //ignore class attributes
				case 'required': break; //ignore required field (define required fields in form class)
				default: $hiddenInputParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_hiddenInputParams', $hiddenInputParams);

		return $smarty->fetch('form/hiddenInput.tpl');
	}

	/**
	 * Form select control.
	 * parameters: from [array], selected [array index], defaultLabel (optional), defaultValue (optional), disabled (optional),
	 * 	translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVSelect($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$selectParams = '';
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
				case 'class': break;
				case 'type': break;
				case 'label': $smarty->assign('FBV_label_content', $this->_smartyFBVSubLabel($params, &$smarty)); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $selectParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_selectParams', $selectParams);

		return $smarty->fetch('form/select.tpl');
	}

	/**
	 * Checkbox input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVCheckbox($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['required'] = isset($params['required']) ? $params['required'] : false;

		$checkboxParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break;
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'required': $smarty->assign('FBV_required', $params['required']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $checkboxParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_checkboxParams', $checkboxParams);

		return $smarty->fetch('form/checkbox.tpl');
	}

	/**
	 * Radio input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVRadioButton($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$radioParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $radioParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_radioParams', $radioParams);

		return $smarty->fetch('form/radioButton.tpl');
	}

	/**
	 * File upload input.
	 * parameters: submit (optional - name of submit button to include), disabled (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVFileInput($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['submit'] = isset($params['submit']) ? $params['submit'] : false;

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'submit': $smarty->assign('FBV_submit', $params['submit']); break;
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'label': $smarty->assign('FBV_label_content', $this->_smartyFBVSubLabel($params, &$smarty)); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
			}
		}

		return $smarty->fetch('form/fileInput.tpl');
	}

	/**
	 * Keyword input.
	 * parameters: available - all available keywords (for autosuggest); current - user's current keywords
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVKeywordInput($params, &$smarty) {
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'available': $smarty->assign('FBV_availableKeywords', $params['available']); break;
				case 'current': $smarty->assign('FBV_currentKeywords', $params['current']); break;
			}
		}

		return $smarty->fetch('form/keywordInput.tpl');
	}

	/**
	 * Custom Smarty function for labelling/highlighting of form fields.
	 * @param $params array can contain 'name' (field name/ID), 'required' (required field), 'key' (localization key), 'label' (non-localized label string), 'suppressId' (boolean)
	 * @param $smarty Smarty
	 */
	function _smartyFBVSubLabel($params, &$smarty) {
		$returner = '';
		if (!isset($params) || !isset($params['label']) ) {
			$smarty->trigger_error('FBV: label for SubLabel not specified.');
		}

		$form =& $this->getForm();
		if (isset($form) && isset($form->errorFields[$params['name']])) {
			$smarty->assign('FBV_error', true);
		} else {
			$smarty->assign('FBV_error', false);
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'suppressId': $smarty->assign('FBV_suppressId', $value); break;
				case 'required': $smarty->assign('FBV_required', $value); break;
			}
		}

		$returner = $smarty->fetch('form/subLabel.tpl');

		return $returner;
	}

	/**
	 * Assign the appropriate class name to the element for client-side validation
	 * @param $params array
	 * return array
	 */
	function _addClientSideValidation($params) {
		$form =& $this->getForm();
		if (isset($form)) {
			// Assign the appropriate class name to the element for client-side validation
			$fieldId = $params['id'];
			if (isset($form->cssValidation[$fieldId])) {
				$params['validation'] = implode(' ', $form->cssValidation[$fieldId]);
			}
		}
		return $params;
	}

	/**
	 * Cycle through layout parameters to add the appropriate classes to the element's parent container
	 * @param $params array
	 * @return string
	 */
	function _getLayoutInfo($params) {
		$classes = array();
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'size':
					switch($value) {
						case 'SMALL': $classes[] = 'quarter'; break;
						case 'MEDIUM': $classes[] = 'half'; break;
					}
					break;
				case 'inline':
					if($value) $classes[] = 'inline'; break;
			}
		}
		if(!empty($classes)) {
			return implode(' ', $classes);
		} else return null;
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
				$params['label'] = Locale::translate($params['key'], $params);
			}

			$form =& $this->getForm();
			if (isset($form) && isset($form->errorFields[$params['name']])) {
				$smarty->assign('class', 'error ' . $params['class']);
			} else {
				$smarty->assign('class', $params['class']);
			}

			foreach ($params as $key => $value) {
				switch ($key) {
					case 'label': $smarty->assign('FBV_label', $value); break;
					case 'required': $smarty->assign('FBV_required', $value); break;
					case 'suppressId': $smarty->assign('FBV_suppressId', true); break;
					case 'required': $smarty->assign('FBV_required', true); break;
					case 'disabled': $smarty->assign('FBV_disabled', $value); break;
					case 'name': $smarty->assign('FBV_name', $value); break;
				}
			}

			$returner = $smarty->fetch('form/fieldLabel.tpl');
		}
		return $returner;
	}
}

?>
