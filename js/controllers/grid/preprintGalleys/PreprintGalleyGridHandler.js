/**
 * @file js/controllers/grid/preprintGalleys/PreprintGalleyGridHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintGalleyGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Preprint galley grid handler.
 */
(function($) {

	// Define the namespace.
	$.pkp.controllers.grid.preprintGalleys =
			$.pkp.controllers.grid.preprintGalleys || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.preprintGalleys.PreprintGalleyGridHandler =
			function($grid, options) {

		this.parent($grid, options);

		// Bind the handler for the "upload a file" event.
		$grid.bind('uploadFile', this.callbackWrapper(this.uploadFileHandler_));
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.preprintGalleys
			.PreprintGalleyGridHandler, $.pkp.controllers.grid.GridHandler);


	//
	// Private methods
	//
	/**
	 * Add a new row to the grid.
	 *
	 * @private
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {string} rowId The row ID that triggered the event.
	 */
	$.pkp.controllers.grid.preprintGalleys.PreprintGalleyGridHandler.
			prototype.uploadFileHandler_ = function(sourceElement, event, rowId) {

		// FIXME: Inter-widget messaging is needed here.
		var selector = 'a[id^="component-grid-preprintgalleys-preprintgalleygrid-' +
				'row-' + rowId + '-addFile-button-"]';
		$.when($(selector)).then(function() {
			$(function() {
				$(selector).click();
			});
		});
	};


}(jQuery));
