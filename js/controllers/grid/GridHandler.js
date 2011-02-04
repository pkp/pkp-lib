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

		// Bind event handlers.
		this.bind('elementDeleted', this.deleteElement);
		this.bind('elementAdded', this.addElement);
		this.bind('elementsChanged', this.refreshGrid);

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
	 * Callback bound to the "element deleted" event.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "element deleted" event.
	 * @param {Event} event The "element deleted" event.
	 * @param {string} gridId The id of the grid that the deleted row
	 *  belongs to.
	 * @param {string} rowId The id of the row to be deleted.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.deleteElement =
			function(sourceElement, event, gridId, rowId) {

		// Check the grid.
		this.checkGridId_(gridId);

		var $grid = this.getHtmlElement(),
				$rowElement = $grid.find('.element' + rowId);

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


	/**
	 * Callback bound to the "element added" event.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "element deleted" event.
	 * @param {Event} event The "element deleted" event.
	 * @param {string} gridId The id of the grid that the deleted row
	 *  belongs to.
	 * @param {string} rowId The id of the row to be deleted.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.addElement =
			function(sourceElement, event, gridId, rowId) {

		// Check the grid.
		this.checkGridId_(gridId);

		// Fetch the row.
		$.get(this.fetchRowUrl_, {rowId: rowId},
				this.callbackWrapper(this.insertOrUpdateRow), 'json');
	};


	/**
	 * Callback to insert or update a row.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.insertOrUpdateRow =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			// Parse the HTML returned from the server.
			var $newRow = $(jsonData.content), newRowId = $newRow.attr('id');

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
		}
	};


	/**
	 * Callback bound to the "elements changed" event.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "refresh grid" event.
	 * @param {Event} event The "element deleted" event.
	 * @param {string} gridId The id of the grid to be refreshed.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.refreshGrid =
			function(sourceElement, event, gridId) {

		// Check the grid.
		this.checkGridId_(gridId);

		// Fetch the new grid data.
		$.get(this.fetchGridUrl_, null,
				this.callbackWrapper(this.replaceGridContent), 'json');
	};


	/**
	 * Callback to replace a grid's content.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.replaceGridContent =
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
	 * Check whether the given grid id corresponds to the id
	 * of the grid attached to this handler.
	 *
	 * @private
	 * @param {string} gridId The remote grid id.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.checkGridId_ =
			function(gridId) {

		// Check that grid and row ids are correct.
		if (gridId !== this.gridId_) {
			throw Error('The grid id of the ' +
					'event does not fit the grid id of the handler!');
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
