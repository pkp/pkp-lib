/**
 * @file js/classes/features/OrderCategoryGridItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
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

		var $categories, index, limit, $category;

		this.applySortPlgOnElements(
				this.getGridHtmlElement(), 'tbody.orderable', null);

		// FIXME *7610*: IE8 can't handle well ordering in both categories and
		// category rows.
		if ($.browser.msie && parseInt(
				$.browser.version.substring(0, 1), 10) <= 8) {
			return;
		}

		$categories = this.gridHandler_.getCategories();
		for (index = 0, limit = $categories.length; index < limit; index++) {
			$category = $($categories[index]);
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

		var categorySequence = this.getCategorySequence_(this.itemsOrder_);
		this.parent('cancelOrderHandler');
		this.gridHandler_.resequenceCategories(categorySequence);

		return false;
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			toggleItemsDragMode = function() {
		this.parent('toggleItemsDragMode');

		var isOrdering = this.isOrdering_,
				$categories = this.gridHandler_.getCategories(),
				index, limit, $category;

		for (index = 0, limit = $categories.length; index < limit; index++) {
			$category = $($categories[index]);
			this.toggleCategoryDragMode_($category);
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.
			addOrderingClassToRows = function() {

		var type = this.options_.type, $categories;

		if (type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_ONLY ||
				type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS) {
			$categories = this.gridHandler_.getCategories();
			$categories.addClass('orderable');
		}

		if (type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY ||
				type == $.pkp.cons.ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS) {
			this.parent('addOrderingClassToRows');
		}

		// We don't want to order category rows tr elements, so
		// remove any style that might be added by calling parent.
		this.gridHandler_.getCategoryRow().removeClass('orderable');
	};


	//
	// Overriden method from OrderGridItemsFeature
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.getItemsDataId =
			function() {
		var categoriesSeq = this.getCategorySequence_(this.itemsOrder_),
				itemsDataId = [],
				index, limit,
				$category, categoryRowsDataId, categoryDataId;

		for (index = 0, limit = categoriesSeq.length; index < limit; index++) {
			$category = $('#' + categoriesSeq[index]);
			categoryRowsDataId = this.getRowsDataId($category);
			categoryDataId = this.gridHandler_.getCategoryDataId($category);
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
		var isOrdering = this.isOrdering_,
				$categoryRow = this.gridHandler_.getCategoryRow($category),
				$categoryRowColumn = $('td:first', $categoryRow),
				moveClasses = this.getMoveItemClasses();

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
		var index, limit, categorySequence = [], categoryDataId, categoryId;
		for (index = 0, limit = itemsOrder.length; index < limit; index++) {
			categoryDataId = this.gridHandler_
					.getCategoryDataIdByRowId(itemsOrder[index]);
			categoryId = this.gridHandler_.getCategoryIdPrefix() + categoryDataId;
			if ($.inArray(categoryId, categorySequence) > -1) {
				continue;
			}
			categorySequence.push(categoryId);
		}

		return categorySequence;
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
