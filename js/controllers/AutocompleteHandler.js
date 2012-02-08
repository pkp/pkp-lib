/**
 * @file js/controllers/AutocompleteHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	 * @param {jQuery} $autocompleteField the wrapped HTML input element element.
	 * @param {Object} options options to be passed
	 *  into the jqueryUI autocomplete plugin.
	 */
	$.pkp.controllers.AutocompleteHandler = function($autocompleteField, options) {
		this.parent($autocompleteField, options);

		// Get the text input inside of this Div.
		this.textInput_ = $autocompleteField.find(':text');

		// Get the text input inside of this Div.
		this.hiddenInput_ = $autocompleteField.find(':hidden');

		// Get the URL passed in
		this.sourceUrl_ = options.sourceUrl;

		// Create autocomplete settings.
		var opt = {};
		opt.source = this.callbackWrapper(this.fetchAutocomplete);

		var autocompleteOptions = $.extend({ },
				this.self('DEFAULT_PROPERTIES_'), opt);

		// Create the autocomplete field with the jqueryUI plug-in.
		this.textInput_.autocomplete(autocompleteOptions);
		this.bind('autocompleteselect', this.itemSelected);
		this.bind('autocompletefocus', this.itemFocused);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.AutocompleteHandler, $.pkp.classes.Handler);


	//
	// Private static properties
	//
	/**
	 * The text input inside the autocomplete div that holds the label.
	 * @private
	 * @type {HTMLElement}
	 */
	$.pkp.controllers.AutocompleteHandler.textInput_ = null;


	/**
	 * The hidden input inside the autocomplete div that holds the value.
	 * @private
	 * @type {HTMLElement}
	 */
	$.pkp.controllers.AutocompleteHandler.hiddenInput_ = null;


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
	 * The URL to be used for autocomplete
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.AutocompleteHandler.sourceUrl_ = null;


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
	$.pkp.controllers.AutocompleteHandler.prototype.itemSelected =
			function(autocompleteElement, event, ui) {

		var $hiddenInput = this.hiddenInput_;
		var $textInput = this.textInput_;

		// only update the text field if the item has a value
		// this allows us to return a 'no items' label with
		// an empty value.
		if (ui.item.value != '') {
			$hiddenInput.val(ui.item.value);
			$textInput.val(ui.item.label);
		}
		return false;
	};


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
	$.pkp.controllers.AutocompleteHandler.prototype.itemFocused =
			function(autocompleteElement, event, ui) {

		var $textInput = this.textInput_;

		if (ui.item.value != '') {
			$textInput.val(ui.item.label);
		}
		return false;
	};


	/**
	 * Search for the users who are availble
	 * @param {HTMLElement} callingElement The calling HTML element.
	 * @param {Object} request The autocomplete search request.
	 * @param {Object} response The response handler function.
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.fetchAutocomplete =
			function(callingElement, request, response) {
		var $textInput = this.textInput_;
		$textInput.addClass('spinner');
		$.post(this.getAutocompleteUrl(), { term: request.term }, function(data) {
			$textInput.removeClass('spinner');
			response(data.content);
		}, 'json');
	};


	/**
	 * Get the autocomplete Url
	 * @return {String} Autocomplete URL.
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.getAutocompleteUrl =
			function() {
		return this.sourceUrl_;
	};


	/**
	 * Set the autocomplete url
	 * @param {String} url Autocomplete URL.
	 */
	$.pkp.controllers.AutocompleteHandler.prototype.setAutocompleteUrl =
			function(url) {
		this.sourceUrl_ = url;
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);
