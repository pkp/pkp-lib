/**
 * @file js/classes/features/OrderItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Base feature class for ordering grid items.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @param {$.controllers.grid.GridHandler} gridHandler The handler of
	 *  the grid element that this feature is attached to.
	 * @param {object} options Configuration of this feature.
	 */
	$.pkp.classes.features.OrderItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);

		this.itemsOrder_ = [];
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderItemsFeature,
			$.pkp.classes.features.Feature);


	//
	// Private properties.
	//
	/**
	 * Item sequence.
	 * @private
	 * @type {array}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.itemsOrder_ = null;


	//
	// Getters and setters.
	//
	/**
	 * Get the html element of the grid that this feature
	 * is attached to.
	 *
	 * @return {jQuery} Return the grid's HTML element.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getGridHtmlElement =
			function() {
		return this.gridHandler_.getHtmlElement();
	};


	/**
	 * Get the move item row action element selector.
	 * @return {string} Return the element selector.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getMoveItemRowActionSelector =
			function() {
		return '.orderable a.order_items';
	};
	
	
	/**
	 * Get the css classes used to stylize the ordering items.
	 * @returns {String}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getMoveItemClasses =
			function() {
		return 'pkp_helpers_moveicon ordering';		
	};


	//
	// Public methods.
	//
	/**
	 * Initialize this feature. Needs to be extended to implement
	 * specific initialization.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.init =
			function() {
		// Default implementation does nothing.
	};


	//
	// Protected template methods.
	//
	/**
	 * Setup the sortable plugin. Must be implemented in subclasses.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.setupSortablePlugin =
			function() {
		// Default implementation does nothing.
	};
	
	
	/**
	 * Called every time user drag and drop an item.
	 * @param {JQuery} contextElement The element this event occurred for.
	 * @param {Event} event The drag/drop event.
	 * @param {JQueryUI} ui The JQueryUI object.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.updateOrderCallback =
			function(contextElement, event, ui) {
		// The default implementation does nothing.
	};
	
	
	//
	// Protected methods.
	//
	/**
	 * Set items sequence store, using
	 * the sequence of the passed items.
	 *
	 * @param {jQuery} $items The items to be used to get the sequence information.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.storeOrder =
			function($items) {
		this.itemsOrder_ = [];
		var index, limit;
		for (index = 0, limit = $items.length; index < limit; index++) {
			var $item = $($items[index]);
			var elementId = $item.attr('id');
			this.itemsOrder_.push(elementId);
		}
	};


	/**
	 * Enable/disable the items drag mode.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleItemsDragMode =
			function() {
		var isOrdering = this.isOrdering_;
		var $rows = this.gridHandler_.getRows();
		var $orderableRows = $rows.filter('.orderable');
		var moveClasses = this.getMoveItemClasses();
		if (isOrdering) {
			$orderableRows.addClass(moveClasses);
		} else {
			$orderableRows.removeClass(moveClasses);
		}

		this.toggleMoveItemRowAction(isOrdering);
	};


	/**
	 * Apply (disabled or enabled) the sortable plugin on passed elements.
	 * @param {jQuery} $container The element that contain all the orderable items.
	 * @param {string} $itemsSelector The jQuery selector for orderable items.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.applySortablePluginOnElements =
			function($container, itemsSelector) {
		var isOrdering = this.isOrdering_;
		var orderItemCallback = this.gridHandler_.callbackWrapper(
				this.updateOrderCallback, this);
		$container.sortable({
			disabled: !isOrdering,
			items: itemsSelector,
			update: orderItemCallback,
			tolerance: 'pointer'});		
	};


	/**
	 * Get the data element id of all rows inside the passed 
	 * container, in the current order.
	 * @param {jQuery} $rowsContainer The element that contains the rows
	 * that will be used to retrieve the id.
	 * @return {Array} A sequence array with data element ids as values.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getRowsDataId =
			function($rowsContainer) {
		var index;
		var rowDataIds = [];
		for (index in this.itemsOrder_) {
			var $row = $('#' + this.itemsOrder_[index], $rowsContainer);
			if ($row.length < 1) continue;
			var rowDataId = this.gridHandler_.getRowDataId($row);
			rowDataIds.push(rowDataId);
		}

		return rowDataIds;
	};

	
	/**
	 * Show/hide the move item row action (position left).
	 * @param {boolean} enable New enable state.
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleMoveItemRowAction =
			function(enable) {
		var $rowActions = $('.row_actions', this.getGridHtmlElement()).children();
		var $moveItemRowAction = $(this.getMoveItemRowActionSelector(),
				this.getGridHtmlElement());
		if (enable) {
			$rowActions.hide();
			$moveItemRowAction.show();
		} else {
			$rowActions.show();
			$moveItemRowAction.hide();
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
