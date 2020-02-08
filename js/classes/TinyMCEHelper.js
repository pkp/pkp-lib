/**
 * @file js/classes/TinyMCEHelper.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TinyMCEHelper
 * @ingroup js_classes
 *
 * @brief TinyMCE helper methods
 */
(function($) {


	/**
	 * Helper singleton
	 * @constructor
	 *
	 * @extends $.pkp.classes.ObjectProxy
	 */
	$.pkp.classes.TinyMCEHelper = function() {
		throw new Error('Trying to instantiate the TinyMCEHelper singleton!');
	};


	//
	// Public static methods.
	//
	/**
	 * Get the list of variables and their descriptions for a specified field.
	 * @param {string} selector The textarea field's selector.
	 * @return {?Object} Map of variableName: variableDisplayName entries.
	 */
	$.pkp.classes.TinyMCEHelper.prototype.getVariableMap =
			function(selector) {

		var variablesEncoded = $(selector).attr('data-variables'),
				variablesParsed;

		// If we found the data attribute, decode and return it.
		if (variablesEncoded !== undefined) {
			return $.parseJSON(decodeURIComponent(
					/** @type {string} */ (variablesEncoded)));
		}

		// If we could not find the data attribute, return an empty list.
		return [];
	};


	/**
	 * Get the list of variables and their types for a specified field.
	 * @param {string} selector The textarea field's selector.
	 * @return {?Object} Map of variableName: variableType entries.
	 */
	$.pkp.classes.TinyMCEHelper.prototype.getVariableTypesMap =
			function(selector) {

		var variablesTypeEncoded = $(selector).attr('data-variablesType');

		// If we found the data attribute, decode and return it.
		if (variablesTypeEncoded !== undefined) {
			return $.parseJSON(decodeURIComponent(
					/** @type {string} */(variablesTypeEncoded)));
		}

		// If we could not find the data attribute, return an empty list.
		return [];
	};


	/**
	 * Generate an element to represent a PKP variable (e.g. primary contact name
	 * in setup) within the TinyMCE editor.
	 * @param {string} variableSymbolic The variable symbolic name.
	 * @param {string} variableName The human-readable name for the variable.
	 * @param {string} selector The selector to use for the element.
	 * @return {jQueryObject} JQuery DOM representing the PKP variable.
	 */
	$.pkp.classes.TinyMCEHelper.prototype.getVariableElement =
			function(variableSymbolic, variableName, selector) {
		var variableType, variableTypes =
				$.pkp.classes.TinyMCEHelper.prototype.getVariableTypesMap(selector);

		// Check if there is a variable type that should be treated otherwise
		if (variableTypes[variableSymbolic] != undefined) {
			variableType = variableTypes[variableSymbolic];
			if (variableType == $.pkp.cons.INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT) {
				return $('<div/>').append($('<span/>').text(variableName));
			}
		}

		return $('<div/>').append($('<span/>')
				.addClass('pkpTag mceNonEditable')
				.attr('data-symbolic', variableSymbolic)
				.text(variableName));
	};


}(jQuery));
