/**
 * @defgroup js_controllers_grid_query
 */
/**
 * @file js/controllers/grid/query/ReadQueryHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReadQueryHandler
 * @ingroup js_controllers_grid_query
 *
 * @brief Catalog carousel handler.
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.queries =
			$.pkp.controllers.grid.queries || { };


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $containerElement The HTML element encapsulating
	 *  the carousel container.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler =
			function($containerElement, options) {

		this.fetchNoteFormUrl_ = options.fetchNoteFormUrl;

		$containerElement.find('.sprite.add').click(
				this.callbackWrapper(this.showNoteFormHandler));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.queries.ReadQueryHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch a spotlight item via autocomplete.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.
			prototype.fetchNoteFormUrl_ = null;


	//
	// Public methods
	//
	/**
	 * Event handler that is called when the suggest username button is clicked.
	 * @param {HTMLElement} element The checkbox input element.
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.
			showNoteFormHandler = function(element) {
		$(element).hide();
		$.get(this.fetchNoteFormUrl_,
				this.callbackWrapper(this.showFetchedNoteForm_), 'json');
	};


	//
	// Protected methods
	//
	/**
	 * Event handler that is called when the suggest username button is clicked.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @protected
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.
			showFetchedNoteForm_ = function(ajaxContext, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$noteFormContainer = $('#newNotePlaceholder');

		$noteFormContainer.children().remove();
		$noteFormContainer.append(processedJsonData.content);
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));
