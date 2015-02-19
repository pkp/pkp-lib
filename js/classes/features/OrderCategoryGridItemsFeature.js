/**
 * @file js/classes/features/OrderCategoryGridItemsFeature.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderCategoryGridItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Feature for ordering category grid items.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderCategoryGridItemsFeature,
			$.pkp.classes.features.OrderGridItemsFeature);


	//
	// Extended methods from OrderItemsFeature.
	//
	/**
	 * Setup the sortable plugin.
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			setupSortablePlugin = function() {

		this.applySortPlgOnElements(
				this.getGridHtmlElement(), 'tbody.orderable', null);

		// FIXME *7610*: IE8 can't handle well ordering in both categories and
		// category rows.
		if ($.browser.msie && parseInt(
				$.browser.version.substring(0, 1), 10) <= 8) {
			return;
		}

		var $categories = this.gridHandler_.getCategories();

		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			this.applySortPlgOnElements($category, 'tr.orderable', null);
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			saveOrderHandler = function() {
		this.gridHandler_.updateEmptyPlaceholderPosition();
		return this.parent('saveOrderHandler');
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			cancelOrderHandler = function() {
		this.parent('cancelOrderHandler');
		var categorySequence = this.getCategorySequence_(this.itemsOrder_);
		this.gridHandler_.resequenceCategories(categorySequence);

		return false;
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			toggleItemsDragMode = function() {
		this.parent('toggleItemsDragMode');

		var isOrdering = this.isOrdering_;
		var $categories = this.gridHandler_.getCategories();

		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			this.toggleCategoryDragMode_($category);
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			addOrderingClassToRows = function() {
		var type = this.options_.type;
		if (type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_ONLY ||
				type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS) {
			var $categories = this.gridHandler_.getCategories();
			$categories.addClass('orderable');
		}

		if (type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY ||
				type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS) {
			this.parent('addOrderingClassToRows');
		}

		// We don't want to order category rows tr elements, so
		// remove any style that might be added by calling parent.
		var $categoryRows = this.gridHandler_.getCategoryRow();
		$categoryRows.removeClass('orderable');
	};


	//
	// Overriden method from OrderGridItemsFeature
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.getItemsDataId =
			function() {
		var categoriesSeq = this.getCategorySequence_(this.itemsOrder_);

		var itemsDataId = [];
		var index, limit;
		for (index = 0, limit = categoriesSeq.length; index < limit; index++) {
			var $category = $('#' + categoriesSeq[index]);
			var categoryRowsDataId = this.getRowsDataId($category);
			var categoryDataId = this.gridHandler_.getCategoryDataId($category);
			itemsDataId.push(
					{'categoryId': categoryDataId, 'rowsId': categoryRowsDataId });
		}

		return itemsDataId;
	};


	//
	// Private helper methods.
	//
	/**
	 * Enable/disable category drag mode.
	 * @param {jQuery} $category Category to set mode on.
	 * @private
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			toggleCategoryDragMode_ = function($category) {
		var isOrdering = this.isOrdering_;
		var $categoryRow = this.gridHandler_.getCategoryRow($category);
		var $categoryRowColumn = $('td:first', $categoryRow);
		var moveClasses = this.getMoveItemClasses();

		if (isOrdering) {
			$categoryRowColumn.addClass(moveClasses);
		} else {
			$categoryRowColumn.removeClass(moveClasses);
		}
	};


	/**
	 * Get the categories sequence, based on the passed items order.
	 * @param {Array} itemsOrder Items order.
	 * @return {Array} A sequence array with the category data id as values.
	 * @private
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			getCategorySequence_ = function(itemsOrder) {
		var index, limit;
		var categorySequence = [];
		for (index = 0, limit = itemsOrder.length; index < limit; index++) {
			var categoryDataId = this.gridHandler_.getCategoryDataIdByRowId(
					itemsOrder[index]);
			var categoryId = this.gridHandler_.getCategoryIdPrefix() + categoryDataId;
			if ($.inArray(categoryId, categorySequence) > -1) {
				continue;
			}
			categorySequence.push(categoryId);
		}

		return categorySequence;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
