/**
 * @file js/controllers/AutocompleteHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
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

		// Create autocomplete settings.
		var opt = {};
		opt.source = function(request, response) {
			$.post(options.source, { term: request.term }, function(data) {
				response(data.content);
			}, 'json');
		};
		var autocompleteOptions = $.extend({ },
				this.self('DEFAULT_PROPERTIES_'), opt);

		// Create the autocomplete field with the jqueryUI plug-in.
		this.textInput_.autocomplete(autocompleteOptions);
		this.bind('autocompleteselect', this.itemSelected);
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

		$hiddenInput.val(ui.item.value);
		$textInput.val(ui.item.label);
		return false;
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);
