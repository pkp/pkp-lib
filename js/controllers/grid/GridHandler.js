/**
 * @defgroup js_controllers_grid
 */
// Define the namespace.
$.pkp.controllers.grid = $.pkp.controllers.grid || {};


/**
 * @file js/controllers/grid/GridHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
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
		this.bind('dataChanged', this.refreshGridHandler_);

		// Bind the handler for the "add new row" event.
		this.bind('addRow', this.addRowHandler_);

		// Handle grid filter events.
		this.bind('formSubmitted', this.refreshGridWithFilterHandler_);

		// Save the ID of this grid.
		this.gridId_ = options.gridId;

		// Save the URL to fetch a row.
		this.fetchRowUrl_ = options.fetchRowUrl;

		// Save the URL to fetch the entire grid
		this.fetchGridUrl_ = options.fetchGridUrl;

		// Save the selector for the grid body.
		this.bodySelector_ = options.bodySelector;

		// Show/hide row action feature.
		this.activateRowActions_();

		if (options.hasOrderingItems) {
			this.initFeatures_(options);
		}

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

	/**
	 * This grid features.
	 * @private
	 * @type {object}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.features_ = null;


	//
	// Protected methods
	//
	/**
	 * Get the fetch row URL.
	 * @return {?string} URL to the "fetch row" operation handler.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getFetchRowUrl =
			function() {

		return this.fetchRowUrl_;
	};


	/**
	 * Get all grid rows.
	 * @private
	 *
	 * @returns {jQuery}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRows =
			function () {
		return $('.gridRow', this.getHtmlElement());
	};


	/**
	 * Get the id prefix of this grid.
	 * @private
	 *
	 * @returns {string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getGridIdPrefix =
			function () {
		return 'component-' + this.gridId_;
	};


	/**
	 * Get the id prefix of this grid rows.
	 * @private
	 *
	 * @returns {string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRowIdPrefix =
			function () {
		return this.getGridIdPrefix() + '-row-';
	};

	/**
	 * Get the data element id of the passed grid row.
	 * @param {jQuery} $gridRow
	 * @returns {string}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRowDataId =
			function ($gridRow) {
		var gridRowHtmlClasses = $gridRow.attr('class').split(' ');
		var rowDataIdPrefix = 'element';
		var index, rowDataId;
		for (index in gridRowHtmlClasses) {
			var startExtractPosition = gridRowHtmlClasses[index].indexOf(rowDataIdPrefix);
			if (startExtractPosition != -1) {
				rowDataId = gridRowHtmlClasses[index].slice(rowDataIdPrefix.length);
				break;
			}
		}
		
		return rowDataId;
	};


	/**
	 * Append a new row to the end of the list.
	 * @protected
	 * @param {HTMLElement} $newRow The new row to append.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.appendRow =
			function($newRow) {

		var $gridBody = this.getHtmlElement().find(this.bodySelector_);
		$gridBody.append($newRow);
	};


	//
	// Public methods
	//
	/**
	 * Show/hide row actions.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.toggleRowActions =
			function(sourceElement, event) {

		// Toggle the row actions.
		$controlRow = $(sourceElement).parents('tr').next('.row_controls');
		this.applyToggleRowActionEffect_($controlRow);
	};


	/**
	 * Hide all visible row actions.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.hideAllVisibleRowActions =
			function() {
		$visibleControlRows = $('.row_controls:visible', this.getHtmlElement());
		var index, limit;
		for (index = 0, limit = $visibleControlRows.length; index < limit; index++) {
			this.applyToggleRowActionEffect_($($visibleControlRows[index]));
		}
	};


	/**
	 * Enable/disable all link actions inside this grid.
	 * @param {boolean} enable
	 */
	$.pkp.controllers.grid.GridHandler.prototype.changeLinkActionsState =
			function(enable, $linkElements) {
		if ($linkElements == undefined) {
			var $linkElements = $('.pkp_controllers_linkAction', this.getHtmlElement());
		}
		$linkElements.each(function() {
			var linkHandler = $.pkp.classes.Handler.getHandler($(this));
			if (enable) {
				linkHandler.enableLink();
			} else {
				linkHandler.disableLink();
			}
		});
	};


	/**
	 * Re-sequence all grid rows based on the passed sequence map.
	 * @param {array} sequenceMap A sequence array with the row id as value.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.resequenceRows =
			function (sequenceMap) {
		var rowId, index;
		for (index in sequenceMap) {
			rowId = sequenceMap[index];
			var $row = $('#' + rowId);
			this.appendRow($row);
		}
		this.updateControlRowsPosition();
	};


	/**
	 * Move all grid control rows to their correct position,
	 * below of each correspondent data grid row.
	 * @returns
	 */
	$.pkp.controllers.grid.GridHandler.prototype.updateControlRowsPosition =
			function () {
		var $rows = this.getRows();
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			var $controlRow = this.getControlRowByGridRow($row);
			if ($controlRow.length > 0) $controlRow.insertAfter($row);
		}
	};


	/**
	 * Refresh the grid after its filter has changed.
	 *
	 * @private
	 *
	 * @param {$.pkp.controllers.form.ClientFormHandler} filterForm
	 *  The filter form.
	 * @param {Event} event A "formSubmitted" event.
	 * @param {string} filterData Serialized filter data.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.refreshGridWithFilterHandler_ =
			function(filterForm, event, filterData) {

		// Retrieve the grid from the server and add the
		// filter data as form data.
		$.post(this.fetchGridUrl_, filterData,
				this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
	};


	//
	// Private methods
	//
	/**
	 * Refresh either a single row of the grid or the whole grid.
	 *
	 * @private
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {number=} elementId The id of a data element that was
	 *  updated, added or deleted. If not given then the whole grid
	 *  will be refreshed.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.refreshGridHandler_ =
			function(sourceElement, event, elementId) {

		if (elementId) {
			// Retrieve a single row from the server.
			$.get(this.fetchRowUrl_, {rowId: elementId},
					this.callbackWrapper(this.replaceRowResponseHandler_), 'json');
		} else {
			// Retrieve the whole grid from the server.
			$.get(this.fetchGridUrl_, null,
					this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
		}

		// Let the calling context (page?) know that the grids are being redrawn.
		this.trigger('gridRefreshRequested');
	};


	/**
	 * Add a new row to the grid.
	 *
	 * @private
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {Object} params The request parameters to use to generate
	 *  the new row.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.addRowHandler_ =
			function(sourceElement, event, params) {

		// Retrieve a single new row from the server.
		$.get(this.fetchRowUrl_, params,
				this.callbackWrapper(this.replaceRowResponseHandler_), 'json');
	};


	/**
	 * Callback to insert, remove or replace a row after an
	 * element has been inserted, update or deleted.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.replaceRowResponseHandler_ =
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

				// Refresh row action event binding.
				this.activateRowActions_();
			}
		}
	};


	/**
	 * Callback to replace a grid's content.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.replaceGridResponseHandler_ =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			// Get the grid that we're updating
			var $grid = this.getHtmlElement();

			// Replace the grid content
			$grid.replaceWith(jsonData.content);

			// Refresh row action event binding.
			this.activateRowActions_();
		}
	};


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
				$existingRow = newRowId ? $grid.find('#' + newRowId) : {};

		if ($existingRow.length > 1) {
			throw Error('There were ' + $existingRow.length +
					' rather than 0 or 1 rows to be replaced!');
		}

		// Does this grid have a different number of columns than the
		// existing grid? If yes, we have to redraw the whole grid so
		// new columns get added/removed to match row.
		var numColumns = $grid.find('th').length;
		var numCellsInNewRow = $newRow.first('tr').find('td').length;
		if (numColumns != numCellsInNewRow) {
			$.get(this.fetchGridUrl_, null,
					this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
		} else {
			// Hide the empty grid row placeholder.
			var $emptyElement = $grid.find('.empty');
			$emptyElement.hide();

			if ($existingRow.length === 1) {
				// Update row.
				this.deleteControlsRow_($existingRow);
				$existingRow.replaceWith($newRow);
			} else {
				// Insert row.
				this.appendRow($newRow);
			}
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

		// Remove the controls row (do this before check for siblings below).
		this.deleteControlsRow_($rowElement);

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
	 * Helper that deletes the row of controls (if present).
	 *
	 * @private
	 *
	 * @param {jQuery} $row The row whose matching control row should be deleted.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.deleteControlsRow_ =
			function($row) {
		if ($row.next().is('tr') && $row.next().hasClass('row_controls')) {
			$row.next().remove();
		}
	};

	/**
	 * Get the control row for the passed the grid row.
	 * @param {jQuery} $gridRow
	 * @returns {jQuery}
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getControlRowByGridRow =
			function ($gridRow) {
		var rowDataId = this.getRowDataId($gridRow);
		var controlRowId = this.getRowIdPrefix() + rowDataId + '-control-row';
		return $('#' + controlRowId);
	};


	/**
	 * Helper that attaches click events to row actions.
	 * @private
	 */
	$.pkp.controllers.grid.GridHandler.prototype.activateRowActions_ =
			function() {

		var $grid = this.getHtmlElement();
		$grid.find('a.settings').unbind('click').bind('click',
				this.callbackWrapper(this.toggleRowActions));
	};


	/**
	 * Apply the effect for hide/show row actions.
	 * @private
	 *
	 * @param {jQuery} $controlRow
	 */
	$.pkp.controllers.grid.GridHandler.prototype.applyToggleRowActionEffect_ =
			function($controlRow) {
		$controlRow.toggle(300);
	};


	/**
	 * Add grid features.
	 * @private
	 */
	$.pkp.controllers.grid.GridHandler.prototype.initFeatures_ =
			function(options) {
		var $orderItemsFeature =
			/** @type {$.pkp.classes.features.OrderItemsFeature} */
			($.pkp.classes.Helper.objectFactory(
					'$.pkp.classes.features.OrderGridItemsFeature',
					[this, {
						'orderButton': $('a.order_items', this.getHtmlElement()),
						'finishControl': $('#' + this.getGridIdPrefix() + '-order-finish-controls'),
						'saveItemsSequenceUrl': options.saveItemsSequenceUrl
					}]));

		this.features_ = {'orderItems': $orderItemsFeature};
		this.features_.orderItems.init();
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
