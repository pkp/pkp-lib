/**
 * @file js/controllers/AutocompleteHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AutocompleteHandler
 * @ingroup js_controllers
 *
 * @brief PKP autocomplete handler (extends the functionality of the
 *  jqueryUI autocomplete)
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $autocompleteField the wrapped HTML input element.
	 * @param {Object} options options to be passed
	 *  into the jqueryUI autocomplete plugin.
	 */
	$.pkp.controllers.AutocompleteHandler = function($autocompleteField, options) {
		var autocompleteOptions, opt;

		this.parent($autocompleteField, options);

		// Get the text input inside of this Div.
		this.textInput = $autocompleteField.find(':text');
		this.textInput.keyup(this.callbackWrapper(this.synchronizeFields_));

		// Get the text input inside of this Div.
		this.hiddenInput_ = $autocompleteField.find(':hidden');

		// Get the URL passed in
		this.sourceUrl_ = options.sourceUrl;
		options.sourceUrl = undefined;

		// Create autocomplete settings.
		opt = {};
		opt.source = this.callbackWrapper(this.fetchAutocomplete);

		autocompleteOptions = $.extend({ },
				this.self('DEFAULT_PROPERTIES_'), opt, options);

		// Create the autocomplete field with the jqueryUI plug-in.
		this.textInput.autocomplete(autocompleteOptions);
		this.bind('autocompleteselect', this.itemSelected);
		this.bind('autocompletefocus', this.itemFocused);
		this.textInput.blur(this.callbackWrapper(this.textInputBlurHandler_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.AutocompleteHandler, $.pkp.classes.Handler);


	//
	// Private static properties
	//
	/**
	 * Default options
	 * @private
	 * @type {Object}
	 * @const
	 */
	$.pkp.controllers.AutocompleteHandler.DEFAULT_PROPERTIES_ = {
		// General settings
		minLength: 2
	};


	//
	// Private properties
	//
	/**
	 * The hidden input inside the autocomplete div that holds the value.
	 * @private
	 * @type {jQueryObject}
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.hiddenInput_ = null;


	/**
	 * The URL to be used for autocomplete
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.sourceUrl_ = null;


	//
	// Protected properties
	//
	/**
	 * The text input inside the autocomplete div that holds the label.
	 * @protected
	 * @type {jQueryObject}
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.textInput = null;


	//
	// Public Methods
	//
	/**
	 * Handle event triggered by selecting an autocomplete item
	 *
	 * @param {HTMLElement} autocompleteElement The element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Return value to be passed back
	 *  to jQuery.
	 */
	/*jslint unparam: true*/
	$.pkp.controllers.AutocompleteHandler.prototype.itemSelected =
			function(autocompleteElement, event, ui) {
		var $hiddenInput, $textInput;

		$hiddenInput = this.hiddenInput_;
		$textInput = this.textInput;

		// only update the text field if the item has a value
		// this allows us to return a 'no items' label with
		// an empty value.
		if (ui.item.value !== '') {
			$hiddenInput.val(ui.item.value);
			$textInput.val(ui.item.label);
		}
		return false;
	};
	/*jslint unparam: false*/


	/**
	 * Handle event triggered by moving through the autocomplete suggestions.
	 * The default behaviour is to insert the value in the text field.  This
	 * handler inserts the label instead.
	 *
	 * @param {HTMLElement} autocompleteElement The element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Return value to be passed back
	 *  to jQuery.
	 */
	/*jslint unparam: true*/
	$.pkp.controllers.AutocompleteHandler.prototype.itemFocused =
			function(autocompleteElement, event, ui) {
		var $textInput;

		$textInput = this.textInput;

		if (ui.item.value !== '') {
			$textInput.val(ui.item.label);
		}
		return false;
	};
	/*jslint unparam: false*/


	/**
	 * Fetch autocomplete results.
	 * @param {HTMLElement} callingElement The calling HTML element.
	 * @param {Object} request The autocomplete search request.
	 * @param {Function} response The response handler function.
	 */
	/*jslint unparam: true*/
	$.pkp.controllers.AutocompleteHandler.prototype.fetchAutocomplete =
			function(callingElement, request, response) {
		var $textInput;

		$textInput = this.textInput;
		$textInput.addClass('spinner');
		$.post(this.getAutocompleteUrl(), { term: request.term }, function(data) {
			$textInput.removeClass('spinner');
			response(data.content);
		}, 'json');
	};
	/*jslint unparam: false*/


	/**
	 * Get the autocomplete Url
	 * @return {?string} Autocomplete URL.
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.getAutocompleteUrl =
			function() {
		return this.sourceUrl_;
	};


	/**
	 * Set the autocomplete url
	 * @param {string} url Autocomplete URL.
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.setAutocompleteUrl =
			function(url) {
		this.sourceUrl_ = url;
	};


	//
	// Private Methods
	//
	/**
	 * Synchronize the input field and the hidden field.
	 * @private
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.synchronizeFields_ =
			function() {
		this.hiddenInput_.val(String(this.textInput.val()));
	};


	//
	// Private methods.
	//
	/**
	 * Text input element blur handler.
	 * @private
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.textInputBlurHandler_ =
			function() {
		// Make sure we clean the text input if user selected no option
		// from the available ones but leaved some text behind. This
		// is needed to avoid bad form validation and to make it clear to
		// users that they need to select an option.
		if (this.hiddenInput_.val() == '') {
			this.textInput.val('');
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
