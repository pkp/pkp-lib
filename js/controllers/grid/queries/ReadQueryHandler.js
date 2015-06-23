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
		this.fetchParticipantsListUrl_ = options.fetchParticipantsListUrl;

		$containerElement.find('.sprite.add').click(
				this.callbackWrapper(this.showNoteFormHandler));

		$containerElement.bind('dataChanged', this.callbackWrapper(this.tester_));

		this.loadParticipantsList();
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


	/**
	 * The URL to be called to fetch a list of participants.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.
			prototype.fetchParticipantsListUrl_ = null;


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


	/**
	 * Load the participants list.
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.
			loadParticipantsList = function() {
		$.get(this.fetchParticipantsListUrl_,
				this.callbackWrapper(this.showFetchedParticipantsList_), 'json');
	};


	//
	// Private methods
	//
	/**
	 * Event handler that is called when the suggest username button is clicked.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.
			showFetchedNoteForm_ = function(ajaxContext, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$noteFormContainer = $('#newNotePlaceholder');

		$noteFormContainer.children().remove();
		$noteFormContainer.append(processedJsonData.content);
	};


	/**
	 * Event handler that is called when the participants list fetch is complete.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.
			showFetchedParticipantsList_ = function(ajaxContext, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$participantsListContainer = $('#participantsListPlaceholder');

		$participantsListContainer.children().remove();
		$participantsListContainer.append(processedJsonData.content);
	};


	/**
	 * Handler to redirect to the correct notification widget the
	 * notify user event.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "notifyUser" event.
	 * @param {Event} event The "notify user" event.
	 * @param {HTMLElement} triggerElement The element that triggered
	 * the "notifyUser" event.
	 * @private
	 */
	$.pkp.controllers.grid.queries.ReadQueryHandler.prototype.tester_ =
			function(sourceElement, event, triggerElement) {
		this.loadParticipantsList();
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));
