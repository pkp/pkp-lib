/**
 * @file js/controllers/UrlInDivHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UrlInDivHandler
 * @ingroup js_controllers
 *
 * @brief "URL in div" handler
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $divElement the wrapped div element.
	 * @param {Object} options options to be passed.
	 */
	$.pkp.controllers.UrlInDivHandler = function($divElement, options) {
		this.parent($divElement, options);

		// Store the URL (e.g. for reloads)
		this.sourceUrl_ = options.sourceUrl;

		// Load the contents.
		this.reload();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.UrlInDivHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The URL to be used for data loaded into this div
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.UrlInDivHandler.sourceUrl_ = null;


	//
	// Public Methods
	//
	/**
	 * Reload the div contents.
	 */
	$.pkp.controllers.UrlInDivHandler.prototype.reload = function() {
		$.get(this.sourceUrl_,
				this.callbackWrapper(this.handleLoadedContent_), 'json');
	};


	//
	// Private Methods
	//
	/**
	 * Handle a callback after a load operation returns.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @return {boolean} Message handling result.
	 * @private
	 */
	$.pkp.controllers.UrlInDivHandler.prototype.handleLoadedContent_ =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData.status === true) {
			this.getHtmlElement().hide().html(jsonData.content).fadeIn(400);
		} else {
			// Alert that loading failed.
			alert(jsonData.content);
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

		if (ui.item.value !== '') {
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
