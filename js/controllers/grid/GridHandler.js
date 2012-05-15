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

		// We give a chance for this handler to initialize
		// before we initialize its features.
		this.initialize(options);

		this.initFeatures_(options.features);
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
	 * Set data and execute operations to initialize.
	 * @param {array} options Grid options.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.initialize =
			function(options) {
		// Bind the handler for the "elements changed" event.
		this.bind('dataChanged', this.refreshGridHandler);

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
	};


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
	 *
	 * @return {jQuery} The rows as a JQuery object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRows =
			function() {
		return $('.gridRow', this.getHtmlElement());
	};


	/**
	 * Get the id prefix of this grid.
	 * @return {string} The ID prefix of this grid.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getGridIdPrefix =
			function() {
		return 'component-' + this.gridId_;
	};


	/**
	 * Get the id prefix of this grid row.
	 * @return {string} The id prefix of this grid row.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRowIdPrefix =
			function() {
		return this.getGridIdPrefix() + '-row-';
	};


	/**
	 * Get the data element id of the passed grid row.
	 * @param {jQuery} $gridRow The grid row JQuery object.
	 * @return {string} The data element id of the passed grid row.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getRowDataId =
			function($gridRow) {
		var gridRowHtmlClasses = $gridRow.attr('class').split(' ');
		var rowDataIdPrefix = 'element';
		var index, rowDataId;
		for (index in gridRowHtmlClasses) {
			var startExtractPosition = gridRowHtmlClasses[index]
					.indexOf(rowDataIdPrefix);
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
	 * @param {jQuery} $newRow The new row to append.
	 * @param {jQuery} $gridBody The tbody container element.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.appendRow =
			function($newRow, $gridBody) {

		if ($gridBody === undefined) {
			$gridBody = this.getHtmlElement().find(this.bodySelector_);
		}
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
		var $controlRow = $(sourceElement).parents('tr').next('.row_controls');
		this.applyToggleRowActionEffect_($controlRow);
	};


	/**
	 * Hide all visible row actions.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.hideAllVisibleRowActions =
			function() {
		var $visibleControlRows = $('.row_controls:visible', this.getHtmlElement());
		var index, limit;
		for (index = 0, limit = $visibleControlRows.length; index < limit; index++) {
			this.applyToggleRowActionEffect_($($visibleControlRows[index]));
		}
	};


	/**
	 * Enable/disable all link actions inside this grid.
	 * @param {boolean} enable Enable/disable flag.
	 * @param {JQuery} $linkElements Link elements JQuery object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.changeLinkActionsState =
			function(enable, $linkElements) {
		if ($linkElements === undefined) {
			$linkElements = $('.pkp_controllers_linkAction', this.getHtmlElement());
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
			function(sequenceMap) {
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
	 */
	$.pkp.controllers.grid.GridHandler.prototype.updateControlRowsPosition =
			function() {
		var $rows = this.getRows();
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			var $controlRow = this.getControlRowByGridRow($row);
			if ($controlRow.length > 0) {
				$controlRow.insertAfter($row);
			}
		}
	};


	/**
	 * Call features hooks.
	 * @param {String} hookName The name of the hook.
	 * @param {Array} args The arguments array.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.callFeaturesHook =
			function(hookName, args) {
		var featureName;
		for (featureName in this.features_) {
			this.features_[featureName][hookName].
					apply(this.features_[featureName], args);
		}
	};


	/**
	 * Do common actions that all subclasses widgets needs to delete
	 * a row.
	 * @param {jQuery} $rowElement The row element to be deleted.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.doCommonDeleteRowActions =
			function($rowElement) {

		var $grid = this.getHtmlElement();

		// Check whether this is the last row.
		var lastRow = false;
		if ($grid.find('.gridRow').length === 1) {
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
	// Protected methods
	//
	/**
	 * Refresh either a single row of the grid or the whole grid.
	 *
	 * @protected
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {number=} opt_elementId The id of a data element that was
	 *  updated, added or deleted. If not given then the whole grid
	 *  will be refreshed.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.refreshGridHandler =
			function(sourceElement, event, opt_elementId) {

		if (opt_elementId) {
			// Retrieve a single row from the server.
			$.get(this.fetchRowUrl_, {rowId: opt_elementId},
					this.callbackWrapper(this.replaceRowResponseHandler_), 'json');
		} else {
			// Retrieve the whole grid from the server.
			$.get(this.fetchGridUrl_, null,
					this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
		}

		// Let the calling context (page?) know that the grids are being redrawn.
		this.trigger('gridRefreshRequested');
	};


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

		this.doCommonDeleteRowActions($rowElement);
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
	 * @param {jQuery} $gridRow The grid row JQuery object.
	 * @return {jQuery} The control row JQuery object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.getControlRowByGridRow =
			function($gridRow) {
		var rowId = $gridRow.attr('id');
		var controlRowId = rowId + '-control-row';
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
	 * @param {jQuery} $controlRow The control row JQuery object.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.applyToggleRowActionEffect_ =
			function($controlRow) {
		var delay = 300;
		var $row = $controlRow.prev().find('td:not(.indent_row)');
		if ($controlRow.is(':visible')) {
			setTimeout(function() {
				$row.removeClass('no_border');
			}, delay);
		} else {
			$row.addClass('no_border');
		}
		$controlRow.toggle(delay);
		clearTimeout();
	};


	/**
	 * Add a grid feature.
	 * @private
	 * @param {string} id Feature id.
	 * @param {$.pkp.classes.features.Feature} $feature The grid
	 * feature to be added.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.addFeature_ =
			function(id, $feature) {
		if (!this.features_) {
			this.features_ = new Array();
		}
		this.features_[id] = $feature;
	};


	/**
	 * Add grid features.
	 * @private
	 * @param {Array} features The features options array.
	 */
	$.pkp.controllers.grid.GridHandler.prototype.initFeatures_ =
			function(features) {
		var id;
		for (id in features) {
			var $feature =
					($.pkp.classes.Helper.objectFactory(
							features[id].JSClass,
							[this, features[id].options]));

			this.addFeature_(id, $feature);
			this.features_[id].init();
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
