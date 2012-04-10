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
		return '.orderable a.add_item';
	};


	//
	// Public methods
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
	// Protected methods.
	//
	/**
	 * Set items sequence store, using
	 * the current rendered rows position.
	 *
	 * @param {jQuery} $rows The rows to store ordering information for.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.storeOrder =
			function($rows) {
		this.itemsOrder_ = [];
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			var elementId = $row.attr('id');
			this.itemsOrder_.push(elementId);
		}
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


	/**
	 * Enable/disable the items drag mode.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleItemsDragMode =
			function() {
		var isOrdering = this.isOrdering_;
		var $rows = this.gridHandler_.getRows();
		var moveClasses = 'pkp_helpers_moveicon ordering';
		if (isOrdering) {
			$rows.addClass(moveClasses);
		} else {
			$rows.removeClass(moveClasses);
		}

		this.toggleMoveItemRowAction_(isOrdering);
	};


	/**
	 * Apply (disabled or enabled) the sortable plugin on orderable rows.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.setupSortablePlugin =
			function() {
		var isOrdering = this.isOrdering_;
		var orderItemCallback = this.gridHandler_.callbackWrapper(
				this.updateOrderCallback, this);
		this.getGridHtmlElement().sortable({
			disabled: !isOrdering,
			items: 'tr.orderable',
			update: orderItemCallback,
			tolerance: 'pointer'});
	};


	/**
	 * Get the data element id of all rows, in the current order.
	 * @return {Array} A sequence array with data element ids as values.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getRowDataIds =
			function() {
		var index;
		var rowDataIds = [];
		for (index in this.itemsOrder_) {
			var $row = $('#' + this.itemsOrder_[index],
					this.gridHandler_.getHtmlElement());
			var rowDataId = this.gridHandler_.getRowDataId($row);
			rowDataIds.push(rowDataId);
		}

		return rowDataIds;
	};


	//
	// Private helper methods.
	//
	/**
	 * Show/hide the move item row action (position left).
	 * @param {boolean} enable New enable state.
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleMoveItemRowAction_ =
			function(enable) {
		$rowActions = $('.row_actions', this.getGridHtmlElement()).children();
		$moveItemRowAction = $(this.getMoveItemRowActionSelector(),
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
