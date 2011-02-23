/**
 * @defgroup js_controllers_grid
 */
// Define the namespace.
$.pkp.controllers.grid = $.pkp.controllers.grid || {};


/**
 * @file js/controllers/grid/GridHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Grid row handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.GridHandler = function($grid, options) {
		this.parent($grid, options);

		// Bind the handler for the "elements changed" event.
		this.bind('dataChanged', this.refreshGrid);

		// Save the ID of this row and it's grid.
		this.gridId_ = options.gridId;

		// Save the URL to fetch a row.
		this.fetchRowUrl_ = options.fetchRowUrl;

		// Save the URL to fetch the entire grid
		this.fetchGridUrl_ = options.fetchGridUrl;

		// Save the selector for the grid body.
		this.bodySelector_ = options.bodySelector;
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.GridHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The id of the grid.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.gridId_ = null;


	/**
	 * The URL to fetch a grid row.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.fetchRowUrl_ = null;


	/**
	 * The URL to fetch the entire grid.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.fetchGridUrl_ = null;


	/**
	 * The selector for the grid body.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.bodySelector_ = null;


	//
	// Public methods
	//
	/**
	 * Refresh either a single row of the grid or the whole grid.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {number=} elementId The id of a data element that was
	 *  updated, added or deleted. If not given then the whole grid
	 *  will be refreshed.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.refreshGrid =
			function(sourceElement, event, elementId) {

		if (elementId) {
			// Retrieve a single row from the server.
			$.get(this.fetchRowUrl_, {rowId: elementId},
					this.callbackWrapper(this.replaceRow), 'json');
		} else {
			// Retrieve the whole grid from the server.
			$.get(this.fetchGridUrl_, null,
					this.callbackWrapper(this.replaceGrid), 'json');
		}
	};


	/**
	 * Callback to insert, remove or replace a row after an
	 * element has been inserted, update or deleted.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.replaceRow =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			if (jsonData.rowNotFound) {
				// The server reported that this row no
				// longer exists in the database so let's
				// delete it.
				this.deleteRow_(jsonData.rowNotFound);
			} else {
				// The server returned mark-up to replace
				// or insert the row.
				this.insertOrReplaceRow_(jsonData.content);
			}
		}
	};


	/**
	 * Callback to replace a grid's content.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.replaceGrid =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			// Get the grid that we're updating
			var $grid = this.getHtmlElement();

			// Replace the grid content
			$grid.html(jsonData.content);
		}
	};


	//
	// Private methods
	//
	/**
	 * Helper that inserts or replaces a row.
	 *
	 * @private
	 *
	 * @param {string} rowContent The new mark-up of the row.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.insertOrReplaceRow_ =
			function(rowContent) {

		// Parse the HTML returned from the server.
		var $newRow = $(rowContent), newRowId = $newRow.attr('id');

		// Does the row exist already?
		var $grid = this.getHtmlElement(),
				$existingRow = $grid.find('#' + newRowId);
		if ($existingRow.length > 1) {
			throw Error('There were ' + $existingRow.length +
					' rather than 0 or 1 rows to be replaced!');
		}

		// Hide the empty grid row placeholder.
		var $emptyElement = $grid.find('.empty');
		$emptyElement.hide();

		if ($existingRow.length === 1) {
			// Update row.
			$existingRow.replaceWith($newRow);
		} else {
			// Insert row.
			var $gridBody = this.getHtmlElement().find(this.bodySelector_);
			$gridBody.append($newRow);
		}
	};


	/**
	 * Helper that deletes the given row.
	 *
	 * @private
	 *
	 * @param {number} rowId The ID of the row to be updated.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.deleteRow_ =
			function(rowId) {

		var $grid = this.getHtmlElement(),
				$rowElement = $grid.find('.element' + rowId);

		// Sometimes we get a delete event before the
		// row has actually been inserted (e.g. when deleting
		// elements due to a cancel action or similar).
		if ($rowElement.length === 0) {
			return;
		}

		// Check whether we really only match one row.
		if ($rowElement.length !== 1) {
			throw Error('There were ' + $rowElement.length +
					' rather than 1 rows to delete!');
		}

		// Check whether this is the last row.
		var lastRow = false;
		if ($rowElement.siblings().length === 0) {
			lastRow = true;
		}

		// Delete the row.
		var $emptyElement = $grid.find('.empty');
		$rowElement.fadeOut(500, function() {
			$(this).remove();
			if (lastRow) {
				$emptyElement.fadeIn(500);
			}
		});
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
