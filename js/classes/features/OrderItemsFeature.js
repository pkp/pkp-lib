/**
 * @defgroup js_classes_features
 */
// Define the namespace
$.pkp.classes.features = $.pkp.classes.features || {};

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
	 * @param {$.controllers.grid.GridHandler} gridHandler The handler of the grid element that
	 * this feature is attached to.
	 * @param {object} options Configuration of this feature.
	 */
	$.pkp.classes.features.OrderItemsFeature =
			function(gridHandler, options) {

		this.gridHandler_ = gridHandler;
		this.options_ = options;
		this.itemsOrder_ = new Array();
	};


	//
	// Private properties.
	//
	/**
	 * The grid that this feature is attached to.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.gridHandler_ = null;

	/**
	 * This feature configuration options.
	 * @private
	 * @type {object}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.options_ = null;

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
	 * @private
	 *
	 * @returns {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getGridHtmlElement =
			function () {
		return this.gridHandler_.getHtmlElement();
	};


	/**
	 * Get the move item row action element selector.
	 * @returns {string}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getMoveItemRowActionSelector =
			function () {
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
	 * @param {jQuery} $rows
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.storeOrder =
			function ($rows) {
		this.itemsOrder_ = new Array();
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			var elementId = $row.attr('id');
			this.itemsOrder_.push(elementId);
		}
	};

	/**
	 * Called every time user drag and drop an item.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.updateOrderCallback =
			function(contextElement, event, ui) {
		// The default implementation does nothing.
	};

	/**
	 * Enable/disable the items drag mode.
	 * @para {boolean} enable
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleItemsDragMode =
			function(enable) {
		var isOrdering = this.isOrdering_;
		var $rows = this.gridHandler_.getRows();
		var moveClasses = 'pkp_helpers_moveicon ordering';
		if (enable) {
			$rows.addClass(moveClasses);
		} else {
			$rows.removeClass(moveClasses);
		};

		this.toggleMoveItemRowAction_(enable);
	};

	/**
	 * Apply (disabled or enabled) the sortable plugin on orderable rows.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.setupSortablePlugin =
			function(enable) {
		var isOrdering = this.isOrdering_;
		var orderItemCallback = this.gridHandler_.callbackWrapper(this.updateOrderCallback, this);
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
		var rowDataIds = new Array();
		for (index in this.itemsOrder_) {
			var $row = $("#" + this.itemsOrder_[index], this.gridHandler_.getHtmlElement());
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
	 * @param {boolean} enable
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleMoveItemRowAction_ =
			function (enable) {
		$rowActions = $('.row_actions', this.getGridHtmlElement()).children();
		$moveItemRowAction = $(this.getMoveItemRowActionSelector(), this.getGridHtmlElement());
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
